<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");


class block_reportsdash_ilptuteesbycourse_report extends block_reportsdash_report
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
        return array('reportsdash_tutees_course_entries'=>'Returns courses entries and tutees');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('coursename',PARAM_TEXT,"Course name"),
            new Reportdash_field_info('parent',PARAM_TEXT,'Parent category name'),
            new Reportdash_field_info('entries',PARAM_INT,'Number of entries'),
            new Reportdash_field_info('tutees',PARAM_INT,'Tutees')),

            'webservice'=>array(new Reportdash_field_info('cid',PARAM_INT,"Course id"),
                new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category"),
                new Reportdash_field_info('userid',PARAM_INT,"ID of parent category")),

            'exports'=>array(new Reportdash_field_info('cid',PARAM_INT,'Course id'),
                new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category"),
                new Reportdash_field_info('userid',PARAM_INT,"ID of parent category")),

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
        $levelfilter = (!empty($f->levelfilter)&& ($f->levelfilter !=-1) ? "and cc1.id ='$f->levelfilter'": '');
        $tuteesfilter = (!empty($f->tuteesfilter)? "and ra.userid = '$f->tuteesfilter'": '');
        $tlcsql="substring_index(substring_index(cc1.path, '/', 2),'/',-1)";
        //Courses which were made visible OR created in the given period.
        $sql="  SELECT   c.id,fullname AS coursename,
                   cc1.name AS parent, cc1.id AS parentid, u.id as userid,
                   COUNT(DISTINCT ent.id) AS entries, CONCAT(u.firstname,' ', u.lastname) as tutees
                   FROM {$pfx}course c
                   LEFT JOIN {$pfx}course_categories cc1 on cc1.id=c.category
                   JOIN {$pfx}block_reportsdash_regcats rc on rc.cid = $tlcsql
                   JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid and r.visible=1)
                   JOIN {$pfx}enrol e ON c.id= e.courseid
                   JOIN {$pfx}user_enrolments ue ON e.id = ue.enrolid
                   JOIN {$pfx}block_ilp_entry ent ON ent.user_id = ue.userid
                   JOIN {$pfx}user u ON u.id = ent.user_id
                   JOIN {$pfx}block_ilp_report rep ON rep.id = ent.report_id
                   JOIN mdl_context ct ON ct.instanceid = c.id AND  ct.contextlevel = 50
                   JOIN mdl_role_assignments ra ON ra.contextid = ct.id
                   JOIN mdl_block_reportsdash_staff rs ON rs.roleid = ra.roleid
                   LEFT JOIN {$pfx}log l on (l.course=c.id )
                   WHERE ((l.time > :from1 and l.time < :to1) or
                   l.id is null and (c.timecreated > :from2 and c.timecreated < :to2))
                   $coursename
                   $levelfilter
                   $tuteesfilter
                 group by u.id,c.id
                   ";

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
        block_reportsdash::wrap($rowdata->entries,"$CFG->wwwroot/blocks/ilp/actions/view_studentlist.php?tutor=0&course_id=$rowdata->id");
        block_reportsdash::wrap($rowdata->tutees,"$CFG->wwwroot/user/profile.php?id=$rowdata->userid");
        $tip=static::full_path($this->mydb,$rowdata->id,false);
        $rowdata->coursename=html_writer::tag('span',$rowdata->coursename,array('title'=>$tip));

        return $rowdata;
    }
    function get_filter_form()
    {
        $this->filterform= new block_reportsdash_tuteesbycourse_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'ilp';
    }

}
