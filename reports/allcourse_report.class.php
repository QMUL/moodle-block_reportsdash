<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");


class block_reportsdash_allcourse_report extends block_reportsdash_report
{
    static function nevershow()
    {
        return true;
    }

    static function services()
    {
        return array('reportsdash_course_entries'=>'Returns courses entries');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('courseid',PARAM_TEXT,"Course Id"),
            new Reportdash_field_info('parent_course',PARAM_TEXT,'Parent Course'),
            new Reportdash_field_info('child_course',PARAM_TEXT,'Child Course'),
            new Reportdash_field_info('parent_category',PARAM_TEXT,'Parent Category'),
            new Reportdash_field_info('child_category',PARAM_TEXT,'child_Category')),

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

        $coursename = (!empty($f->coursefilter)? "AND c.shortname='$f->coursefilter'": '');
        $levelfilter = (!empty($f->levelfilter)&& ($f->levelfilter !=-1) ? "AND cc.id =$f->levelfilter": '');
        $tlcsql="substring_index(substring_index(cc.path, '/', 2),'/',-1)";


        //use the log manager class
        $manager = get_log_manager();

        $selectreaders = $manager->get_readers('\core\log\sql_internal_reader');

        if ($selectreaders) {

            $reader     =   $selectreaders['logstore_standard'];

            if ($reader->is_logging())   {

                $tablename  =   $reader->get_internal_log_table_name();

                //Courses which were made visible OR created in the given period.

                $sql="SELECT DISTINCT e.courseid, e.courseid AS parent_course ,
                             e.customint1 AS child_course, cc.id AS parent_category,
                             (cc.parent) AS child_category
                      FROM {$pfx}course c
                      JOIN {$pfx}enrol e ON e.courseid = c.id
                      JOIN {$pfx}course_categories cc ON cc.id = c.category
                      JOIN {$pfx}block_reportsdash_regcats rc on rc.cid = $tlcsql
                      JOIN {$pfx}block_reportsdash_region r on (r.id=rc.rid and r.visible=1)
                      JOIN {$pfx}{$tablename} l  ON l.courseid = c.id
                      WHERE target = 'course' $coursename $levelfilter";


                $this->sql=$sql;

            } else {
                // The {log} table is kept for storing the old logs only. New events are not written to it and must be taken from another log storage.
            }

        } else {
            // You are probably developing for 2.10 and table {log} does not exist any more. Or administrator uninstalled the plugin
        }
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
