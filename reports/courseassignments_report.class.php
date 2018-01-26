<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_courseassignments_report extends block_reportsdash_report
{
   static function nevershow()
   {
      return true;  ///Sub-report of departmental report
   }

   static function ex_services()
   {
      return array('reportsdash_course_assignments'=>'Returns information on assignment marking on a course');
   }

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
   protected static function fields()
   {
      $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
      return (object)array('outputs'=>array(new Reportdash_field_info('name',PARAM_TEXT,"Course name"),
                                            new Reportdash_field_info('timedue',PARAM_INT,'Deadline for assignment'),
                                            new Reportdash_field_info('submissionraw',PARAM_INT,'Number of submissions made'),
                                            new Reportdash_field_info('submissionper',PARAM_INT,'Number of submissions as fraction of expected'),
                                            new Reportdash_field_info('markedraw',PARAM_INT,'Number of submissions marked'),
                                            new Reportdash_field_info('markedper',PARAM_INT,'Number of submissions marked as fraction of submitted'),
                                            new Reportdash_field_info('ontimeraw',PARAM_INT,'Number of submissions marked on time'),
                                            new Reportdash_field_info('ontimeper',PARAM_INT,'Number of submissions marked on time as fraction of total marked'),
                                            new Reportdash_field_info('lateraw',PARAM_INT,'Number of submissions which were marked late'),
                                            new Reportdash_field_info('lateper',PARAM_INT,'Number of late submissions as fraction of total'),
                                            new Reportdash_field_info('passedraw',PARAM_INT,'Number of marked submissions which are passes '),
                                            new Reportdash_field_info('passedper',PARAM_INT,'Number of passes as fraction of total marked')),

                           'webservice'=>array(new Reportdash_field_info('course',PARAM_INT,"Course's Moodle id"),
                                               new Reportdash_field_info('aid',PARAM_INT,'Assignment id'),
                                               new Reportdash_field_info('type',PARAM_TEXT,"Moodle mod type for this assignment")),

                           'inputs'=>array(new Reportdash_field_info('fromfilter',PARAM_INT,'Unix timestamp: report on assignments due after this time',$defaulttime),
                                           new Reportdash_field_info('courseid',PARAM_INT,'Course id'),
                                           new Reportdash_field_info('markingwindow',PARAM_INT,"Number of seconds after due date that marking becomes 'late'. Default is one week",WEEKSECS)));
   }

//Instance

   protected $submissionssql;
   protected $userssql;
   protected $assignmentssql;
   protected $users;
   protected $course;
   protected $activetypes;
   protected $leader;
   protected $canseeassignment;
   protected $markingwindow;

   function __construct()
   {
      global $USER;
      parent::__construct(static::column_names(),false);

      $this->course=required_param('courseid',PARAM_INT);

      $this->markingwindow=optional_param('markingwindow',86400*7,PARAM_INT);

      $this->updateBaseUrl('courseid',$this->course);
      $this->updateBaseUrl('markingwindow',$this->markingwindow);

      $this->users=array();

      $modules=$this->mydb->get_records('modules',array(),'','name,id');

//The list of assignment-types which are understood by this report
      foreach(array('assign','assignment','turnitintool') as $modtype)
      {
         if(isset($modules[$modtype]))
         {
            $this->activetypes[$modtype]=$modules[$modtype]->id;
         }
      }

      $contextid=$this->mydb->get_field_sql("select id from {context} where contextlevel=:courselv and instanceid=:courseid",
                                    array('courselv'=>CONTEXT_COURSE,'courseid'=>$this->course));

      $leaderroles=get_config('block_reportsdash','leaderroles');

      if($leaderroles)
      {
//Make sure we only get one leader, and its the same leader as displayed on the department level report.
         $leaders=$this->mydb->get_recordset_sql("SELECT contextid, u.id as uid, u.firstname,u.lastname
                                                      FROM {role_assignments} ra
                                                      JOIN {user} u on (ra.userid=u.id and roleid = $leaderroles)
                                               WHERE contextid=$contextid");
         $leaders=block_reportsdash::reindex($leaders,'contextid');
         $this->leader=reset($leaders);
      }
      else
      {
         $this->leader=0;
      }

      $modules=$this->mydb->get_records('modules',array(),'','name,id');

//The list of assignment-types which are understood by this report
      foreach(array('assign','assignment','turnitintool') as $modtype)
      {
         if(isset($modules[$modtype]))
         {
            $this->activetypes[$modtype]=$modules[$modtype]->id;
         }
      }

      $this->canseeassignment=block_reportsdash_assignmentmarking_report::can_view_report($USER->id);

   }

   protected function setSql($usesort=true,$course=0)
   {
      global $CFG;

      $pfx=$CFG->prefix;

      $path=substr(str_replace('/',',',$this->mydb->get_field('context','path',array('contextlevel'=>CONTEXT_COURSE,'instanceid'=>$course))),1);

      $sql="select distinct u.id as uid
               from {$pfx}user u
               join {$pfx}role_assignments ra on u.id=ra.userid
               join {$pfx}context cx on (cx.id=ra.contextid)
               left join {$pfx}block_reportsdash_staff st on st.roleid=ra.roleid
            where st.id is null and cx.id in ($path)";

      $this->userssql=$sql;  //SQL for users
//Make an array of bits for the union
      $ucoursessql=$uassignmentssql=array();

      if(@$mid=$this->activetypes['assignment'])
      {
         $assignmentssql="select distinct ass.*
                                      FROM {$pfx}course_modules cm
                                      JOIN {$pfx}assignment ass on (ass.id=cm.instance and cm.module=$mid)
                                      JOIN {$pfx}course_sections cs on cs.id=cm.section
                                      WHERE cs.visible=1 and cm.visible=1 AND ass.course=:course ";

         $uassignmentssql['assignment']="($assignmentssql)";
      }

      if(@$mid=$this->activetypes['assign'])
      {
         $assignmentssql="select distinct ass.*, ass.duedate as timedue
                                      FROM {$pfx}course_modules cm
                                      JOIN {$pfx}assign ass on (ass.id=cm.instance and cm.module=$mid)
                                      JOIN {$pfx}course_sections cs on cs.id=cm.section
                                      WHERE cs.visible=1 and cm.visible=1 AND ass.course=:course ";

         $uassignmentssql['assign']="($assignmentssql)";
      }

      if(@$mid=$this->activetypes['turnitintool'])
      {
         $assignmentssql="select distinct ass.*, ass.defaultdtdue as timedue
                                      FROM {$pfx}course_modules cm
                                      JOIN {$pfx}turnitintool ass on (ass.id=cm.instance and cm.module=$mid)
                                      JOIN {$pfx}course_sections cs on cs.id=cm.section
                                      WHERE cs.visible=1 and cm.visible=1 AND ass.course=:course ";

         $uassignmentssql['turnitintool']="$assignmentssql";
      }
      $this->assignmentssql=$uassignmentssql;
   }

///Get all the submissions on a course.
   protected function submissionssql($course,$type)
   {
      global $CFG;

      $pfx=$CFG->prefix;

//Find all users on a course, their submitted assignments, grade given and required grade

      $userssql=$this->userssql;

      if($type==='assignment')
      {
//Pathological where clause: take the <>-10 out and the query returns the same
//result but takes 26 times longer. -10 is not allowed, so this is the same result
//as having no where clause

         return "select uid, u.firstname, u.lastname, ass.id as aid, ass.name, ass.assignmenttype, asub.*,
                      gi.*,gg.finalgrade, gg.timemodified as marktime
                 from ($userssql) students
                 join {$pfx}user u on u.id=students.uid
                 left join ({$pfx}assignment_submissions asub
                      join {$pfx}assignment ass on (ass.id=asub.assignment and ass.course = $course)
                      join {$pfx}grade_items gi on gi.iteminstance=ass.id and gi.itemmodule='assignment'
                 left join {$pfx}grade_grades gg on gg.itemid=gi.id and gg.userid=asub.userid) on asub.userid=students.uid
                 where asub.grade<>-10";
      }

      if($type==='assign')
      {
         return "select uid, u.firstname, u.lastname, ass.id as aid, ass.name, asub.*,
                      gi.*,gg.finalgrade, gg.timemodified as marktime
                 from ($userssql) students
                 join {$pfx}user u on u.id=students.uid
                 left join ({$pfx}assign_submission asub
                      join {$pfx}assign ass on (ass.id=asub.assignment and ass.course = $course)
                      join {$pfx}grade_items gi on gi.iteminstance=ass.id and gi.itemmodule='assign'
                 left join {$pfx}grade_grades gg on gg.itemid=gi.id and gg.userid=asub.userid) on asub.userid=students.uid";
      }

      if($type==='turnitintool')
      {
         return "select uid, u.firstname, u.lastname, ass.id as aid, ass.name, asub.*,
                      gi.*,gg.finalgrade, gg.timemodified as marktime
                 from ($userssql) students
                 join {$pfx}user u on u.id=students.uid
                 left join ({$pfx}turnitintool_submissions asub
                      join {$pfx}turnitintool ass on (ass.id=asub.turnitintoolid and ass.course = $course)
                      join {$pfx}grade_items gi on gi.iteminstance=ass.id and gi.itemmodule='turnitintool'
                 left join {$pfx}grade_grades gg on gg.itemid=gi.id and gg.userid=asub.userid) on asub.userid=students.uid
               where gg.rawgrade is not null ";
      }
   }

   protected function users()
   {
      if($this->users)
         return $this->users;

      return $this->users=$this->mydb->get_records_sql($this->userssql);
   }

//The data returned is the basis for the final data summary, not the actual values
   protected function getData($usesort=true)
   {
      global $CFG;

      static::checkInstall();

      $this->setSql(true,$this->course);

      if(isset($this->activetypes['turnitintool']))
         $turnitintoolsubmitted=get_string('submissionuploadsuccess','turnitintool'); // 8(

      $combineda=array();

      foreach($this->assignmentssql as $type=>$asql)
      {

         $assignments=$this->mydb->get_records_sql($asql,array('course'=>$this->course));
         $raw=$this->mydb->get_recordset_sql($this->submissionssql($this->course,$type));

         foreach($assignments as $a)
         {
            $a->submitted=$a->marked=$a->ontime=$a->late=$a->passed=0;
            $a->type=$type;
         }

         foreach($raw as $item)
         {
            $aid=$item->aid;
            if($type==='assignment' and (($item->assignmenttype=='upload' and $item->data2=='submitted')
                                         or (($item->assignmenttype==='uploadsingle'  and !empty($item->numfiles))
                                             or
                                             ($item->assignmenttype==='online' and !empty($item->timecreated))
                                             or
                                             ($item->assignmenttype==='offline' and !empty($item->timecreated))
                                            )
                  )
               or
               ($type==='assign' and $item->status==='submitted') or
               ($type==='turnitintool' and $item->submission_status===$turnitintoolsubmitted))
            {
               $assignments[$aid]->submitted++;
               if(!is_null($item->finalgrade))
               {
                  $assignments[$aid]->marked++;
                  if($assignments[$aid]->timedue and ($item->marktime-$assignments[$aid]->timedue)>$this->markingwindow)
                  {
                     $assignments[$aid]->late++;
                  }
                  else
                  {
                     $assignments[$aid]->ontime++;
                  }
                  if($item->finalgrade>$item->gradepass)
                  {
                     $assignments[$aid]->passed++;
                  }
               }
            }
         }
         $combineda=array_merge($combineda,$assignments);
      }
      $data=array();

      $pop=count($this->users());

      foreach($combineda as $a)
      {
         $item=new stdClass;

         $item->type=$a->type;
         $item->assignmenttype=$a->assignmenttype;
         $item->name=$a->name;
         $item->aid=$a->id;

         $item->timedue=$a->timedue;

         $item->submissionraw=$a->submitted;
         $item->submissionper=($pop)?$a->submitted/$pop:0;;

         $item->markedraw=$a->marked;
         $item->markedper=($a->submitted)?$a->marked/$a->submitted:0;

         $item->ontimeraw=$a->ontime;
         $item->ontimeper=($a->marked)?$a->ontime/$a->marked: 0;

         $item->lateraw=$a->late;
         $item->lateper=($a->marked)?$a->late/$a->marked : 0;

         $item->passedraw=$a->passed;
         $item->passedper=($a->marked)?$a->passed/$a->marked:0;

         $data[]=$item;
      }

      $this->records=count($data);

      if($this->sort())
      {
         block_reportsdash_report::external_sort($data,$this->sort());
      }

      return $this->data=$data;

   }

   protected function prepareHTML()
   {
      parent::prepareHTML();
      $this->table->sortable(true,'timedue',SORT_DESC);
   }

   protected function setColumnStyles()
   {
      parent::setColumnStyles();
      $this->table()->column_style_all('text-align','right');
      $this->table()->column_style('name','text-align','left');
   }

   protected function preprocessWebservice($rowdata)
   {
      $rowdata->course=$this->course;
      return $rowdata;
   }

   protected function preprocessExport($rowdata)
   {
      global $CFG;

      if($rowdata->timedue)
      {
           $rowdata->timedue=userdate($rowdata->timedue,static::timeformat());
      }
      else
      {
         $rowdata->timedue=get_string('open','block_reportsdash');
      }

      $rowdata->submissionper=format_float(100*$rowdata->submissionper,2).'%';
      $rowdata->markedper=format_float(100*$rowdata->markedper,2).'%';
      $rowdata->ontimeper=format_float(100*$rowdata->ontimeper,2).'%';
      $rowdata->lateper=format_float(100*$rowdata->lateper,2).'%';
      $rowdata->passedper=format_float(100*$rowdata->passedper,2).'%';

      return $rowdata;
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG,$DB;

      if($this->canseeassignment)
         block_reportsdash::wrap($rowdata->name,"$CFG->wwwroot/blocks/reportsdash/report.php?rptname=assignmentmarking_report&assignmenttype=$rowdata->type&assignmentid=$rowdata->aid&courseid=$this->course&clearfilters=1&markingwindow={$this->markingwindow}");

      $rowdata->submissionper=format_float(100*$rowdata->submissionper,2);
      $rowdata->markedper=format_float(100*$rowdata->markedper,2);
      $rowdata->ontimeper=format_float(100*$rowdata->ontimeper,2);
      $rowdata->lateper=format_float(100*$rowdata->lateper,2);
      $rowdata->passedper=format_float(100*$rowdata->passedper,2);

      if($rowdata->timedue)
      {
           $rowdata->timedue=userdate($rowdata->timedue,static::timeformat());
      }
      else
      {
         $rowdata->timedue=get_string('open','block_reportsdash');
      }


      return $rowdata;
   }

   protected function reportHeader($ex=0)
   {
      parent::reportHeader($ex);
      global $DB,$CFG;

      $ctitle=$this->mydb->get_field('course','fullname',array('id'=>$this->course));
      if($this->leader)
      {
         $leader=$this->leader->firstname.' '.$this->leader->lastname;
      }

      if(empty($ex))
      {
         global $OUTPUT;

         block_reportsdash::wrap($ctitle,"$CFG->wwwroot/course/view.php?id=$this->course");

         print $OUTPUT->heading($ctitle,2);
         if($this->leader)
         {
            block_reportsdash::wrap($leader,"$CFG->wwwroot/user/view.php?id={$this->leader->uid}");
            print $OUTPUT->heading($this->translate('courseleadershow',$leader),2);
         }

         print $OUTPUT->heading($this->translate('totalusers',count($this->users())),3);
      }
      else
      {
         $headers=array(static::display_name()." - $ctitle");
         if($this->leader)
            $headers[]=$this->translate('courseleadershow',$leader);
         $ex->output_headers($headers);
      }
   }

   function get_filter_form()
   {
      $this->filterform=new block_reportsdash_buttononly_filter_form(null, array('rptname'=>$this->reportname()),
                                                                                '','',array('id'=>'rptdashfilter'));
      return $this->filterform;
   }

}