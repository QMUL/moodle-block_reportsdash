<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");


class block_reportsdash_ilptargetbycourse_report extends block_reportsdash_report
{

    static function nevershow()
    {
        return true;
    }

    protected static $dependencies = array(
        'block_ilp' => 2014070100,
    );

    static function services()
    {
        return array('reportsdash_course_targets'=>'Returns courses targets');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('coursename',PARAM_TEXT,"Course name"),
            new Reportdash_field_info('parent',PARAM_TEXT,'Parent category name'),
            new Reportdash_field_info('targets',PARAM_INT,'Number of targets')),

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
        $target_value = (!empty($f->target_criteria)? "and steent.value ='$f->target_criteria'": '');

        $join_target = (!empty($target_value)? "JOIN {$pfx}block_ilp_plu_ste_ent steent ON ent.id = steent.entry_id
                   JOIN {$pfx}block_ilp_plu_ste_items steitems ON steitems.id = steent.parent_id" :'');

        $order_by = "";
        if ($usesort and $this->sort()) {
            $order_by = " order by " . $this->sort();
        }
        $levelfilter = (!empty($f->levelfilter)&& ($f->levelfilter !=-1) ? "and cc1.id ='$f->levelfilter'": '');
        $tlcsql="substring_index(substring_index(cc1.path, '/', 2),'/',-1)";
//Courses which were made visible OR created in the given period.
        $sql="  SELECT  c.id,fullname AS coursename,
                   cc1.name AS parent, cc1.id AS parentid, COUNT(DISTINCT ue.userid) AS targets,
                   r.id as rid, r.visible as rvis, cc1.path,
                   $tlcsql as tlcat
                   FROM {$pfx}course c
                   LEFT JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                   JOIN {$pfx}block_reportsdash_regcats rc on rc.cid = $tlcsql
                   JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid and r.visible=1)
                   JOIN {$pfx}enrol e ON c.id= e.courseid
                   JOIN {$pfx}user_enrolments ue ON e.id = ue.enrolid
                   JOIN {$pfx}block_ilp_entry ent ON ent.user_id = ue.userid
                   $join_target
                   LEFT JOIN {$pfx}log l on (l.course=c.id and module='course' and action='show')
                   WHERE ((l.time > :from1 and l.time < :to1) or
                   l.id is null and (c.timecreated > :from2 and c.timecreated < :to2))
                   $target_value
                   $coursename
                   $levelfilter
                   GROUP BY c.id,cc1.id
                   $order_by";



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

        $this->params['from1']=$f->fromfilter;
        $this->params['from2']=$f->fromfilter;
        $this->params['to1']=$f->tofilter;
        $this->params['to2']=$f->tofilter;

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

        block_reportsdash::wrap($rowdata->coursename,"$CFG->wwwroot/course/view.php?id=$rowdata->id");
        block_reportsdash::wrap($rowdata->parent,"$CFG->wwwroot/course/category.php?id=$rowdata->parentid");

        $tip=static::full_path($this->mydb,$rowdata->id,false);
        $rowdata->coursename=html_writer::tag('span',$rowdata->coursename,array('title'=>$tip));

        return $rowdata;
    }
    function get_filter_form()
    {
        $this->filterform= new block_reportsdash_targetbycourse_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'ilp';
    }

}
