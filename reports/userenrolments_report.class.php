<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_userenrolments_report extends block_reportsdash_report
{
   static function services()
   {
      return array('reportsdash_userenrolments'=>'Report all courses a user is enrolled on in a non-staff role');
   }

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields which are only output for the webservice
   protected static function fields()
   {
      return (object)array('outputs'=>array(new Reportdash_field_info('fullname',PARAM_TEXT,"Full name of course"),
                                            new Reportdash_field_info('shortname',PARAM_TEXT,"Short name of course"),
                                            new Reportdash_field_info('idnumber',PARAM_TEXT,"ID number of course"),
                                            new Reportdash_field_info('startdate',PARAM_INT,"Course startdate (Unix epoch)")
                              ),

                           'webservice'=>array(new Reportdash_field_info('cid',PARAM_INT,"Course's Moodle id"),
                                               new Reportdash_field_info('roleid',PARAM_INT,"Roleid"),
                                               new Reportdash_field_info('duration',PARAM_INT,"Duration of course (in seconds). -1=N/A"),
                                               new Reportdash_field_info('rolename',PARAM_TEXT,"ID number of course"),
                                               new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category"),
                                               new Reportdash_field_info('visible',PARAM_INT,"Is course visible? 0=no ")
                              ),

                           'inputs'=>array(new Reportdash_field_info('uid',PARAM_INT,"User's Moodle id",0),
                                           new Reportdash_field_info('uname',PARAM_TEXT,"User's login name",''),
                                           new Reportdash_field_info('idnumber',PARAM_TEXT,"User's ID (arbitary string of <256 characters)",'')
                              ));
   }


   static function nevershow()
   {
      return true;
   }

//Instance
//Just a skeleton for testing/debugging. Never intended to be shown except via webservice

   function __construct()
   {
      parent::__construct(static::column_names(),false);
       $this->totalStudents=0;
   }

   protected function setSql($usesort=true)
   {
       global $CFG;

       $pfx=$CFG->prefix;

       $f=$this->filters;
       mylog(print_r($f,true),false);

       $wherebit=' 0 and ';
       if(!empty($f->uid))
       {
          $wherebit="u.id=$f->uid and ";
       }
       elseif(!empty($f->uname))
       {
          $wherebit="u.username='$f->uname' and ";
       }
       elseif(!empty($f->idnumber))
       {
          $wherebit="u.idnumber='$f->idnumber' and ";
       }

       $now=time();

       $courselv=CONTEXT_COURSE;

       $sql="SELECT distinct c.id cid, fullname, c.shortname, c.idnumber, startdate, ra.roleid, c.visible,
                      cc1.id as parentid, cc1.name as parent,r.shortname as rolename, format, numsections
                    FROM {$pfx}course c
                    JOIN {$pfx}enrol e on (e.courseid=c.id)
                    JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                    JOIN {$pfx}context cx on (cx.instanceid=c.id and cx.contextlevel=$courselv)
                    LEFT JOIN ({$pfx}role_assignments ra
                         JOIN {$pfx}role r on r.id=ra.roleid
                         JOIN {$pfx}user u on u.id=ra.userid
                         JOIN {$pfx}user_enrolments ue on (ue.userid=u.id)
                         LEFT JOIN {$pfx}block_reportsdash_staff brs on brs.roleid=ra.roleid)
                                   ON (ra.contextid=cx.id)
                    WHERE $wherebit ue.enrolid=e.id and u.deleted=0 and
                          ((ue.timeend>$now or ue.timeend=0) and (ue.timestart<$now or ue.timestart=0))
                          and brs.id IS NULL";

       $this->sql=$sql;
   }

   function get_filter_form()
   {
       $this->filterform=new block_reportsdash_enrolments_filter_form(null, array('rptname'=>$this->reportname(),
                                                                                  'singlemode'=>0,
                                                                                  'notopencourses'=>1),
                                                                      '','',array('id'=>'rptdashfilter'));
       return $this->filterform;
   }

   protected function defaultFilters()
   {
      return new stdClass;
   }

   protected function preprocessWebservice($rowdata)
   {
      if($rowdata->format==='weeks')
      {
         $rowdata->duration=$rowdata->numsections*WEEKSECS;
      }
      else
      {
         $rowdata->duration=-1;
      }
      return $rowdata;
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG;
      block_reportsdash::wrap($rowdata->course,"$CFG->wwwroot/course/view.php?id=$rowdata->cid");
      block_reportsdash::wrap($rowdata->parent,"$CFG->wwwroot/course/category.php?id=$rowdata->parentid");
      block_reportsdash::wrap($rowdata->students,"$CFG->wwwroot/blocks/reportsdash/report.php?rptname=enroldetail_report&item=$rowdata->cid");

      return $rowdata;
   }
}