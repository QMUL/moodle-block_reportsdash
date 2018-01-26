<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_courseaccess_report extends block_reportsdash_report
{

   const DEFAULT_PAGE_SIZE=25;
   const CRON_PERIOD=82800;  //Dailyish

   const SETTING_NAME='courseusage_tables_version';

   static function services()
   {
      return array('reportsdash_course_accesses'=>'Numbers of users who have accessed courses in period between two timestamps');
   }

   protected static function fields()
   {
      $defaulttime=floor((time()-60*60*24*7)/86400)*86400;
      return (object)array('outputs'=>array(new Reportdash_field_info('course',PARAM_TEXT,"Course name"),
                                            new Reportdash_field_info('parent',PARAM_TEXT,"Name of course's parent category"),
                                            new Reportdash_field_info('students',PARAM_INT,'Number of students accessing course in period')),
                           'webservice'=>array(new Reportdash_field_info('cid',PARAM_TEXT,"Course id")),
                           'inputs'=>array(new Reportdash_field_info('fromfilter',PARAM_INT,'Unix timestamp, start of report period',$defaulttime),
                                           new Reportdash_field_info('tofilter',PARAM_INT,'Unix timestamp, end of report period',time())));
   }

   static function version()
   {
       return 2013031301;
   }

   static function has_cron()
   {
       return true;
   }

   static function cron()
   {

       if(!parent::cron())
           return false;

       $now=time();

       $B='block_reportsdash';

       @($lastcron=get_config($B,'last_course_usage_cron') or $lastcron=0);

       $flag=false;

       $hr=date('H',$now);

//Run once per day between 4 and 5am
       if($now-$lastcron > static::CRON_PERIOD and $hr=='4')
       {

           mtrace('Starting Course Usage Report cron');

           global $DB,$CFG;
           require_once("$CFG->libdir/dmllib.php");

           mtrace('Starting Update Report cron query');

           $pfx=$CFG->prefix;

            //use the log manager class
            $manager = get_log_manager();

           $selectreaders = $manager->get_readers('\core\log\sql_internal_reader');

           if ($selectreaders) {

               $reader     =   $selectreaders['logstore_standard'];

               if ($reader->is_logging())   {

                   $tablename  =   $reader->get_internal_log_table_name();

                   ///Mysql-only REPLACE. If we want to be standard and use INSERT then
                   ///we need to watch out for the effect of rounding the time down to days
                    ///as this can cause duplicates with INSERT, which causes problems
                    ///with the UNIQUE index on newtime,userid,course

                   $sql="REPLACE INTO {$pfx}block_reportsdash_courseuse (newtime,userid,course)
                         SELECT DISTINCT (floor(timecreated/86400)*86400) as newtime, userid, courseid
                                FROM {$pfx}{$tablename}
                                WHERE action='viewed' AND target='course'
                                AND component = 'core'
                                      AND timecreated>:lastcron";


//           mtrace("Executing: $sql ($lastcron)");

                   $flag=$DB->execute($sql,array('lastcron'=>$lastcron));

                   mtrace('Finished course usage report cron');


               } else {
                   mtrace('Skipping as logstore standard is disabled');
               }



           } else {
               mtrace('Skipping as can not find any internal log readers');
           }





       }
       else
       {
           mtrace('Skipping due to time');
       }

       if($flag)
            set_config('last_course_usage_cron',time(),$B);

       return true;
   }

   static function upgrade($oldversion)
   {
        if($oldversion===0)
        {
             return static::install();
        }

       return true;
   }

   static function install()
   {
        global $DB;
      // Define table block_reportsdash_courseuse to be created
        $table = new xmldb_table('block_reportsdash_courseuse');

        // Adding fields to table block_reportsdash_courseuse
        $table->add_field('id', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('newtime', XMLDB_TYPE_NUMBER, '20', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
        $table->add_field('course', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');

        // Adding keys/indexes to table block_reportsdash_courseuse
        $table->add_key('idx_id', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('idx_timeidcourse', XMLDB_KEY_UNIQUE, array('newtime', 'userid', 'course'));
        $table->add_key('idx_course', XMLDB_INDEX_NOTUNIQUE, array('newtime', 'userid', 'course'));

        $dbman = $DB->get_manager();

        // Conditionally launch create table for block_reportsdash_courseuse
        if (!$dbman->table_exists($table)) {
             mtrace('Creating table block_reportsdash_courseuse');
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
       $table = new xmldb_table('block_reportsdash_courseuse');

       if ($dbman->table_exists($table)) {
           $dbman->drop_table($table);
       }

       return true;
   }

//Instance

   function __construct()
   {
      parent::__construct(static::column_names(),false);
   }

   protected function setSql($usesort=true)
   {
       global $CFG;

       $pfx=$CFG->prefix;

       $f=$this->filters;

       $this->params['from']=min($f->fromfilter,$f->tofilter);
       $this->params['to']=max($f->tofilter,$f->fromfilter);

       $tlcsql="substring_index(substring_index(cc1.path, '/', 2),'/',-1)";

//Slightly strange structure to force MySQL to order the query efficiently
//The "obvious" order takes several minutes on a slow machine; this takes ~9s
       $sql="select c.id as cid, fullname as course, ifnull(t.students,0) students, cc1.name as parent,
                      cc1.id as parentid,  r.id as rid, r.visible as rvis, cc1.path,
                    $tlcsql tlcat
                    FROM {$pfx}course c
                    JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                    JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring_index(substring_index(cc1.path, '/', 2),'/',-1)
                    JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid and r.visible=1)

                    LEFT JOIN (SELECT count(distinct userid) students, course
                                      FROM {$pfx}block_reportsdash_courseuse
                                      WHERE newtime >=:from and newtime <=:to
                                      GROUP by course with rollup) t on t.course=c.id where c.visible=1 ";


       if(!empty($f->levelfilter)){
          if($f->levelfilter>0)
          {
             $filtercat=$this->mydb->get_record('course_categories',array('id'=>$f->levelfilter));
             if($filtercat->depth==1)
             {
                $sql.=" and $tlcsql=$f->levelfilter";
             }
             else
             {
                $bits=explode('/',$filtercat->path);
                $tlc=$bits[1];
                $sql.=" and $tlcsql=$tlc and (path like('%/$f->levelfilter/%') or path like('%/$f->levelfilter')) ";
             }
          }
          elseif($f->levelfilter<0)
          {
             $sql.=' and rid='.-$f->levelfilter;
          }
       }

       if($usesort and $this->sort())
       {
           $sql="$sql order by ".$this->sort();
       }
       $this->sql=$sql;
   }

   protected function setColumnStyles()
   {
      parent::setColumnStyles();
      $this->table->column_style('course','text-align','left');
      $this->table->column_style('parent','text-align','left');
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG;
      block_reportsdash::wrap($rowdata->course,"$CFG->wwwroot/course/view.php?id=$rowdata->cid");
      block_reportsdash::wrap($rowdata->parent,"$CFG->wwwroot/course/category.php?id=$rowdata->parentid");

      $tip=static::full_path($this->mydb,$rowdata->cid,false);
      $rowdata->course=html_writer::tag('span',$rowdata->course,array('title'=>$tip));
      return $rowdata;
   }

   function get_filter_form()
   {
      $this->filterform=new block_reportsdash_report_filter_form(null, array('rptname'=>$this->reportname(),
                                                                             'db'=>$this->mydb,
									     'notopencourses'=>true),
								 '','',array('id'=>'rptdashfilter'));
      return $this->filterform;
   }
    static function get_report_category(){
        return 'courseuse';
    }
}