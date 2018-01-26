<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_departmentassignments_report extends block_reportsdash_report
{
   const MEMCACHE_TIMEOUT=600; // Only for the big cache.

   static function nevershow()
   {
      return true;  ///Suspended
   }

    //Suspended
   static function ex_services()
   {
      return array('reportsdash_departmental_assignments'=>'Returns information on marking aggregated across a top or second level category');
   }

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
   protected static function fields()
   {
      $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
      return (object)array('outputs'=>array(new Reportdash_field_info('name',PARAM_TEXT,"Course name"),
                                            new Reportdash_field_info('parent',PARAM_TEXT,"Parent category"),
                                            new Reportdash_field_info('fullname',PARAM_TEXT,"Not used in webservice",'',false,true),
                                            new Reportdash_field_info('acount',PARAM_INT,'Number of assignments in course'),
                                            new Reportdash_field_info('pop',PARAM_INT,'Number of students on course'),
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

                           'webservice'=>array(new Reportdash_field_info('cid',PARAM_INT,"Course's Moodle id"),
                                               new Reportdash_field_info('firstname',PARAM_TEXT,"Forename of course leader, if any"),
                                               new Reportdash_field_info('lastname',PARAM_TEXT,"Surname of course leader, if any"),
                                               new Reportdash_field_info('uid',PARAM_INT,"Course leader's Moodle id"),
                                               new Reportdash_field_info('toplevelid',PARAM_INT,"ID of top-level category")),

                           'inputs'=>array(new Reportdash_field_info('fromfilter',PARAM_INT,'Unix timestamp: report on assignments due after this time',$defaulttime),
                                           new Reportdash_field_info('levelfilter',PARAM_INT,'Either top-level category ID or second level negative ID for report scope.',0),
                                           new Reportdash_field_info('opencourses',PARAM_INT,'1 = include courses which have no due date',0),
                                           new Reportdash_field_info('markingwindow',PARAM_INT,"Number of seconds after due date that marking becomes 'late'. Default is one week",WEEKSECS)));
   }



//Instance

   protected $submissionssql;
   protected $usersql;
   protected $assignmentssql;
   protected $coursessql;
   protected $context;
   protected $activetypes;
   protected $canseecourse;
   protected $assignmentcache;
   protected $memcachekey;

   function __construct()
   {
      global $USER;
      parent::__construct(static::column_names(),false);

      if($l=optional_param('levelfilter',0,PARAM_INT))
      {
         $this->setFilter('levelfilter',$l);
      }

      $modules=$this->mydb->get_records('modules',array(),'','name,id');

//The list of assignment-types which are understood by this report
      foreach(array('assignment','assign','turnitintool') as $modtype)
      {
         if(isset($modules[$modtype]))
         {
            $this->activetypes[$modtype]=$modules[$modtype]->id;
            $this->assignmentcache[$modtype]=array();
         }
      }

      $this->canseecourse=block_reportsdash_courseassignments_report::can_view_report($USER->id);
   }

   protected function defaultFilters()
   {
      $filters=new stdClass;
      $filters->fromfilter=time();
      return $filters;
   }

//Because of the difficulties in handling large sites, this
//function has gradually drifted off-purpose. It no longer
//sets all the sql and it actually calls the DB to cache
//some data for later.
   protected function setSql($usesort=true)
   {
      global $CFG;

      $pfx=$CFG->prefix;

      $f=$this->filters;

      if(!empty($f->levelfilter))
      {
         if($f->levelfilter<0)
         {
            $contextbit="r.id=".-$f->levelfilter;
         }
         else
         {
            $filtercat=$this->mydb->get_record('course_categories',array('id'=>$f->levelfilter));
            if($filtercat->depth==1)
            {
               $contextbit="substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2)=$f->levelfilter";
            }
            else
            {
               $bits=explode('/',$filtercat->path);
               $tlc=$bits[1];
               $contextbit="substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2) = $tlc and (cc1.path like('%/$f->levelfilter/%') or cc1.path like('%/$f->levelfilter')) ";
            }
         }
      }
      else
      {
         $contextbit='1 ';
      }

      $from=$f->fromfilter;

      if(!isset($f->opencourses))
         $f->opencourses=0;

      $this->memcachekey="departmentassignmentreport|{$f->levelfilter}|{$f->fromfilter}|{$f->opencourses}|{$f->markingwindow}";

      $courselv=CONTEXT_COURSE;

//Make an array of bits for the union
      $ucoursessql=$uassignmentssql=array();

      if(@$mid=$this->activetypes['assignment'])
      {
         $coursessql="select distinct c.id, c.fullname,
                            cx.id as cxid, cx.depth, cc1.path,
                            substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2) as tlcat,
                            cc1.name parent, cc1.id parentid
                             FROM {$pfx}assignment ass
                             JOIN {$pfx}course c on c.id=ass.course
                             JOIN {$pfx}context cx on (cx.instanceid=c.id and cx.contextlevel=$courselv)
                             JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                             JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2)
                             JOIN {$pfx}block_reportsdash_region r on r.id=rc.rid where  $contextbit and r.visible=1 and c.visible=1 ";

         $assignmentssql="select distinct ass.id, ass.course
                                      FROM {$pfx}course_modules cm
                                      JOIN {$pfx}assignment ass on (ass.id=cm.instance and cm.module=$mid)
                                      JOIN {$pfx}course_sections cs on cs.id=cm.section
                                      WHERE cs.visible=1 and cm.visible=1";

         if(empty($f->opencourses))
         {
            $coursessql.=" and timedue>=$from";
            $assignmentssql.=" and timedue>=$from";
         }
         else
         {
            $coursessql.=" and (timedue>=$from or timedue=0)";
            $assignmentssql.=" and (timedue>=$from or timedue=0)";
         }

         $ucoursessql[]="($coursessql)";

//We'll trade memory for speed here. This saves about 1/3rd off the overall query, which
//is significent on a large site when the department filter is set to show all courses.
         $key="assignment|{$from}|{$mid}";
         if(!($this->memcache and $this->assignmentcache['assignment']=$this->memcache->get($key)))
         {
            $this->assignmentcache['assignment']=block_reportsdash::reindex2d($this->mydb->get_recordset_sql($assignmentssql),'course');
            ($this->memcache and  $this->memcache->set($key,$this->assignmentcache['assignment'],false,120));
         }
      }

      if(@$mid=$this->activetypes['assign'])
      {
         $coursessql="select distinct c.id, c.fullname, cx.id as cxid, cx.depth, cc1.path,
                            substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2) as tlcat,
                            cc1.name parent, cc1.id parentid
                             FROM {$pfx}assign ass
                             JOIN {$pfx}course c on c.id=ass.course
                             JOIN {$pfx}context cx on (cx.instanceid=c.id and cx.contextlevel=$courselv)
                             JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                             JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2)
                             JOIN {$pfx}block_reportsdash_region r on r.id=rc.rid where  $contextbit and r.visible=1 and c.visible=1 ";


         $assignmentssql="select distinct ass.id, ass.course, ass.duedate as timedue
                                      FROM {$pfx}course_modules cm
                                      JOIN {$pfx}assign ass on (ass.id=cm.instance and cm.module=$mid)
                                      JOIN {$pfx}course_sections cs on cs.id=cm.section
                                      WHERE cs.visible=1 and cm.visible=1  ";

         if(empty($f->opencourses))
         {
            $coursessql.=" and duedate>=$from";
            $assignmentssql.=" and duedate>=$from";
         }
         else
         {
            $coursessql.=" and (duedate>=$from or duedate=0)";
            $assignmentssql.=" and (duedate>=$from or duedate=0)";
         }

         $ucoursessql[]="($coursessql)";

         $key="assign|{$from}|{$mid}";
         if(!($this->memcache and $this->assignmentcache['assign']=$this->memcache->get($key)))
         {
            $this->assignmentcache['assign']=block_reportsdash::reindex2d($this->mydb->get_recordset_sql($assignmentssql),'course');
            ($this->memcache and $this->memcache->set(md5($assignmentssql),$this->assignmentcache['assign'],false,120));
         }

      }

      if(@$mid=$this->activetypes['turnitintool'])
      {
         $coursessql="select distinct c.id, c.fullname, cx.id as cxid, cx.depth, cc1.path,
                            substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2) as tlcat,
                            cc1.name parent, cc1.id parentid
                             FROM {$pfx}turnitintool ass
                             JOIN {$pfx}course c on c.id=ass.course
                             JOIN {$pfx}context cx on (cx.instanceid=c.id and cx.contextlevel=$courselv)
                             JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                             JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring(substring_index(cc1.path, '/', 2),length(substring_index(cc1.path, '/', 2 - 1)) + 2)
                             JOIN {$pfx}block_reportsdash_region r on r.id=rc.rid where  $contextbit and r.visible=1 and c.visible=1 ";


         $assignmentssql="select distinct ass.id, ass.course, ass.defaultdtdue as timedue
                                      FROM {$pfx}course_modules cm
                                      JOIN {$pfx}turnitintool ass on (ass.id=cm.instance and cm.module=$mid)
                                      JOIN {$pfx}course_sections cs on cs.id=cm.section
                                      WHERE cs.visible=1 and cm.visible=1";

         if(empty($f->opencourses))
         {
            $coursessql.=" and defaultdtdue>=$from";
            $assignmentssql.=" and defaultdtdue>=$from";
         }
         else
         {
            $coursessql.=" and (defaultdtdue>=$from or defaultdtdue=0)";
            $assignmentssql.=" and (defaultdtdue>=$from or defaultdtdue=0)";
         }

         $ucoursessql[]="($coursessql)";

         $key="turnitiintool|{$from}|{$mid}";
         if(!($this->memcache and $this->assignmentcache['turnitintool']=$this->memcache->get($key)))
         {
            $this->assignmentcache['turnitintool']=block_reportsdash::reindex2d($this->mydb->get_recordset_sql($assignmentssql),'course');
            ($this->memcache and $this->memcache->set($key,$this->assignmentcache['turnitintool'],false,120));
         }

      }

      $this->coursessql=implode(' UNION ',$ucoursessql);
//     print $this->coursessql;
//     exit;
   }

///Get all the submissions on a course.
   protected function submissionssql($course,$type)
   {
      global $CFG;

      $pfx=$CFG->prefix;

//Find all users on a course, their submitted assignments, grade given and required grade

      $userssql=$this->userssql($course);

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
                      join {$pfx}assignment ass on (ass.id=asub.assignment and ass.course = :course)
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
                      join {$pfx}assign ass on (ass.id=asub.assignment and ass.course = :course)
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
                      join {$pfx}turnitintool ass on (ass.id=asub.turnitintoolid and ass.course = :course)
                      join {$pfx}grade_items gi on gi.iteminstance=ass.id and gi.itemmodule='turnitintool'
                 left join {$pfx}grade_grades gg on gg.itemid=gi.id and gg.userid=asub.userid) on asub.userid=students.uid
               where gg.rawgrade is not null ";
      }
   }

//Get all the students on a course.
   protected function users($course)
   {
      return $this->mydb->get_records_sql($this->userssql($course));
   }

//Return the SQL for student users on a course.
//As well as the function to find those users, this
//result is embedded into the SQL to find submissions.
   protected function userssql($course)
   {
      global $CFG;

      $pfx=$CFG->prefix;

      if($path=substr(str_replace('/',',',
                                  $this->mydb->get_field('context','path',array('contextlevel'=>CONTEXT_COURSE,'instanceid'=>$course))),1))
      {
         $sql="SELECT DISTINCT u.id as uid
                      FROM {$pfx}user u
                      JOIN {$pfx}role_assignments ra on u.id=ra.userid
                      JOIN {$pfx}context cx on (cx.id=ra.contextid)
                      LEFT JOIN {$pfx}block_reportsdash_staff st on st.roleid=ra.roleid
               WHERE st.id is null and cx.id in ($path)";
         return $sql;
      }
      return "select -1 as uid";
   }

   protected function getData($usesort=true)
   {
      global $CFG;

      static::checkInstall();

      $raw=$semifinal=array();
      $data=array();

      $this->setSql(false);

      $hits=0;

      $start=microtime(true);

      if(!($this->memcache and $data=$this->memcache->get($this->memcachekey)))
      {
         $allcats=$this->mydb->get_records('course_categories');

         $cc=array();  //Course Cache for later

         $leaders=array();

//Used to allow multiple roles, now doesn't.
         $leaderroles=get_config('block_reportsdash','leaderroles');

         if($leaderroles)
         {

            $leaders=$this->mydb->get_recordset_sql("select contextid, u.firstname, u.lastname, u.id as uid
                                                            FROM {role_assignments} ra
                                                            JOIN {user} u on (ra.userid=u.id and roleid = :leaderroles)",
                                                    array('leaderroles'=>$leaderroles));

            $leaders=block_reportsdash::reindex($leaders,'contextid');
         }

         if(isset($this->activetypes['turnitintool']))
            $turnitintoolsubmitted=get_string('submissionuploadsuccess','turnitintool'); // 8(

         foreach($this->mydb->get_recordset_sql($this->coursessql) as $course)
         {
//Reset this course's counters
            $submitted=$marked=$ontime=$late=$passed=$totalassignments=$pop=0;

            $basepop=count($this->users($course->id));

            foreach(array_keys($this->activetypes) as $type)
            {
               $assignments=$this->assignmentcache[$type][$course->id];

               $totalassignments+=count($assignments);

               $key="submissionssql|{$course->id}|{$type}";
               if(!($this->memcache and $raw=$this->memcache->get($key)))
               {
                  $raw=block_reportsdash::rstoarray($this->mydb->get_recordset_sql($this->submissionssql($course->id,$type),array('course'=>$course->id)));
                  ($this->memcache and $this->memcache->set($key,$raw,false,300));
               }
               else
               {
                  $hits++;
               }

               foreach($raw as $item)
               {

                  $aid=$item->aid;

//Hopefully the old assignment module won't get any more stupid subtypes added to it
                  if(isset($assignments[$aid]) and
                     ($type==='assignment' and (($item->assignmenttype=='upload' and $item->data2=='submitted')
                                                or (($item->assignmenttype==='uploadsingle'  and !empty($item->numfiles))
                                                    or
                                                    ($item->assignmenttype==='online' and !empty($item->timecreated))
                                                    or
                                                    ($item->assignmenttype==='offline' and !empty($item->timecreated))
                                                   )
                        )
                      or
                      ($type==='assign' and $item->status==='submitted') or
                      ($type==='turnitintool' and $item->submission_status===$turnitintoolsubmitted)))
                  {
                     $submitted++;
                     if(!is_null($item->finalgrade))
                     {
                        $marked++;
                        if($assignments[$aid]->timedue and ($item->marktime-$assignments[$aid]->timedue)>$this->filters->markingwindow)
                        {
                           $late++;
                        }
                        else
                        {
                           $ontime++;
                        }
                        if($item->finalgrade>$item->gradepass)
                        {
                           $passed++;
                        }
                     }
                  }
               }
            }

            $pop=$basepop*$totalassignments;

            $item=new stdClass;
///Try to force nulls to end. May not work with unicode names.
            $item->firstname='ZZZZZZZZZZZZZZZZZZZ';
            $item->lastname='ZZZZZZZZZZZZZZZZZZZ';
            $item->fullleader=false;

            if(@$l=$leaders[$course->cxid])
            {
               $item->fullleader=$l;
               $item->firstname=$l->firstname;
               $item->lastname=$l->lastname;
            }

            $item->name=$course->fullname;
            $item->cid=$course->id;

            $item->parent=$course->parent;
            $item->parentid=$course->parentid;

//Caching
            @($bits=$cc[$course->parentid] or $bits=$cc[$course->parentid]=explode('/',$allcats[$course->parentid]->path));

            $item->toplevel=$allcats[$bits[1]]->name;
            $item->toplevelid=$bits[1];

            $item->pop=$basepop;

            $item->acount=$totalassignments;

            $item->submissionraw=$submitted;
            $item->submissionper=($pop)? $submitted/$pop: 0;

            $item->markedraw=$marked;
            $item->markedper=($submitted)? $marked/$submitted : 0;

            $item->ontimeraw=$ontime;
            $item->ontimeper=($marked)? $ontime/$marked : 0 ;

            $item->lateraw=$late;
            $item->lateper=($marked)? $late/$marked: 0;

            $item->passedraw=$passed;
            $item->passedper=($marked)? $passed/$marked : 0;

            $data[]=$item;
         }

         if($this->memcache)
         {
            $this->memcache->set($this->memcachekey,$data,false,static::MEMCACHE_TIMEOUT);
         }
      }

      $end=microtime(true);

      $this->records=count($data);

//      print $end-$start;
//      print " $this->records";

      if($this->sort())
      {
         block_reportsdash_report::external_sort($data,$this->sort(),true);
      }
//      print $hits;
      return $this->data=$data;

   }

   protected function setColumnStyles()
   {
      parent::setColumnStyles();
      $this->table()->column_style_all('text-align','right');
      $this->table()->column_style('name','text-align','left');
      $this->table()->column_style('toplevel','text-align','left');
   }

   protected function preprocessWebservice($rowdata)
   {
      if(!$rowdata->fullleader)
      {
         $rowdata->firstname='';
         $rowdata->lastname='';
         $rowdata->uid=0;
      }
      else
      {
         $rowdata->uid=$rowdata->fullleader->uid;
      }

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

      $rowdata->parent=static::full_path($this->mydb,$rowdata->cid,false);

      return $rowdata;
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG;

      $rowdata->submissionper=format_float(100*$rowdata->submissionper,2).'%';
      $rowdata->markedper=format_float(100*$rowdata->markedper,2).'%';
      $rowdata->ontimeper=format_float(100*$rowdata->ontimeper,2).'%';
      $rowdata->lateper=format_float(100*$rowdata->lateper,2).'%';
      $rowdata->passedper=format_float(100*$rowdata->passedper,2).'%';

      if($rowdata->fullleader)
      {
         $rowdata->fullname=$rowdata->fullleader->firstname.' '.$rowdata->fullleader->lastname;
         block_reportsdash::wrap($rowdata->fullname,
                                 "$CFG->wwwroot/user/view.php?id=".$rowdata->fullleader->uid);
      }
      else
      {
         $rowdata->firstname='';
         $rowdata->lastname='';
      }

      if($this->canseecourse)
         block_reportsdash::wrap($rowdata->name,"$CFG->wwwroot/blocks/reportsdash/report.php?rptname=courseassignments_report&courseid=$rowdata->cid&clearfilters=1&markingwindow={$this->filters->markingwindow}");

      $tip=static::full_path($this->mydb,$rowdata->cid,false);
      $rowdata->name=html_writer::tag('span',$rowdata->name,array('title'=>$tip));

      block_reportsdash::wrap($rowdata->parent,"$CFG->wwwroot/course/category.php?id=$rowdata->parentid");

      return $rowdata;
   }

   function get_filter_form()
   {
      $this->filterform=new block_reportsdash_departmentassignments_filter_form(null, array('rptname'=>$this->reportname(),
                                                                                            'db'=>$this->mydb),
                                                                                '','',array('id'=>'rptdashfilter'));
      return $this->filterform;
   }
}