<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");


class block_reportsdash_unmarkedassignment_report extends block_reportsdash_report
{
    static function nevershow()
    {
        return true;
    }

    static function services()
    {
        return array('reportsdash_unmarked_assignments'=>'Returns Unmarked assignments');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('coursename',PARAM_TEXT,"Course name"),
            new Reportdash_field_info('parent',PARAM_TEXT,'Parent category name'),
            new Reportdash_field_info('name',PARAM_TEXT,'Parent category name'),
            new Reportdash_field_info('total',PARAM_INT,'Number of entries')),

            'webservice'=>array(new Reportdash_field_info('cid',PARAM_INT,"Course id"),
                new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category")),

            'exports'=>array(new Reportdash_field_info('cid',PARAM_INT,'Course id'),
                new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category")),

            'inputs'=>array(new Reportdash_field_info('fromfilter',PARAM_INT,'Unix timestamp, start of report period',$defaulttime),
                new Reportdash_field_info('tofilter',PARAM_INT,'Unix timestamp, end of report period',time())));
    }

    protected $target_criteria;
    function __construct()
    {
        parent::__construct(static::column_names(),false);
    }

    protected function setSql($usesort=true)
    {
        global $CFG;

        $pfx=$CFG->prefix;

        $f=$this->filters;

        $coursename = (!empty($f->coursefilter)? "and c.shortname='$f->coursefilter'": '');
        $levelfilter = (!empty($f->levelfilter)&& ($f->levelfilter !=-1) ? "and cc.id ='$f->levelfilter'": '');
        $time_now = time();
        if($f->duefilter == 0){
            $duedate = 'a.duedate';
        } else if ($f->duefilter == 1){
            $duedate = 'a.duedate  + 1 * 7 * 24 * 60 * 60'  ;
        } else if ($f->duefilter == 2){
            $duedate = 'a.duedate + 2 * 7 * 24 * 60 * 60' ;
        }
        $tlcsql="substring_index(substring_index(cc.path, '/', 2),'/',-1)";
        //Courses which were made visible OR created in the given period.
        $sql="   SELECT  c.fullname AS coursename,
                   cc.name AS parent, a.name,COUNT(s.assignment) as total, a.duedate  FROM mdl_assign  a
                                              LEFT JOIN  mdl_assign_submission s ON
					                              s.assignment = a.id
                                              LEFT JOIN mdl_assign_grades g ON
                                                  s.userid = g.userid AND
                                                  s.assignment = g.assignment
                                              LEFT JOIN mdl_course c ON
                                                  c.id = a.course
                                              LEFT JOIN mdl_course_categories cc ON
                                                  cc.id = c.category
                                              WHERE ( g.timemodified IS NULL OR
                                                  s.timemodified > g.timemodified ) AND
                                                  s.timemodified IS NOT NULL
                                                  AND $duedate < $time_now
                                                   $coursename
                                                   $levelfilter
                                                   group by a.id";

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



        $this->sql=$sql;
    }


    protected function setColumnStyles()
    {
        parent::setColumnStyles();
        $this->table->column_style_all('text-align','left');
        $this->table->column_style('date','text-align','right');
        $this->table->column_style('coursename','padding-left','0');
    }


    protected function preprocessShow($rowdata)
    {
        global $CFG;
        // $rowdata->date=userdate($rowdata->date,static::timeformat());
      //  block_reportsdash::wrap($rowdata->coursename,"$CFG->wwwroot/course/view.php?id=$rowdata->id");
      //  block_reportsdash::wrap($rowdata->parent,"$CFG->wwwroot/course/category.php?id=$rowdata->parentid");
       // block_reportsdash::wrap($rowdata->entries,"$CFG->wwwroot/blocks/ilp/actions/view_studentlist.php?tutor=0&course_id=$rowdata->id");
     //   $tip=static::full_path($this->mydb,$rowdata->id,false);
       // $rowdata->coursename=html_writer::tag('span',$rowdata->coursename,array('title'=>$tip));

        return $rowdata;
    }
    function get_filter_form()
    {
        $this->filterform= new block_reportsdash_unmarkedassignment_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'userspecific';
    }

}
