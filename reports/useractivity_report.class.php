<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 

class block_reportsdash_useractivity_report extends block_reportsdash_report
{

   const DEFAULT_PAGE_SIZE=50;

   static function heavy()
   {
      return true;
   }

   protected static function timeformat()
   {
      return get_string('strftimedatetimeshort','langconfig');
   }

   static function nevershow()
   {
      return true;
   }

/// Instance
   protected $user;
   protected $modcache;
   protected $modidcache;
   protected $usercourses;

   function __construct()
   {
      parent::__construct(array('time','coursename','module','action','ip'));

      $this->user=null;
      $this->modcache=array();
      $this->usercourses=array();

   }

   protected function checkFilters()
   {
      global $SESSION;
      if(isset($SESSION->reportsdashfilters[$this->reportname()]))
      {
         $f=$SESSION->reportsdashfilters[$this->reportname()];

         //Check that saved session was for the same user.
         //DO NOT use !== here; the types are different!
         if($f->uid!=required_param('uid',PARAM_INT))
         {
            return $this->defaultFilters();
         }

         return $f;
      }
      return $this->defaultFilters();
   }

   public function defaultFilters()
   {
      global $SESSION,$DB;

      ($f=$SESSION->reportsdashfilters['login_report'] or $f=new stdClass);

      $f->uid=required_param('uid',PARAM_INT);

      $f->fromfilter=optional_param('timefrom',0,PARAM_INT);


      $SESSION->reportsdashfilters[$this->reportname()]=$f;
      return $f;
   }

   protected function setupCache()
   {
      $DB=$this->mydb;

      if(isset($this->modidcache))
         return true;

      foreach($DB->get_recordset('course_modules',array(),'','id,instance') as $id=>$object)
      {
         $this->modidcache[$id]=$object->instance;
      }

      return true;
   }

   protected function checkAccess($courseid)
   {
      global $USER;

      if(isset($this->usercourses[$courseid]))
      {
         return $this->usercourses[$courseid];
      }

      context_helper::preload_course($courseid);

      if (!$context = context_course::instance($courseid))
      {
         print_error('nocontext');
      }

      $users=get_users_by_capability($context,'moodle/course:view','u.id');

      $t=isset($users[$USER->id]);

      $this->usercourses[$courseid]=$t;

      return $t;
   }

   //Fetch the name of a module. Returns false if that module no longer exists
   //Caches results
   protected function getMod($module,$id)
   {
      $DB=$this->mydb;

      $this->setupCache();

      $module=strtolower($module);

      //Convert module instance id into module id
      if(!$mid=$this->modidcache[$id])  //Unknown module id
         return false;

      if($c=$this->modcache[$module])
      {
         if(isset($c[$mid]))
            return $c[$mid];
         return false;
      }

      foreach($DB->get_recordset($module,array(),'','id,name') as $id=>$object)
      {
         $this->modcache[$module][$id]=$object->name;
      }

      $tab=$this->modcache[$module];

      if(isset($tab[$mid]))
         return $tab[$mid];

      return false;
   }

   // Make sure user data is loaded.
   protected function needsUser() {
      $DB=$this->mydb;
      if(isset($this->user))
         return;

      $this->user = $DB->get_record('user', array('id' => required_param('uid', PARAM_INT)));

   }

   function setSql($usesort=true)
   {
      global $CFG;

      $this->needsUser();

      $pfx=$CFG->prefix;

      $uid=$this->user->id;

      $from=$this->filters->fromfilter;

      $this->updateBaseUrl('uid',$uid);

       $manager = get_log_manager();

       $selectreaders = $manager->get_readers('\core\log\sql_internal_reader');

       if ($selectreaders) {
           $reader = $selectreaders['logstore_standard'];

           if ($reader->is_logging()) {
               $tablename = $reader->get_internal_log_table_name();

               $this->sql = "select u.firstname, u.lastname, c.fullname coursename, c.id cid, l.* from
                        {$pfx}$tablename l join {$pfx}user u on u.id=l.userid LEFT JOIN
                        {$pfx}course c on c.id=l.courseid
                        where userid=$uid";

               if (isset($this->filters->onlynever)) //inherited from parent report
               {
                   $this->sql .= " and l.timecreated < $from";
               } else {
                   $this->sql .= " and l.timecreated >= $from";
               }

               if ($usesort and $this->sort()) {
                   $this->sql .= ' order by ' . $this->sort();
               } else {
                   $this->sql .= ' order by l.timecreated desc';
               }
           } else {
               // The {log} table is kept for storing the old logs only. New events are not written to it and must be taken from another log storage.
           }
       } else {
           // You are probably developing for 2.10 and table {log} does not exist any more. Or administrator uninstalled the plugin
       }
   }

   protected function setColumnStyles()
   {
      parent::setColumnStyles();
      $this->table()->column_style_all('text-align','left');
      $this->table()->column_style('time','text-align','right');
      $this->table()->column_style('ip','text-align','right');
   }

   protected function exportName()
   {
      return static::display_name()." ({$this->user->id})";
   }

   protected function preprocessExport($rowdata)
   {
      $rowdata->time=userdate($rowdata->timecreated,static::timeformat());

      if($rowdata->cid==SITEID)
      {
         $rowdata->coursename='';
         $rowdata->module='Site';
         $rowdata->coursename='Site';
      }

      $rowdata->action=ucwords($rowdata->action);

       if(!isset($rowdata->cid) ) //Deleted course
       {
           if ($rowdata->target == 'course'){
               $rowdata->coursename=get_string('deleted');
           } else {
               $rowdata->coursename='Unknown';
           }

       }

       if (!empty($rowdata->courseid)
           and $rowdata->action != 'Login'
           and $rowdata->action != 'Logout'
           and $rowdata->courseid!=SITEID) {
           if ($rowdata->objectid && $rowdata->target == 'course_modules') {

               $name = $this->getMod($rowdata->target, $rowdata->objectid);

               if ($name !== false) //false means deleted module
               {
                   $rowdata->module = $rowdata->target . ': ' . $name;
               } else {
                   $rowdata->module = $rowdata->target . ': ' . get_string('deleted');
               }
           }
       }
       $rowdata->module=$rowdata->target;


      return $rowdata;

   }

   protected function preprocessShow($rowdata) {
      global $CFG;

      $rowdata->time = userdate($rowdata->timecreated, static::timeformat());

      $rowdata->action = ucwords($rowdata->action);

      if(!isset($rowdata->cid) ) //Deleted course
      {
          if ($rowdata->target == 'course'){
              $rowdata->coursename=get_string('deleted');
          } else {
              $rowdata->coursename='Unknown';
          }

      }
      elseif($rowdata->courseid!=SITEID and $this->checkAccess($rowdata->cid) )
      {
         block_reportsdash::wrap($rowdata->coursename,"$CFG->wwwroot/course/view.php?id=$rowdata->courseid");
      }

      if (!empty($rowdata->courseid)
          and $rowdata->action != 'Login'
          and $rowdata->action != 'Logout'
          and $rowdata->courseid!=SITEID) {
         if ($rowdata->objectid && $rowdata->target=='course_modules') {

            $name=$this->getMod($rowdata->target,$rowdata->objectid);

            if($name!==false) //false means deleted module
            {
               block_reportsdash::wrap($rowdata->action, "$CFG->wwwroot/course/view.php?id=$rowdata->courseid");
               $rowdata->module=$rowdata->target.': '.$name;
            }
            else
            {
               $rowdata->module=$rowdata->target.': '.get_string('deleted');
            }
         } else if ($rowdata->action != get_string('deleted') && $rowdata->coursename != get_string('deleted')){
            block_reportsdash::wrap($rowdata->action, "$CFG->wwwroot/course/view.php?id=$rowdata->courseid");
         }
      }

      if ($rowdata->cid == SITEID) {
         $rowdata->coursename = '';
         $rowdata->target = 'Site';
         $rowdata->coursename = 'Site';
      }

      $rowdata->module=ucwords($rowdata->target);

      return $rowdata;
   }

   function get_filter_form() {
      global $SESSION;

      $this->needsUser();
      $this->filterform = new block_reportsdash_useractivity_filter_form(null,
                                                                         array('rptname' => $this->reportname,
                                                                               'uid' => $this->user->id,
                                                                               'onlynever' => isset($this->filters->onlynever)),
                                                                         '', '', array('id' => 'rptdashfilter'));
      return $this->filterform;
   }
}
