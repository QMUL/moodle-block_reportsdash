<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
class block_reportsdash_assignmentmarking_report extends block_reportsdash_report
{
   static function nevershow()
   {
      return true;  ///Sub-sub-report of departmental report
   }

    //Suspended
   static function ex_services()
   {
      return array('reportsdash_assignment_marking'=>'Returns information on marking of a specific assignment');
   }

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
   protected static function fields()
   {
      return (object)array('outputs'=>array(new Reportdash_field_info('name',PARAM_TEXT,"Surname of marker",'',false,true),
                                            new Reportdash_field_info('timedue',PARAM_INT,'Deadline for assignment'),
                                            new Reportdash_field_info('submissionraw',PARAM_INT,'Number of submissions made'),
                                            new Reportdash_field_info('submissionper',PARAM_INT,'Number of submissions as fraction of expected','',false,true),
                                            new Reportdash_field_info('markedraw',PARAM_INT,'Number of submissions marked by this marker'),
                                            new Reportdash_field_info('markedper',PARAM_INT,'Number of submissions marked as fraction of submitted'),
                                            new Reportdash_field_info('ontimeraw',PARAM_INT,'Number of submissions marked on time'),
                                            new Reportdash_field_info('ontimeper',PARAM_INT,'Number of submissions marked on time as fraction of total marked'),
                                            new Reportdash_field_info('lateraw',PARAM_INT,'Number of submissions which were marked late'),
                                            new Reportdash_field_info('lateper',PARAM_INT,'Number of late submissions as fraction of total'),
                                            new Reportdash_field_info('passedraw',PARAM_INT,'Number of marked submissions which are passes '),
                                            new Reportdash_field_info('passedper',PARAM_INT,'Number of passes as fraction of total marked')),

                           'webservice'=>array(new Reportdash_field_info('students',PARAM_INT,'Number of students expected to make submissions'),
                                               new Reportdash_field_info('firstname',PARAM_TEXT,"Marker's forename"),
                                               new Reportdash_field_info('lastname',PARAM_TEXT,"Marker's surname"),
                                               new Reportdash_field_info('uid',PARAM_INT,"Marker's Moodle id")),

                           'inputs'=>array(new Reportdash_field_info('assignmentid',PARAM_INT,'Assignment id (not module id)'),
                                           new Reportdash_field_info('assignmenttype',PARAM_TEXT,'Assignment module type (may be turnitintool, assignment, or assign)'),
                                           new Reportdash_field_info('courseid',PARAM_INT,'Course id'),
                                           new Reportdash_field_info('markingwindow',PARAM_INT,"Number of seconds after due date that marking becomes 'late'. Default is one week",WEEKSECS)));
   }

//Instance

   protected $assignment;
   protected $submissionssql;
   protected $userssql;
   protected $assignmentssql;
   protected $datefield;
   protected $activetypes;
   protected $markingwindow;

   protected $pop; //Bit of state passing for webservice. Dirty.
   protected $submitted;

   function __construct()
   {
      parent::__construct(static::column_names(),false);

      $assignment=new stdClass;

      $assignment->course=required_param('courseid',PARAM_INT);
      $assignment->id=required_param('assignmentid',PARAM_INT);
      $assignment->type=required_param('assignmenttype',PARAM_ALPHA);
      $this->markingwindow=optional_param('markingwindow',86400*7,PARAM_INT);

      $this->updateBaseUrl('assignmentid',$assignment->id);
      $this->updateBaseUrl('assignmenttype',$assignment->type);
      $this->updateBaseUrl('courseid',$assignment->course);
      $this->updateBaseUrl('markingwindow',$this->markingwindow);

      $this->assignment=$assignment;

      $this->pop=$this->submitted=0;

      $modules=$this->mydb->get_records('modules',array(),'','name,id');

//The list of assignment-types which are understood by this report
      foreach(array('assign','assignment','turnitintool') as $modtype)
      {
         if(isset($modules[$modtype]))
         {
            $this->activetypes[$modtype]=$modules[$modtype]->id;
            if($this->assignment->type===$modtype)
            {
               $this->assignment->mid=$modules[$modtype]->id;
            }
         }
      }
   }

   protected function setSql($usesort=true)
   {
      global $CFG;

      $assignment=$this->assignment;
      $course=$assignment->course;

      $pfx=$CFG->prefix;

      $path=substr(str_replace('/',',',$this->mydb->get_field('context','path',array('contextlevel'=>CONTEXT_COURSE,'instanceid'=>$course))),1);

      $sql="select distinct u.id as uid
               from {$pfx}user u
               join {$pfx}role_assignments ra on u.id=ra.userid
               join {$pfx}context cx on (cx.id=ra.contextid)
               left join {$pfx}block_reportsdash_staff st on st.roleid=ra.roleid
            where st.id is null and cx.id in ($path)";

      $this->userssql=$sql;  //SQL for users

      $userssql=$this->userssql;

      if($assignment->type==='assignment')
      {

//Pathological where clause: take the <>-10 out and the query returns the same
//result but takes 26 times longer. -10 is not allowed, so this is the same result
//as having no where clause
         $this->sql= "select uid, u.firstname, u.lastname, ass.id as aid, ass.name, ass.assignmenttype, asub.*,
                           gi.*,gg.finalgrade, gg.timemodified as marktime
                      from ($userssql) students
                      join {$pfx}user u on u.id=students.uid
                      left join ({$pfx}assignment_submissions asub
                           join {$pfx}assignment ass on (ass.id=asub.assignment and ass.id = $assignment->id)
                           join {$pfx}grade_items gi on gi.iteminstance=ass.id and gi.itemmodule='assignment'
                      left join {$pfx}grade_grades gg on gg.itemid=gi.id and gg.userid=asub.userid) on asub.userid=students.uid
                      where asub.grade<>-10";

         $this->datefield='timedue';
      }

      if($assignment->type==='assign')
      {
         $this->sql= "select uid, u.firstname, u.lastname, ass.id as aid, ass.name, asub.*,
                           gi.*,gg.finalgrade, gg.timemodified as marktime, ag.grader as teacher
                      from ($userssql) students
                      join {$pfx}user u on u.id=students.uid
                      left join ({$pfx}assign_submission asub
                           join {$pfx}assign ass on (ass.id=asub.assignment and ass.id = $assignment->id)
                           join {$pfx}grade_items gi on gi.iteminstance=ass.id and gi.itemmodule='assign'
                      left join {$pfx}grade_grades gg on gg.itemid=gi.id and gg.userid=asub.userid) on asub.userid=students.uid
                      left join {$pfx}assign_grades ag on (ag.id=ass.id and ag.userid=u.id)
";
         $this->datefield='duedate';
      }

      if($assignment->type==='turnitintool')
      {
         $this->sql= "select uid, u.firstname, u.lastname, ass.id as aid, ass.name, asub.*,
                           gi.*,gg.finalgrade, gg.timemodified as marktime, 0 as teacher
                      from ($userssql) students
                      join {$pfx}user u on u.id=students.uid
                      left join ({$pfx}turnitintool_submissions asub
                           join {$pfx}turnitintool ass on (ass.id=asub.turnitintoolid and ass.id = $assignment->id)
                           join {$pfx}grade_items gi on gi.iteminstance=ass.id and gi.itemmodule='turnitintool'
                      left join {$pfx}grade_grades gg on gg.itemid=gi.id and gg.userid=asub.userid) on asub.userid=students.uid
                    where gg.rawgrade is not null ";

         $this->datefield='defaultdtdue';
      }
   }

   protected function users()
   {
      return $this->users=$this->mydb->get_records_sql($this->userssql);
   }

   protected function getData($usesort=true)
   {
      global $CFG;

      static::checkInstall();

      $this->setSql(true,$this->assignment->course);
      $raw=$this->mydb->get_records_sql($this->sql);

      $markers=array();

      $data=array();

      $users=$this->users();

      $assignment=$this->mydb->get_record($this->assignment->type,array('id'=>$this->assignment->id));

      if(isset($this->activetypes['turnitintool']))
         $turnitintoolsubmitted=get_string('submissionuploadsuccess','turnitintool'); // 8(

      $datefield=$this->datefield;
      $type=$this->assignment->type;

      $submitted=$marked=$ontime=$late=$passed=$beforedeadraw=$afterdeadraw=0;

      foreach($raw as $item)
      {
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
            $submitted++;
            if(!is_null($item->finalgrade))
            {
               $marked++;

               $markers[$item->teacher]->students='';

               if(!isset($markers[$item->teacher]))
               {
                  $markers[$item->teacher]=new stdClass;
                  $markers[$item->teacher]->afterdeadraw=
                     $markers[$item->teacher]->beforedeadraw=
                     $markers[$item->teacher]->late=
                     $markers[$item->teacher]->ontime=
                     $markers[$item->teacher]->passed = 0;
               }

               $markers[$item->teacher]->marked++;

               if($item->timecreated>$assignment->$datefield)
               {
                  $afterdeadraw++;
                  $markers[$item->teacher]->afterdeadraw++;
               }
               else
               {
                  $beforedeadraw++;
                  $markers[$item->teacher]->beforedeadraw++;
               }

               if($assignment->$datefield and ($item->marktime-$assignment->$datefield) > $this->markingwindow)
               {
                  $late++;
                  $markers[$item->teacher]->late++;
               }
               else
               {
                  $ontime++;
                  $markers[$item->teacher]->ontime++;
               }
               if($item->finalgrade>$item->gradepass)
               {
                  $passed++;
                  $markers[$item->teacher]->passed++;
               }
            }
         }
      }

      $pop=count($users);

      $item=new stdClass;

      $item->name=get_string('total');

      $item->students=$pop;

      $this->pop=$pop;
      $this->submitted=$submitted;

      $item->submissionraw=$submitted;
      $item->submissionper=($pop)? $submitted/$pop : 0;

      $item->markedraw=$marked;
      $item->markedper=($submitted)? $marked/$submitted : 0;

      $item->ontimeraw=$ontime;
      $item->ontimeper=($marked)? $ontime/$marked : 0;

      $item->lateraw=$late;
      $item->lateper=($marked)?$late/$marked : 0;

      $item->passedraw=$passed;

      $item->beforedeadraw=$beforedeadraw;
      $item->afterdeadraw=$afterdeadraw;

      $item->passedper=($marked)?$passed/$marked : 0;
      $item->beforedeadper=($marked)? $beforedeadraw/$marked : 0;
      $item->afterdeadper=($marked)? $afterdeadraw/$marked : 0;

      $item->user=false;

      $item->istotal=true;

      $data[]=$item;

      $markdata=array();

      foreach($markers as $uid=>$marking)
      {
         if($uid)
         {
            $user=$this->mydb->get_record('user',array('id'=>$uid));

            $item=new stdClass;

            $item->name=$user->lastname;

            $item->user=$user;

            $item->submissionper='';
//            $item->submissionraw=$marking->submitted;

            $pop=$marking->marked;

            $item->markedraw=$marking->marked;
            $item->markedper=$marking->marked/$pop;

            $item->ontimeraw=$marking->ontime*1;
            $item->ontimeper=$marking->ontime/$pop;

            $item->lateraw=$marking->late*1;
            $item->lateper=$marking->late/$pop;

            $item->passedraw=$marking->passed*1;

            $item->beforedeadraw=$marking->beforedeadraw*1;
            $item->afterdeadraw=$marking->afterdeadraw*1;

            if($marked)
            {
               $item->passedper=$marking->passed/$pop;
               $item->beforedeadper=$marking->beforedeadraw/$pop;
               $item->afterdeadper=$marking->afterdeadraw/$pop;
            }
            else
            {
               $item->passedper=0;
               $item->beforedeadper=0;
               $item->afterdeadper=0;
            }

            $markdata[]=$item;
         }
      }

//Put marker data before the total by default

      foreach($data as $item)
      {
         $markdata[]=$item;
      }

      if($this->sort())
      {
         block_reportsdash_report::external_sort($markdata,$this->sort());
      }

      return $this->data=$markdata;

   }

   protected function preprocessWebservice($rowdata)
   {
      if(isset($rowdata->istotal))
      {
         return null;
      }

      if($user=$rowdata->user)
      {
         $rowdata->firstname=$user->firstname;
         $rowdata->lastname=$user->lastname;
         $rowdata->uid=$user->id;
      }

      $rowdata->submissionraw=$this->submitted;
      $rowdata->students=$this->pop;

      return $rowdata;
   }

   protected function preprocessExport($rowdata)
   {
      global $CFG;

      $rowdata->submissionper=format_float(100*$rowdata->submissionper,2);
      $rowdata->markedper=format_float(100*$rowdata->markedper,2);
      $rowdata->ontimeper=format_float(100*$rowdata->ontimeper,2);
      $rowdata->lateper=format_float(100*$rowdata->lateper,2);
      $rowdata->passedper=format_float(100*$rowdata->passedper,2);
      $rowdata->beforedeadper=format_float(100*$rowdata->beforedeadper,2);
      $rowdata->afterdeadper=format_float(100*$rowdata->afterdeadper,2);

      if($rowdata->user)
      {
         $rowdata->name=$rowdata->user->lastname.', '.$rowdata->user->firstname;
         $rowdata->submissionper='';
      }

      return $rowdata;
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG;

      $rowdata->submissionper=format_float(100*$rowdata->submissionper,2);
      $rowdata->markedper=format_float(100*$rowdata->markedper,2);
      $rowdata->ontimeper=format_float(100*$rowdata->ontimeper,2);
      $rowdata->lateper=format_float(100*$rowdata->lateper,2);
      $rowdata->passedper=format_float(100*$rowdata->passedper,2);
      $rowdata->beforedeadper=format_float(100*$rowdata->beforedeadper,2);
      $rowdata->afterdeadper=format_float(100*$rowdata->afterdeadper,2);

      if($rowdata->user)
      {
         $rowdata->name=$rowdata->user->firstname.'&nbsp;'.$rowdata->user->lastname;
         $rowdata->submissionper='';
         block_reportsdash::wrap($rowdata->name,
                                 "$CFG->wwwroot/user/view.php?id=".$rowdata->user->id);
      }

      return $rowdata;
   }

   protected function reportHeader($ex=false)
   {
      global $CFG;

      $atitle=$this->mydb->get_field($this->assignment->type,'name',array('id'=>$this->assignment->id));
      $ctitle=$this->mydb->get_field('course','fullname',array('id'=>$this->assignment->course));

      $cmid=$this->mydb->get_field('course_modules','id',array('course'=>$this->assignment->course,
                                                               'module'=>$this->assignment->mid,
                                                               'instance'=>$this->assignment->id));

      if($ex)
      {
         $headers=array(static::display_name()." - $ctitle: $atitle");
         $ex->output_headers($headers);
      }
      else
      {
         global $OUTPUT;
         block_reportsdash::wrap($ctitle,"$CFG->wwwroot/course/view.php?id={$this->assignment->course}");
         block_reportsdash::wrap($atitle,"$CFG->wwwroot/mod/{$this->assignment->type}/view.php?id=$cmid");
         print $OUTPUT->heading("$ctitle: $atitle",3);
      }
   }

   function get_filter_form()
   {
      $this->filterform=new block_reportsdash_buttononly_filter_form(null, array('rptname'=>$this->reportname()),
                                                                                '','',array('id'=>'rptdashfilter'));
      return $this->filterform;
   }
}