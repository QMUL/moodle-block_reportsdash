<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*  NOTE ON DATABASE USAGE.
 *  The static functions use $DB as normal
 *  But instance methods MUST use $this->mydb
 *  This allows the report to be classified as
 *  light or heavy without changing anything else
 */

require_once("$CFG->libdir/tablelib.php");
require_once("$CFG->libdir/externallib.php");

//Inherit from external_api to allow webservices
class block_reportsdash_report extends external_api
{

    // Class/static methods

    const DEFAULT_PAGE_SIZE=20;

    //A string representing a setting in the block config which is used
    //to store the version id for the tables used by this report.

    //That is used in turn by checkInstall.

    //If the report has no custom tables, this should be left as an empty string.
    const SETTING_NAME='';

    static protected $staffRoles=null;

    static protected $heavy_ok=false;

    static protected $dependencies = array();

    protected $chart=false;

    //The static (class) functions are aimed at finding out or doing stuff without having to set
    //up an instance, which has a considerable overhead

    static function description()
    {
        return get_string(get_called_class().'_description','block_reportsdash');
    }

    //Find staff roles, with caching
    static function staff_roles()
    {
        if(isset(static::$staffRoles))
        {
            return static::$staffRoles;
        }

        global $DB;

        $t=implode(',',$DB->get_fieldset_select('block_reportsdash_staff','roleid'));

        if(!empty($t))
        {
            return static::$staffRoles="(0,$t)";
        }
        else
        {
            return static::$staffRoles='(0)';
        }

    }

    //Called by any function that needs to access a report's specific DB tables
    //If the given setting does not contain the same version number as the
    //report's version() fn, then either the install or upgrade fn is called
    //and the setting saved.
    //
    // $setting should be the name of a config setting which will hold
    // the version number of the report's database tables
    //
    //There is no obvious reason why a sub class should need to override this.
    //Subclasses should only need to implement upgrade() and install()
    static protected function checkInstall()
    {
        if(!$setting=static::SETTING_NAME)
        {
            return true;
        }

        @($existing=get_config('block_reportsdash',$setting)
          or $existing=0);

        if($existing==static::version())
            return true;

        if($existing<static::version())
        {
            static::upgrade($existing);
        }
        elseif($existing>static::version())
        {
            print_error("Usage trying to downgrade $setting!");
            exit;
        }
        else
        {
            static::install();
        }

        set_config($setting,static::version(),'block_reportsdash');
    }

    //Does not check that we should be using heavyDB, assumes caller knows
    static protected function getHeavyDB()
    {
        global $CFG,$DB;
        require_once("$CFG->libdir/dmllib.php");

        if(!static::heavydb_active())
            return $DB;

        $mytype=$CFG->heavydbtype;
        $mylibrary=$CFG->heavydblibrary;

        if (empty($mydb = moodle_database::get_driver_instance($mytype, $mylibrary)))
        {
            throw new dml_exception('dbdriverproblem', "Unknown driver $mylibrary/$mytype");
        }

        $mydb->connect($CFG->heavydbhost, $CFG->heavydbuser, $CFG->heavydbpass,
                       $CFG->heavydbname, $CFG->prefix, $CFG->dboptions);


        return $mydb;
    }

    static function heavydb_active()
    {
        return (!empty(get_config('block_reportsdash','useheavyhost')) and static::heavy_config());
    }

    //Export the results
    static function webservice()
    {
        //Don't know at static class level how many arguments have been passed
        //so we have to work it out.
        $args=func_get_args();

        $params=array();

        //Make sure that they are in the same order as defined in the fields function
        $fn=0;
        foreach(static::fields()->inputs as $field)
        {
            if(!isset($args[$fn]))
                $args[$fn]=$field->value();
            $params[$field->name()]=$args[$fn];

            //Fake POST in case param is required in report constructor below;
            $_POST[$field->name()]=$args[$fn++];
        }

        $inst=new static();  //new self() does not create the actual class being called!

        //Set the internal filters
        foreach($params as $name=>$value)
        {
            $inst->filters->$name=$value;
        }

        //Then validate them via webservices' function
        $params=static::validate_parameters(static::webservice_parameters(),$params);

        $tab=$inst->table;
        $tab->setup();

        $data=array();

        //Only return the data specifically asked for.
        $outs=array_merge(static::fields()->outputs,static::fields()->webservice);

        foreach($inst->getData(false) as $item)
        {
            $item=$inst->preprocessWebservice($item);
            if($item!==null) //May be erased by preprocessWebservice
            {
                $composite=array();

                foreach($outs as $field)
                {
                    if(!$field->suppress())
                    {
                        $name=$field->name();
                        //Manually clean the data here because Moodlelib validation breaks on
                        //floats with trailing zeroes (including 0.0).
                        $composite[$name]=clean_param($item->$name,$field->type());
                    }
                }

                $data[]=(array)$composite;
            }
        }

        return $data;
    }

    //report what we return
    static function webservice_returns()
    {
        $params=array();
        $fields=static::fields();
        if(isset($fields->outputs))
        {
            foreach($fields->outputs as $f)
            {
                //Output field is marked as not for webservice
                if(!$f->suppress())
                {
                    $params[$f->name()]=new external_value($f->type(),$f->description(0));
                }
            }
        }

        //Fields that are normally not shown on the webpage of the report
        if(isset($fields->webservice))
        {
            foreach($fields->webservice as $f)
            {
                //Makes no sense to suppress here, but maybe you're testing and not just an idiot
                if(!$f->suppress())
                {
                    $params[$f->name()]=new external_value($f->type(),$f->description(0));
                }
            }
        }

        return new external_multiple_structure(new external_single_structure($params));
    }

    //Report what parameters we expect
    static function webservice_parameters()
    {
        $params=array();
        $fields=static::fields();
        if(isset($fields->inputs))
        {
            foreach($fields->inputs as $field)
            {

                //We use VALUE_DEFAULT here rather than VALUE_OPTIONAL because, ha ha,
                //VALUE_OPTIONAL means that the parameter is skipped completely, making
                //it impossible to know which parameters have actually been passed
                //without being psychic.
                if($field->required())
                {
                    $params[$field->name()]=new external_value($field->type(),$field->description(),VALUE_REQUIRED);
                }
                else
                {
                    $params[$field->name()]=new external_value($field->type(),$field->description(),VALUE_DEFAULT,$field->value());
                }
            }
        }

        return new external_function_parameters($params);
    }

    //Extract column names from output fields data, not strictly a webservice thing
    protected static function column_names()
    {
        $r=array();
        foreach(static::fields()->outputs as $f)
        {
            $r[]=$f->name();
        }
        return $r;
    }

    ///// End of webservices stuff

    protected static function export_names()
    {
        $r=static::column_names();
        foreach(static::fields()->exports as $f)
        {
            $r[]=$f->name();
        }

        return $r;
    }

    protected static function fields()
    {
        return array();
    }

    static function has_cron()
    {
        return false;
    }

    // Return false if cron had an error.
    static function cron()
    {
        static::checkInstall();
        return true;
    }

    protected static function timeformat()
    {
        return get_string('strftimedatefullshort','langconfig');
    }

    //Should the report hand off to a remote DB server?
    static function heavy()
    {
        return false;
    }

    // Any special installation requirements
    static function install()
    {
        return true;
    }

    // Any special uninstall requirements
    static function uninstall()
    {
        return true;
    }

    // Only used by reports which create their own tables
    static function version()
    {
        return 0;
    }

    //Update handling
    static function upgrade($oldversion)
    {
        return true;
    }

    //Any special caps beyond the view cap.
    static function permissions()
    {
        return array();
    }

    //Mostly used for sub-reports that never appear on the dashboard
    static function nevershow()
    {
        return false;
    }

    static function reportname()
    {
        return substr(get_called_class(),18); //Skip over block_reportsdash_
    }

    static public function display_name()
    {
        return get_string(static::reportname(),'block_reportsdash');
    }

    static public function display_report_description(){

       return get_string(static::reportname().'_descr','block_reportsdash');

    }

    static public function display_graph_label(){
        return "<div class = 'graphlabel'>".get_string(static::reportname().'_graph_label','block_reportsdash')."</div>";
    }

    //Is config.php set up with the required heavy settings?
    static function heavy_config()
    {
        global $CFG;

        if(static::$heavy_ok)
            return true;

        foreach(array('heavydbhost','heavydbtype','heavydblibrary',
                      'heavydbuser','heavydbpass') as $itemname)
        {
            if(!isset($CFG->$itemname))
                return false;
        }

        static::$heavy_ok=true;

        return true;
    }

    static function can_view_report($uid)
    {
        global $DB;

        if(static::heavy() and (!get_config('block_reportsdash','useheavyhost') or !static::heavy_config()))
            return false;

        $pm = core_plugin_manager::instance();
        foreach (static::$dependencies as $plugin => $version)
        {
            $info = $pm->get_plugin_info($plugin);
            if (is_null($info))
            {
                return false;
            }
            if ($version != ANY_VERSION && $info->versiondisk < $version)
            {
                return false;
            }
            if (!$info->is_enabled())
            {
                return false;
            }
        }

        if(has_capability('block/reportsdash:configure_reportsdash',context_system::instance()))
            return true;

        $reportname=static::reportname();

        // TODO Make this a join
        $permissions=$DB->get_records('block_reportsdash_perms',
                                      array('canview'=>1,'report'=>$reportname),
                                      '',
                                      'roleid,report');

        $permissions=block_reportsdash::reindex2d($permissions,'report');

        $myroles = $DB->get_records_sql("select distinct roleid from {role_assignments} where userid=:uid",
                                        array('uid'=>$uid));


        foreach($myroles as $rid=>$dummy)
        {
            if(isset($permissions[$reportname][$rid]))
                return true;
        }

        return false;
    }


    //Converts an sql sort into the parameters for a PHP sort
    //(the sort is external to the sql query, in case you're wondering)
    //$fast indicates that sorting is only to be done on one dimension
    static function external_sort(&$data,$sort,$fast=false)
    {
        $sarray=array();
        $sorting=explode(', ',$sort);
        $bits=explode(' ',$sorting[0]);

        $sarray[$bits[0]]=constant("SORT_$bits[1]");

        if(isset($sorting[1]) and !$fast)
        {
            $bits=explode(' ',$sorting[1]);
            $sarray[$bits[0]]=constant("SORT_$bits[1]");
        }

        block_reportsdash::sort_objects($data,$sarray);
    }

    static function make_filter(&$mform,$DB)
    {
        global $PAGE;

         $options = array('0' => 'All');

        $regions = block_reportsdash::get_regions_category_list();
        $allcats=block_reportsdash::reindex2d($DB->get_recordset('course_categories',array('visible'=>1)),'parent');

        $prevreg = 0;

        $select = $mform->addElement('select', 'levelfilter', get_string('levelfilter', 'block_reportsdash'), $options, array('class'=>'levelfilter'));

        foreach ($regions as $reg) {
            //             print_object($reg);
            if ($reg->rid != $prevreg) {
                $prevreg = $reg->rid;
                //$options[-$reg->rid]=$reg->regionname;
                $select->addOption($reg->regionname, -$reg->rid, array('class' => 'bold'));
            }
            if($reg->id)
            {
                //$options[$reg->id]=str_repeat('-',$reg->depth).$reg->name;
                $optname = str_repeat('-', $reg->depth) . $reg->name;
                $select->addOption($optname, $reg->id, array());
                if(isset($allcats[$reg->id]))
                {
                    foreach($allcats[$reg->id] as $sub)
                    {
                        $optname = str_repeat('-', $sub->depth+1) . $sub->name;
                        $select->addOption($optname, $sub->id, array());
                    }
                }
            }
        }

        $mform->addElement("html","<script type='text/javascript'>$(document).ready(function() {
            $('#id_levelfilter').select2({
            placeholder: 'Select an option',
            allowClear: true
            });
         });</script>");

    }


    //A general DB query with one or more straight joins. The on clause is
    //defined by the index of the second and later tables.
    //eg: $this->get_join_set(array('block_reportsdash_regcats rc',
    //                               course_categories cc'='cc.id=rc.cid'));
    // Just a copy of the code in block_reportsdash but uses mydb.
    static function get_join_set($DB,$tables,$where='1',$fields='*',$orderby='')
    {
        global $CFG;
        $sql='';
        $pfx=$CFG->prefix;

        foreach($tables as $table=>$join)
        {
            if(empty($sql))
            {
                $sql="select $fields from {$pfx}$table ";
            }
            else
            {
                $sql.="JOIN {$pfx}$table on $join\n";
            }
        }

        $sql.=" WHERE $where\n";
        if($orderby)
            $sql.="ORDER BY $orderby";

        return $DB->get_recordset_sql($sql);
    }

    //As above but result is an array, and optionally indexed by a particular field $idx
    static function get_join($DB,$tables,$idx='',$where='1',$fields='*',$orderby='')
    {
        if($idx)
        {
            return block_reportsdash::reindex(static::get_join_set($DB,$tables,$where,$fields,$orderby),$idx);
        }
        return block_reportsdash::rstoarray(static::get_join_set($DB,$tables,$where,$fields,$orderby));
    }


    static function full_path($DB,$courseid,$makelinks=true)
    {
        global $CFG;
        $course=$DB->get_record('course',array('id'=>$courseid));
        $parent=$DB->get_record('course_categories',array('id'=>$course->category));

        if (!$parent) { // Root category
            return '';
        }

        $bits=explode('/',substr($parent->path,1));

        $links=array();
        foreach($bits as $catid)
        {
            $cat=$DB->get_record('course_categories',array('id'=>$catid));
            $l=$cat->name;
            if($makelinks)
                block_reportsdash::wrap($l,"$CFG->wwwroot/course/category.php?id=$catid");
            $links[]=$l;
        }

        return implode('->',$links);
    }


    //Instance

    protected $sql;
    protected $params;
    protected $filters;
    protected $records;
    protected $data;
    protected $sort;
    protected $table;
    protected $dynamiccols;
    protected $reportname;
    protected $translator;
    protected $filterform;
    protected $configform;
    protected $baseurl;
    protected $mydb;
    protected $memcache;

    function __construct($columns=array(),$dynamiccols=false,$langfiles='block_reportsdash')
    {
        global $SESSION,$DB,$USER;

        if(!$this->can_view_report($USER->id))
        {
            print_error('notallowedtoview','block_reportsdash','',$this->display_name());
        }

        $this->param=array();
        $this->sql='';
        $this->sort='';
        $this->records=0;
        $this->data=null;

        $this->reportname=static::reportname();

        $this->translator=new String_Container($columns,$langfiles);

        $this->table=new flexible_table($this->reportname);

        if(optional_param('clearfilters',0,PARAM_INT))
        {
            $SESSION->reportsdashfilters[static::reportname()]
                =$this->filters
                =$SESSION->flextable[static::reportname()]
                =null;
        }

        $this->baseurl=new moodle_url("/blocks/reportsdash/report.php");

        $this->updateBaseUrl('rptname',$this->reportname);

        $this->dynamiccols=$dynamiccols;

        if(!$dynamiccols)
        {
            $this->setColumns($columns);
        }

        $this->filters=$this->checkFilters();

        if(static::heavy())
        {
            $this->setupheavyDB();
        }
        else
        {
            $this->mydb=$DB;
        }

        $this->memcache=false;

        if(class_exists('Memcache',false))
        {
            $memcache=new Memcache;

            if($memcache->connect('localhost', 11211))
            {
                $this->memcache=$memcache;
            }
        }
    }

    protected function setupheavyDB()
    {
        global $CFG;
        require_once("$CFG->libdir/dmllib.php");

        try {
            $mydb=static::getHeavyDB();
        } catch (moodle_exception $e) {
            if (empty($CFG->noemailever) and !empty($CFG->emailconnectionerrorsto)) {
                if (file_exists($CFG->dataroot.'/emailcount')){
                    $fp = @fopen($CFG->dataroot.'/emailcount', 'r');
                    $content = @fread($fp, 24);
                    @fclose($fp);
                    if((time() - (int)$content) > 600){
                        //email directly rather than using messaging
                        @mail($CFG->emailconnectionerrorsto,
                              'WARNING: Database connection error: '.$CFG->wwwroot,
                              'Connection error: '.$CFG->wwwroot);
                        $fp = @fopen($CFG->dataroot.'/emailcount', 'w');
                        @fwrite($fp, time());
                    }
                } else {
                    //email directly rather than using messaging
                    @mail($CFG->emailconnectionerrorsto,
                          'WARNING: Database connection error: '.$CFG->wwwroot,
                          'Connection error: '.$CFG->wwwroot);
                    $fp = @fopen($CFG->dataroot.'/emailcount', 'w');
                    @fwrite($fp, time());
                }
            }
            // rethrow the exception
            throw $e;
        }

        $this->mydb=$mydb;
    }

    protected function table()
    {
        return $this->table;
    }

    //Mainly for sub-reports to embed required params into the sorting links
    function updateBaseUrl($param,$value)
    {
        $this->baseurl->param($param,$value);
        $this->table->define_baseurl($this->baseurl->out());
    }

    function __destruct()
    {
        global $SESSION;
        $SESSION->reportsdashfilters[$this->reportname]=$this->filters;
    }

    //Try to get the session filters; otherwise get the default ones
    protected function checkFilters()
    {
        global $SESSION;

        if(@$f=$SESSION->reportsdashfilters[$this->reportname])
        {
            return (object)$f;
        }

        return $this->defaultFilters();
    }

    protected function defaultFilters()
    {
        $now=time();
        $filters=new stdClass;
        $filters->tofilter=$now;
        $filters->fromfilter=$now-60*60*24*365;
        $filters->levelfilter=0;
        return $filters;
    }

    function setFilter($name,$value)
    {
        global $SESSION;
        $SESSION->reportsdashfilters[$this->reportname]->$name=$value;
    }

    function setColumns($columns, $headers = false)
    {
        $this->columns=$columns;
        $this->table->define_columns($this->columns);
        if ($headers)
        {
            $this->table->define_headers($headers);
        }
        else
        {
            $this->table->define_headers($this->translator->translate(array_keys($this->table->columns)));
        }

        $this->setColumnStyles();
    }

    protected function translate($stuff,$dropin='')
    {
        return $this->translator->translate($stuff,$dropin);
    }

    protected function preprocessExport($rowdata)
    {
        return $rowdata;
    }

    protected function preprocessShow($rowdata)
    {
        return $rowdata;
    }

    protected function preprocessWebservice($rowdata)
    {
        return $rowdata;
    }

    protected function getData($usesort=true)
    {
        global $DB;

        static::checkInstall();

        $this->setSql($usesort);
        $this->data=$this->mydb->get_recordset_sql($this->sql,$this->params);

        //The reason for this next bit is to use recordset instead of records, as the
        //number of possible results is quite high. Unfortunately, the Moodle team
        //have taken it into their heads to hide the number of results in a
        //recordset, so we have to break the object open with a hammer to get to
        //the number we need:
        if($this->data->valid())
        {
            $jim=(array)$this->data;
            $this->records=array_shift($jim)->num_rows;
        }
        else
        {
            $this->records=0;
        }
        return $this->data;
    }

    //This HAS to be defined in actual reports
    //But we can't declate THIS class as abstract because
    //then "new self()" in the webservices code above doen't
    //work, because PHP's parser is idiosyncratic that way.
    //***************************************************

    protected function setSql($usesort=true)
    {
    }

    //***************************************************

    protected function sort()
    {
        return $this->table->get_sql_sort();
    }

    protected function exportname()
    {
        return $this->reportname.'-'.userdate(time(),get_string('strftimedaydate','langconfig'));
    }

    function export($export='report',$usesort=true)
    {
        $table=$this->table;

        $flag=static::fields(); //empty() doesn't work directly on this test

        if(!$this->dynamiccols and !empty($flag))
        {
            $columns=static::export_names();

            $table->define_columns($columns);
        }

        $table->setup();

        $this->getData();

        $columns=array_keys($table->columns);

//        $ex="table_{$export}_export_format";
//
//        $ex=new $ex($table);

        $ex = new table_dataformat_export_format($table, $export);

        $ex->start_document($this->exportname());

        $ex->start_table('Sheet1');

        $this->reportHeader($ex);

        if(!empty($this->table->headers))
        {
            $ex->output_headers($this->table->headers);
        }
        else
        {
            $ex->output_headers($this->translator->ucwords($columns,!$this->dynamiccols));
        }

        foreach($this->data as $row)
        {
            $ex->add_data($table->get_row_from_keyed($this->preprocessExport($row)));
        }
        
        $ex->table->started_output=(!empty($this->data));
        $ex->finish_table();

        $this->reportFooter($ex);

        // Trigger an event for report_exported
        $params = array('userid' => $USER->id,
                        'context' => context_system::instance(),
                        'other'=>array('report'=>$this->reportname)
        );
      
        $event = \block_reportsdash\event\report_exported::create($params);
        $event->trigger();

        $ex->finish_document();

        exit;

    }

    protected function prepareHtml()
    {
        $table=$this->table;

        $table->sortable(true);
        $table->pageable(true);
        $table->is_downloadable(true);
        $table->set_attribute('width','100%');
        $table->set_attribute('style','margin-top:2ex');
    }

    protected function readFilters()
    {
        global $SESSION;

        $filterform=$this->get_filter_form();
        $filterform->set_data($this->filters);
        //$filterform->validate_defined_fields();

        $filterform->display();

        if($filters=$filterform->get_data())
        {
            if(@$filters->timetoggle)
            {
                global $DB;

                $startterm=$filters->termstart[1];
                $endterm=$filters->termend[1];

                if($startterm==YEAR_START)
                {
                    $filters->fromfilter=$DB->get_field('block_reportsdash_years','yearstart',array('id'=>$filters->termstart[0]));
                }
                elseif($startterm==YEAR_END)
                {
                    $filters->fromfilter=$DB->get_field('block_reportsdash_years','yearend',array('id'=>$filters->termstart[0]));
                }
                else
                {
                    $filters->fromfilter=$DB->get_field('block_reportsdash_terms','termstart',array('id'=>$startterm));
                }

                if($endterm==YEAR_START)
                {
                    $filters->tofilter=$DB->get_field('block_reportsdash_years','yearstart',array('id'=>$filters->termend[0]));
                }
                elseif($endterm==YEAR_END)
                {
                    $filters->tofilter=$DB->get_field('block_reportsdash_years','yearend',array('id'=>$filters->termend[0]));
                }
                else
                {
                    $filters->tofilter=$DB->get_field('block_reportsdash_terms','termend',array('id'=>$endterm));
                }
            }
            elseif(isset($filters->tofilter))
            {
                $filters->tofilter=$filters->tofilter+86399;
            }

            $SESSION->reportsdashfilters[$this->reportname]=$filters;
            return $this->filters=$filters;
        }
    }

    function show()
    {
        global $USER;

        $this->readFilters();

        $this->prepareHtml();

        $table=$this->table;

        $table->setup();

        $data = $this->getData();

        $this->reportHeader();

        $table->pagesize(constant(get_called_class().'::DEFAULT_PAGE_SIZE'),$this->records);

        $start=$table->get_page_start();
        $end=min($start+$table->get_page_size(),$start+$this->records);



        if ((is_object($data) && $data->valid()) || (is_array($data) && !empty($data))) {
            $this->reportGraph($start, $end);
        }

        //we have to get data again as recordsets in moodle_database do not allow data seeking
        $this->getData();



        $i=0;
        foreach($this->data as $item)
        {
            if($i++<$start)
            {
                continue;
            }

            $table->add_data_keyed($this->preprocessShow($item));

            if($i==$end)
                break;
        }
        $table->finish_html();

        $this->reportFooter();

        // Trigger an event for report_exported
        $params = array('userid' => $USER->id,
                        'context' => context_system::instance(),
                        'other'=>array('report'=>$this->reportname)
        );
      
        $event = \block_reportsdash\event\report_viewed::create($params);
        $event->trigger();

    }


    //$export is either null/false or an export handle for writing to.
    protected function reportHeader($export=false)
    {
        return;
    }

    protected function reportFooter($export=false)
    {
        return;
    }

    protected function reportGraph($dataStart=0,$dataEnd=0)    {
        return;
    }

    protected function setColumnStyles()
    {
        $this->table->column_style_all('padding-left','12px');
        $this->table->column_style_all('text-align','right');
    }

    function get_filter_form()
    {
        $this->filterform=new block_reportsdash_report_filter_form(null, array('rptname'=>$this->reportname(),
                                                                               'db'=>$this->mydb,
                                                                               'singlemode'=>optional_param('singlemode',0,PARAM_INT)),
                                                                   '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    //Read-only access, mainly for filter forms
    function current_filters()
    {
        return $this->filters;
    }

    function get_config_form()
    {
        $this->configform=null;
        return $this->configform;
    }

   static function get_report_category(){
        return '';
    }
}
