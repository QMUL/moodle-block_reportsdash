<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");


class block_reportsdash_courseactivity_report extends block_reportsdash_report
{
    static function services()
    {
        return array('reportsdash_course_activity'=>"Returns courses' activity as views");
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('activities',PARAM_TEXT,"Module Names"),
                                              new Reportdash_field_info('total',PARAM_TEXT,'Total')),

                             'webservice'=>array(new Reportdash_field_info('cid',PARAM_INT,"Course id"),
                                                 new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category")),

                             'exports'=>array(new Reportdash_field_info('cid',PARAM_INT,'Course id'),
                                              new Reportdash_field_info('parentid',PARAM_INT,"ID of parent category")));
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

        $order_by = "";
        $coursename = (!empty($f->coursefilter)? "c.shortname='$f->coursefilter'": '');
        $levelfilter = (!empty($f->levelfilter)&& ($f->levelfilter !=-1) ? " cc.id =$f->levelfilter": '');
        $where = (!empty($coursename) || !empty($levelfilter) ? "WHERE": "" );
        $and = (!empty($coursename) && !empty($levelfilter) ? "AND": "" );
        $tlcsql="substring_index(substring_index(cc.path, '/', 2),'/',-1)";
        //Courses which were made visible OR created in the given period.


        if ($usesort and $this->sort()) {
            $order_by = " order by " . $this->sort();
        }

        $sql="  SELECT  DISTINCT COUNT(m.id) AS Total , m.name as activities
                   FROM {$pfx}modules m
                   JOIN {$pfx}course_modules cm ON cm.module = m.id
                   JOIN {$pfx}course c ON c.id = cm.course
                   JOIN {$pfx}course_categories cc on cc.id=c.category
                   JOIN {$pfx}block_reportsdash_regcats rc on rc.cid = $tlcsql
                   JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid and r.visible=1)
                   $where
                   $coursename $and
                   $levelfilter
                   GROUP BY m.id
                   $order_by";


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


        return $rowdata;
    }
    function get_filter_form()
    {
        $this->filterform= new block_reportsdash_courseactivity_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'courseuse';
    }

}
