<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_line.class.php");

class block_reportsdash_usage_report extends block_reportsdash_report
{

    const DEFAULT_PAGE_SIZE=25;
    const CRON_PERIOD=82800;  //Dailyish - wait at least 23hrs and then run sometime between 2 and 3

    const SETTING_NAME='usage_tables_version';

    static function version()
    {
        return 2013060601;
    }

    //The cron summarises data from the heavydb; don't run it if not available
    static function has_cron()
    {
        return static::heavydb_active();

    }

    // In order to not have any writing to the heavy db, this cron
    // now reads yesterday's log from the heavy DB but saves the
    // result in the standard DB, which is where the report reads it from
    static function cron($force=false)
    {
        if(!parent::cron())
            return false;

        mtrace('Starting Usage Report cron');

        global $DB,$CFG;
        require_once("$CFG->libdir/dmllib.php");

        $B='block_reportsdash';
        $L='block_reportsdash';

        mtrace('Starting Update Report cron query');

        $now=time();

        if(!$lastcron=get_config($B,'last_usage_cron'))
        {
            $lastcron=$now-60*60*24*365;
        }

        //Round down to midnight
        $lastcron=86400*(floor($lastcron/86400));

        $flag=false;

        $hr=date('H',$now);

        //2am cron run
        if($force or (get_config('block_reportsdash','useheavyhost')
                      and block_reportsdash_report::heavy_config()
                      and $now-$lastcron > static::CRON_PERIOD
                      and $hr=='2'))
        {

            // Major change: The summary table remains on live but the "live" log table that
            // is used is now the heavy one which should never be truncated.
            // The problem is that this means the data has to be moved from one
            // host to the other.

            $mydb=static::getHeavyDB();

            $pfx=$CFG->prefix;

            //The following may take a while and we don't want that to interfere with
            //the record of the lastcron, so we store the time now.
            $run=time();

            ($topid=$DB->get_field_sql("select max(lid) from {block_reportsdash_usecron}") or $topid=0);

            $data = false;
            //use the log manager class
            $manager = get_log_manager();

            $selectreaders = $manager->get_readers('\core\log\sql_internal_table_reader');

            if ($selectreaders) {

                $reader     =   $selectreaders['logstore_standard'];

                if ($reader->is_logging())   {

                    $tablename  =   $reader->get_internal_log_table_name();

                    $highid=$mydb->get_field_sql("select max(id) from {$pfx}{$tablename}");

                    // Log table has been truncated; throw away this data too.
                    // Keeping it causes too many issues.
                    if($highid < $topid)
                    {
                        $DB->execute("delete from {block_reportsdash_usecron}");
                        $topid=0;
                    }

                    mtrace("Droping old table");
                    $mydb->execute("DROP TABLE IF EXISTS tmp.buffer");
                    
                    mtrace("Creating new table");
                    $mydb->execute("create table tmp.buffer like {block_reportsdash_usecron}");
                    
                    //Time in the usecron is number of days since 1/1/1970. If you need seconds,
                    //just join it to the log table using lid (log id)
                    $sql=" INSERT INTO tmp.buffer (`time`,`hr`,`userid`,`lid`)
                            SELECT floor(timecreated/86400) time, hour(from_unixtime(timecreated)) as hr,  userid, id as lid
                                    from {$pfx}{$tablename}
                                    where target='course' and action='viewed'
                                    and component = 'core'
                                    and id>:topid";

                    mtrace("Executing: $sql ($lastcron) ($topid)");
                    $mydb->execute($sql,array('topid'=>$topid));

                    mtrace("Pulling data across");
                    
                    //Transfer the data back to live
                    $dot=0;
                    foreach ($mydb->get_recordset_sql("select * from tmp.buffer where lid>:topid "
                                                      ,array('topid'=>$topid)) as $r)
                    {
                        $DB->execute("INSERT INTO {block_reportsdash_usecron} (`time`,`hr`,`userid`,`lid`)
                                                                        VALUES(:time,:hr,:userid,:lid)",
                                     array('time'=>$r->time,
                                           'hr'=>$r->hr,
                                           'userid'=>$r->userid,
                                           'lid'=>$r->lid));

                        $dot++;
                        
                        if($dot%100===0)
                        {
                            mtrace('.','');
                            if($dot%2000===0)
                            {
                                mtrace(" $dot");
                            }
                        }
                    }

                    //For email, get only yesterday's data,
                    //even if some days have been skipped for some reason

                    $sql="SELECT hr, count(distinct uc.userid) as logins,
                           count(*) as pages,
                           count(distinct courseid) as courses
                        FROM {$pfx}block_reportsdash_usecron uc
                        JOIN {$pfx}{$tablename} l on l.id=uc.lid
                        WHERE uc.time>=:from and uc.time<=:end GROUP by hr WITH ROLLUP";

                    $yesterday=(floor($run/86400)-1);

                    $data=$DB->get_records_sql($sql,array('from'=>$yesterday,
                                                          'end'=>$yesterday));
                } else {
                    mtrace('Skipping as logstore standard is disabled');
                }
            } else {
                mtrace('Skipping as can not find any internal log readers');
            }


            if($data)
            {
                $message=get_string('yesterdayslogins',$L,
                                    userdate($yesterday*86400,
                                             get_string('strftimedaydate','langconfig')));


                $message.="\r\n";

                $message.="Time\tLogins\tPages\tCourses\r\n";

                $total=array_pop($data);

                foreach(range(0,23) as $hr)
                {
                    $message.=sprintf("%02d:00-%02d:59\t",$hr,$hr);
                    if(isset($data[$hr]))
                    {
                        $message.=$data[$hr]->logins."\t".$data[$hr]->pages."\t".$data[$hr]->courses;
                    }
                    else
                    {
                        $message.="0\t0\t0";
                    }
                    $message.="\r\n";
                }
                $message.=get_string('total').": $total->logins\t$total->pages\t$total->courses\r\n";

            }
            else
            {
                $message=get_string('nologinsyesterday',$L,
                                    userdate($yesterday*86400,
                                             get_string('strftimedaydate','langconfig')));
            }

            $flag=true;

            if(!empty($CFG->siteadmins))
            {
                mtrace('About to send message');

                foreach(explode(',',$CFG->siteadmins) as $id)
                {
                    try
                    {
                        block_reportsdash::quickmail(2,$id,get_string('distinctloginsreport',$L),$message);
                    }
                    catch(Exception $e){
                        mtrace("\n$message\nFailed to send to user $id");
                    }
                }

                mtrace('Sent message');
            }
            else
            {
                mtrace('No site admins defined.');
            }
        }
        else
        {
            mtrace('--Skipping due to time/date');
        }

        mtrace('Finished usage report cron');

        if($flag)
            set_config('last_usage_cron',$run,$B);

        return true;

    }

    //Construct the single table needed for this report.
    static function install()
    {
        global $DB;

        $dbman = $DB->get_manager();

        // Define field id to be added to block_reportsdash_usecron
        $table = new xmldb_table('block_reportsdash_usecron');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null);
        $table->add_field('time', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'id');
        $table->add_field('hr', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'time');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'hr');
        $table->add_field('lid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, 'userid');
        $table->add_key('primidx', XMLDB_KEY_PRIMARY, array('id'));

        $table->add_index('timeidx', XMLDB_INDEX_NOTUNIQUE, array('time'));
        $table->add_index('hridx', XMLDB_INDEX_NOTUNIQUE, array('hr'));
        $table->add_index('timeuseridx', XMLDB_INDEX_NOTUNIQUE, array('time','userid'));
        $table->add_index('lididx', XMLDB_INDEX_UNIQUE, array('lid'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        return true;
    }

    static function uninstall()
    {
        global $DB;

        parent::uninstall();
        $dbman = $DB->get_manager();

        // Define field id to be added to block_reportsdash_usecron
        $table = new xmldb_table('block_reportsdash_usecron');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        set_config('last_usage_cron',0,'block_reportsdash');

        return true;
    }

    //Although this report uses the heavy DB for its summary data
    //it reads the summary from the local DB
    //Thus, it has to declare itself heavy (and therefore hidden)
    //if the use of the heavy database is not active.
    static function heavy()
    {
        return !static::heavydb_active();
    }

    static function upgrade($oldversion)
    {
        if($oldversion===0)
        {
            return static::install();
        }
        else
        {
            self::uninstall();
            return static::install();
        }

        return true;
    }


    //////////////////////////////////// Instance

    protected $peak;
    protected $peakusers;

    function __construct()
    {
        parent::__construct(array('hr','pages','users'));
    }

    protected function setSql($usesort=true)
    {
        $f=$this->filters;

        if(!$f->filtersvalid)
        {
            (date('I',$f->fromfilter) and $f->fromfilter+=3600);
            (date('I',$f->tofilter) and $f->tofilter+=3600);
        }

        $f->filtersvalid=true;
        $from=min(floor($f->fromfilter/86400),floor($f->tofilter/86400));
        $to=max(floor($f->tofilter/86400),floor($f->fromfilter/86400));

        $this->sql="SELECT hr, count(*) as pages, count(distinct userid) users
                          FROM {block_reportsdash_usecron}
                          WHERE time >=$from and time <=$to
                          GROUP by hr WITH ROLLUP" ;
    }


    //Default to last four weeks
    protected function defaultFilters()
    {
        $filters=parent::defaultFilters();

        $filters->fromfilter=time()-60*60*24*7*4;

        return $filters;
    }

    function get_filter_form()
    {
        $this->filterform=new block_reportsdash_usage_filter_form(null, array('rptname'=>$this->reportname),
                                                                  '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    protected function prepareHtml()
    {
        parent::prepareHtml();
        $table=$this->table();
        $table->set_attribute('width','50%');
        $table->sortable(false);
        $table->pageable(false);
    }

    protected function getData($usesort=true)
    {
        //Using limits on this query increases the processing time by a factor of x20!
        //Since the query's group clause means that only a maximum of 24 results
        //can ever be obtained, there's no need for specific limits.
        parent::getData();

        $data=array();
        $this->records=0;

        if($this->data->valid())
        {

            $total=0;
            $this->peak=0;
            $this->peakusers=0;

            foreach(range(0,23) as $hour)
            {
                $data[$hour]=(object)array('hr'=>$hour,
                                           'pages'=>0,
                                           'users'=>0);
            }

            foreach($this->data as $hour=>$newdata)
            {
                if(!isset($newdata->hr))
                {
                    $data['total']=(object)array('hr'=>'total','pages'=>$newdata->pages,'users'=>$newdata->users);
                }
                else
                {
                    $data[$hour]=$newdata;
                    if($newdata->pages>$this->peak)
                    {
                        $this->peak=$newdata->pages;
                    }
                    if($newdata->users>$this->peakusers)
                    {
                        $this->peakusers=$newdata->users;
                    }
                }
            }

            $this->records=25;
        }
        $this->data=$data;
        return $data;
    }

    protected function setColumnStyles()
    {
        parent::setColumnStyles();

        $this->table->column_style('hr','text-align','left');
    }

    protected function preprocessExport($row)
    {
        return $this->preprocessShow($row,true);
    }

    protected function preprocessShow($row,$exporting=false)
    {
        global $USER;

        $tz=date_default_timezone_get();

        date_default_timezone_set('UTC');

        $n=usertime($row->hr*3600)+3600;
        $n1=usertime($row->hr*3600+3599)+3600;
        if($row->hr!=='total') //PHP quirk: must remember that extra =
        {
            if(!$exporting)
            {
                if($row->pages==$this->peak)
                    $row->pages="<span class='peaktime'>$row->pages</span>";

                if($row->users==$this->peakusers)
                    $row->users="<span class='peaktime'>$row->users</span>";
            }

            $row->hr=date('H:i - ',$n).date('H:i',$n1);
        }
        else
        {
            $row->hr=get_string('total');
        }

        date_default_timezone_set($tz);

        return $row;
    }
    static function get_report_category(){
        return 'sitestatistics';
    }

   protected function reportGraph($dataStart = 0, $dataEnd = 0)
   {
        $dataStart  =   0;
        $dataEnd    =   $this->records;
        $this->chart    =   new block_reportdash_graph_line();

        $users=$pages = array();
        $labels = array();
        for ($i = 0; $i < 24; $i++)
        {
            $users[] = $this->data[$i]->users;
            $pages[] = $this->data[$i]->pages;
            $labels[] = sprintf("%02d:00\n", $i);
        }

        $this->chart->chartData->addPoints($pages,get_string('pages','block_reportsdash'));
        $this->chart->chartData->addPoints($users,get_string('users'));
        $this->chart->chartData->addPoints($labels,'xaxis');
        $this->chart->chartData->setSerieOnAxis(get_string('pages','block_reportsdash'),0);
        $this->chart->chartData->setSerieOnAxis(get_string('users'),1);
        $this->chart->chartData->setAxisPosition(0,AXIS_POSITION_RIGHT);
        $this->chart->chartData->setAbscissa('xaxis');

        //Can't get this to rotate properly
        //$this->chart->chartData->setAxisName(0,get_string('pages','block_reportsdash'));
        //$this->chart->chartData->setAxisName(1,get_string('users'));

        $width = 60 * 24;
        $this->chart->setGraphPosition(150, 45, $width, 500);
        $this->chart->setLegend(20,20,false);
        $this->chart->createGraph();
        $this->chart->displayGraph();
        $this->chart->createImageMap();
   }
}
