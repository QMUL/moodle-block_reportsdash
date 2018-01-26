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
class block_reportsdash_activeusers_report extends block_reportsdash_report
{
    static function nevershow()
    {
        return true;
    }

    static function services()
    {
        return array('reportsdash_user_activities'=>'Returns Activities');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime=floor((time()-60*60*24*7*4)/86400)*86400;
        return (object)array('outputs'=>array(new Reportdash_field_info('logdate',PARAM_TEXT,'Date'),
            new Reportdash_field_info('unique_login',PARAM_INT,'Unique Login'),
            new Reportdash_field_info('max_user_name',PARAM_INT,'Max User Name'),
            new Reportdash_field_info('activity',PARAM_INT,'Activity')),

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
        $this->params['from1']=$f->fromfilter;
        $this->params['from2']=$f->fromfilter;
        $this->params['to1']=$f->tofilter;
        $this->params['to2']=$f->tofilter;
        $studentname = (!empty($f->participantfilter)? "and l.userid='$f->participantfilter'": '');


        //use the log manager class
        $manager = get_log_manager();

        $selectreaders = $manager->get_readers('\core\log\sql_internal_reader');

        if ($selectreaders) {

            $reader     =   $selectreaders['logstore_standard'];

            if ($reader->is_logging())   {

                $tablename  =   $reader->get_internal_log_table_name();

                ///Mysql-only REPLACE. If we want to be standard and use INSERT then
                ///we need to watch out for the effect of rounding the time down to days
                ///as this can cause duplicates with INSERT, which causes problems
                ///with the UNIQUE index on newtime,userid,course

                $sql="    SELECT FROM_UNIXTIME(l.timecreated,'%Y-%m-%d, %a ') AS logdate,
                  MAX(CONCAT(u.firstname,' ',u.lastname))  AS max_user_name,
                  COUNT(DISTINCT userid)  AS unique_login, COUNT(*) AS activity
                  FROM {$pfx}{$tablename} l
                  JOIN mdl_user u ON u.id = l.userid
                  WHERE (l.timecreated > :from1 and l.timecreated < :to1)
                  $studentname
                  GROUP BY logdate  DESC ";

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

        $this->filterform= new block_reportsdash_activeusers_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'userspecific';
    }

}
