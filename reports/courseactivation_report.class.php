<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
class block_reportsdash_courseactivation_report extends block_reportsdash_report
{
   static function services()
   {
      return array('reportsdash_course_activations'=>'Returns courses made visible during date range');
   }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
   protected static function fields()
   {
      $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
      return (object)array('outputs'=>array(new Reportdash_field_info('coursename',PARAM_TEXT,"Full name of course"),
                                            new Reportdash_field_info('parent',PARAM_TEXT,'Name of parent category'),
                                            new Reportdash_field_info('leader',PARAM_TEXT,'Course leader lastname'),
                                            new Reportdash_field_info('date',PARAM_INT,'Timestamp of course activation')),
                           'webservice'=>array(new Reportdash_field_info('id',PARAM_INT,'Moodle id of course'),
                                               new Reportdash_field_info('firstname',PARAM_TEXT,'Course leader firstname'),
                                               new Reportdash_field_info('uid',PARAM_INT,'Moodle id of course leader')),
                           'inputs'=>array(new Reportdash_field_info('fromfilter',PARAM_INT,'Unix timestamp, start of report period',$defaulttime),
                                           new Reportdash_field_info('tofilter',PARAM_INT,'Unix timestamp, end of report period',time())));
   }


   function __construct()
   {
      parent::__construct(static::column_names(),false);
   }

   protected function setSql($usesort=true)
   {
      global $CFG;

      $pfx=$CFG->prefix;

      $leaderfields='0 as uid,"" as leader';
      $leaderjoin='';

//Used to allow multiple values. May do so again one day.
      $leaderroles=get_config('block_reportsdash','leaderroles');

      $courselv=CONTEXT_COURSE;

      if($leaderroles)
      {
         $leaderfields='su.firstname,su.lastname, su.lastname as leader,su.id as uid';

//Group by clause prevents duplicates even if multiple roles
// TODO: enforce role sortorder if there are multiples.
         $leaderjoin="LEFT JOIN (select contextid, u.firstname, u.lastname, u.id
                                   FROM {$pfx}role_assignments ra
                                   JOIN {$pfx}user u on (ra.userid=u.id and roleid = $leaderroles) group by contextid) su
                            ON cx.id=su.contextid";
      }

//Courses which were made visible OR created in the given period.
      $sql="select c.id,fullname as coursename, c.timecreated as date,
                   cc1.name as parent, cc1.id as parentid,
                    substring_index(substring_index(cc1.path, '/', 2),'/',-1) as tlcat,
                   $leaderfields
                   FROM {$pfx}course c
                   JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                   JOIN {$pfx}block_reportsdash_regcats rc on rc.cid=substring_index(substring_index(cc1.path, '/', 2),'/',-1)
                   JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid and r.visible=1)
                   JOIN {$pfx}context cx ON (cx.contextlevel=$courselv and cx.instanceid=c.id)
                   $leaderjoin
                  WHERE c.timecreated > :from2 and c.timecreated < :to2
           ";


      $filters=$this->filters;
      if(!empty($filters->levelfilter)){
         if($filters->levelfilter>0)
         {
             $filtercat=$this->mydb->get_record('course_categories',array('id'=>$filters->levelfilter));
             if($filtercat->depth==1)
             {
                $sql.=" and substring_index(substring_index(cc1.path, '/', 2),'/',-1)=$filters->levelfilter";
             }
             else
             {
                $bits=explode('/',$filtercat->path);
                $tlc=$bits[1];
                $sql.=" and substring_index(substring_index(cc1.path, '/', 2),'/',-1)=$tlc and (cc1.path like('%/$filters->levelfilter/%') or cc1.path like('%/$filters->levelfilter')) ";
             }
         }
         elseif($filters->levelfilter<0)
         {
            $sql.=" and r.id=".-$filters->levelfilter;
         }
      }

      if($usesort and $sort=$this->table->get_sort_columns())
      {
         $sorted_array = array_keys($sort);
         if(($coll=reset($sorted_array))==='leader' and $leaderroles) //Capture the first key for the else clause
         {
            if($sort['leader']==SORT_ASC)
            {
               $dir=' asc';
            }
            else
            {
               $dir=' desc';
            }
            $sql.=" order by coalesce(su.lastname,'ZZZZZZZZ') $dir, su.firstname $dir";
         }
         else
         {
            if($sort[$coll]==SORT_ASC)
            {
               $dir=' asc';
            }
            else
            {
               $dir=' desc';
            }
            $sql.=" order by $coll $dir";
         }
      }

      $this->params['from1']=$this->filters->fromfilter;
      $this->params['from2']=$this->filters->fromfilter;
      $this->params['to1']=$this->filters->tofilter;
      $this->params['to2']=$this->filters->tofilter;

      $this->sql=$sql;
   }

   protected function setColumnStyles()
   {
      parent::setColumnStyles();
      $this->table->column_style_all('text-align','left');
      $this->table->column_style('date','text-align','right');
      $this->table->column_style('coursename','padding-left','0');
   }

   protected function preprocessExport($rowdata)
   {
      $rowdata->date=userdate($rowdata->date,static::timeformat());

      if($rowdata->uid) //leader defined
      {
         $rowdata->leader="$rowdata->lastname $rowdata->firstname";
      }

      return $rowdata;
   }

   protected function preprocessShow($rowdata)
   {
      global $CFG;
      $rowdata->date=userdate($rowdata->date,static::timeformat());

      if($rowdata->uid) //leader defined
      {
         $rowdata->leader="$rowdata->firstname $rowdata->lastname";
	 block_reportsdash::wrap($rowdata->leader,"$CFG->wwwroot/user/view.php?id=$rowdata->uid");
      }

      block_reportsdash::wrap($rowdata->coursename,"$CFG->wwwroot/course/view.php?id=$rowdata->id");
      block_reportsdash::wrap($rowdata->parent,"$CFG->wwwroot/course/category.php?id=$rowdata->parentid");

      $tip=static::full_path($this->mydb,$rowdata->id,false);
      $rowdata->coursename=html_writer::tag('span',$rowdata->coursename,array('title'=>$tip));

      return $rowdata;
   }

  function get_filter_form()
   {
      $this->filterform=new block_reportsdash_report_filter_form(null, array('rptname'=>$this->reportname(),
									     'notopencourses'=>true),
								 '','',array('id'=>'rptdashfilter'));
      return $this->filterform;
   }


    static function get_report_category(){
        return 'coursesetup';
    }
}
