<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");

global $PAGE;
class block_reportsdash_activityview_report extends block_reportsdash_report
{
    static function nevershow()
    {
        return true;
    }

    static function services()
    {
        return array('reportsdash_view_activities'=>'Returns Activities');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('time',PARAM_TEXT,'Parent category name'),
            new Reportdash_field_info('ip',PARAM_INT,'IP address'),
            new Reportdash_field_info('fullname',PARAM_INT,'Fullname'),
            new Reportdash_field_info('action',PARAM_INT,'Action'),
            new Reportdash_field_info('target',PARAM_INT,'target')),

            'webservice'=>array(new Reportdash_field_info('cid',PARAM_INT,"Course id")),

            'exports'=>array(new Reportdash_field_info('cid',PARAM_INT,'Course id')),

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

        $coursename = (!empty($f->coursefilter)? "and c.id= $f->coursefilter and c.id IS NOT NULL": '');
        // get_coursename($coursename);
        if(!empty($_GET["targetfilter"])){
            $target = $_GET['targetfilter'];
        }

        $target = (!empty($target)? "and l.target = '$target'": '');

        $participants = (!empty($f->participantfilter)? "and u.id = $f->participantfilter ": '');
        $actions = (!empty($f->actionfilter)? "and l.action ='$f->actionfilter'": '');
        $roles = (!empty($f->rolefilter)? "and r.shortname ='$f->rolefilter'": '');

        //use the log manager class
        $manager = get_log_manager();

        $selectreaders = $manager->get_readers('\core\log\sql_internal_reader');

        if ($selectreaders) {

            $reader     =   $selectreaders['logstore_standard'];

            if ($reader->is_logging())   {

                $tablename  =   $reader->get_internal_log_table_name();

                // Courses which were made visible OR created in the given period.
                $sql="  SELECT FROM_UNIXTIME(l.timecreated,'%Y-%m-%d, %a ') as time, l.ip as ip, l.action as action, l.target,
                        CONCAT(u.firstname,' ', u.lastname) as fullname
                   FROM {$pfx}{$tablename} l
                   LEFT JOIN {$pfx}course c on l.courseid=c.id
                   JOIN {$pfx}context ct ON ct.instanceid = c.id
                   JOIN {$pfx}role_assignments ra ON ra.contextid = ct.id
                   JOIN {$pfx}role r ON r.id = ra.roleid
                   JOIN {$pfx}user u ON u.id = l.userid
                   WHERE ((l.timecreated > :from1 and l.timecreated < :to1) or
                   l.id is null and (c.timecreated > :from2 and c.timecreated < :to2))
                   $coursename
                   $participants
                   $actions
                   $roles
                   $target
                   AND ct.contextlevel = 50
                   group by l.id";

                $this->params['from1']=$f->fromfilter;
                $this->params['from2']=$f->fromfilter;
                $this->params['to1']=$f->tofilter;
                $this->params['to2']=$f->tofilter;

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

        global $PAGE;
        $jsmodule = array(
            'name'          => 'activityview_functions',
            'fullpath'      => "/blocks/reportsdash/reports/activityview_report.js",
            'requires'      => array('event','dom','node','io-form')
        );

        $PAGE->requires->js_init_call('M.activityview_functions.init', null, true, $jsmodule);


        $this->filterform= new block_reportsdash_activityview_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'courseuse';
    }

}
