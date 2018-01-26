<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
class block_reportsdash_enroldetail_report extends block_reportsdash_report
{

   const DEFAULT_PAGE_SIZE=60;

   static function nevershow()
   {
        return true;
   }

   protected static function timeformat()
   {
      return get_string('strftimedatetime','langconfig');
   }

   static function services()
   {
      return array('reportsdash_enroldetail'=>'Report users on course or category who are enrolled with given method, with last login time');
   }

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
   protected static function fields()
   {
      return (object)array('outputs'=>array(new Reportdash_field_info('firstname',PARAM_TEXT,"User's forename"),
                                            new Reportdash_field_info('lastname',PARAM_TEXT,"User's surname"),
                                            new Reportdash_field_info('lastlogin',PARAM_INT,'Unix timestamp of last login')),

                           'webservice'=>array(new Reportdash_field_info('uid',PARAM_INT,"User's Moodle id")),

                           'exports'=>array(new Reportdash_field_info('uid',PARAM_INT,"User's Moodle id"),
                                            new Reportdash_field_info('timestamp',PARAM_INT,"User's last login as Unix timestamp")),

                           'inputs'=>array(new Reportdash_field_info('item',PARAM_INT,'ID of course or negative ID of category (0 for site-wide)'),
                                           new Reportdash_field_info('enrolfilter',PARAM_ALPHANUMEXT,"Name of enrolment method to show, or blank for all users",'')));
   }

//Instance
   protected $item=0;
   protected $enrolfilter;
   protected $itemname='';

   function __construct()
   {
      parent::__construct(static::column_names(),false);

      $this->totalStudents=0;

      $item=$this->item=required_param('item',PARAM_INT);
      $method=$this->enrolfilter=required_param('enrolfilter',PARAM_ALPHANUMEXT);

      $this->updateBaseUrl('item',$this->item);
      $this->updateBaseUrl('enrolfilter',(empty($this->method)? $this->method=$method:$this->method));

      if($this->item<0)
      {
         $this->itemname=$this->mydb->get_field('course_categories','name',array('id'=>-$item));
      }
      else
      {
         if($item===0)
            $item=1;

         $this->itemname=$this->mydb->get_field('course','fullname',array('id'=>$item));
      }
   }

//We're listing the students in a course or a category (or side-wide)
//Based on the value in the item param. If this is positive, it is
//a course; otherwise it is a category.
   protected function setSql($usesort=true)
   {
       global $CFG;

       $pfx=$CFG->prefix;

       $item=$this->item;

       if(isset($this->enrolfilter))
       {
           $enrolbit=" and e.enrol='$this->enrolfilter'";
       }
       else
       {
           $enrolbit='';
       }

       if($item<=0)
       {
            $item=-$item;

            $sql="select u.firstname,u.lastname,u.id as uid, max(currentlogin) as lastlogin
                    FROM {$pfx}course_categories cc1
                    JOIN {$pfx}course c on cc1.id=c.category
                    JOIN ({$pfx}enrol e
                         JOIN {$pfx}user_enrolments ue on ue.enrolid=e.id
                         JOIN {$pfx}user u on u.id=ue.userid) ON (e.courseid=c.id $enrolbit)
                    WHERE u.deleted=0";

            if($item)
            {
                $sql.=" and substring(substring_index(cc1.path, '/', 3),
                             length(substring_index(cc1.path, '/', 3 - 1)) + 2) =:levelfilter";
                $this->params['levelfilter']=$item;
            }

            $sql.="  GROUP BY u.id ";

// this didn't work for this query for some reason (probably Moodle bug)
//            $this->pathomarams['cata']=-$item;
//            $this->params['catb']=-$item;
       }
       else  //Course
       {
            $sql="select u.firstname,u.lastname,u.id as uid, currentlogin as lastlogin
                    FROM {$pfx}course c
                    JOIN ({$pfx}enrol e
                         JOIN {$pfx}user_enrolments ue on ue.enrolid=e.id
                         JOIN {$pfx}user u on u.id=ue.userid) ON (e.courseid=c.id $enrolbit)
                    WHERE u.deleted=0 and c.id=:cid";

            $this->params['cid']=$item;
       }

       if(!empty($usesort) and $this->sort())
       {
            $sql.=' order by '.$this->sort();
       }

       $this->sql=$sql;
   }

   protected function reportHeader($ex=false)
   {
      parent::reportHeader($ex);
      if($ex)
      {
         $ex->output_headers(array(html_entity_decode($this->itemname)));
      }
      else
      {
         global $OUTPUT;
         print $OUTPUT->heading($this->itemname,2);
         print $OUTPUT->heading(get_string('total').': '.$this->records,3);
      }
   }

   protected function prepareHtml()
   {
      parent::prepareHtml();
      $table=$this->table();
      $table->set_attribute('width','50%');
   }

   protected function preprocessExport($rowdata)
   {
      global $CFG;

      $n=$rowdata->lastlogin;
      $rowdata->timestamp=$n;

      if($n)
      {
	 $rowdata->lastlogin=userdate($n,static::timeformat());
      }
      else
      {
	 $rowdata->lastlogin=get_string('never');
      }

      return $rowdata;
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG;

      $n=$rowdata->lastlogin;

      if($n)
      {
	 $rowdata->lastlogin=userdate($n,static::timeformat());
      }
      else
      {
	 $rowdata->lastlogin=get_string('never');
      }

      block_reportsdash::wrap($rowdata->firstname,"$CFG->wwwroot/user/view.php?id=$rowdata->uid");
      block_reportsdash::wrap($rowdata->lastname,"$CFG->wwwroot/user/view.php?id=$rowdata->uid");

      if(class_exists('block_reportsdash_useractivity_report') and $n)
      {
           block_reportsdash::wrap($rowdata->lastlogin,"$CFG->wwwroot/blocks//reportsdash/report.php?rptname=useractivity_report&uid=$rowdata->uid&timefrom=$n");
      }

      return $rowdata;

   }

   protected function setColumnStyles()
   {
      $this->table->column_style_all('padding-right','12px');
      $this->table->column_style_all('text-align','left');
      $this->table->column_style('lastlogin','text-align','right');
   }

   function get_filter_form()
   {
      $this->filterform=new block_reportsdash_enroldetail_filter_form(null, array('rptname'=>$this->reportname(),
                                                                                  'item'=>$this->item,
                                                                                  'enrolfilter'=>$this->enrolfilter),
                                                                      '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
   }


}
