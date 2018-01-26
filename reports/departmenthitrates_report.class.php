<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_bar.class.php");
require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_pie.class.php");

class block_reportsdash_departmenthitrates_report extends block_reportsdash_report
{
    static function nevershow()
    {
        return true;
    }

    static function services()
    {
        return array('reportsdash_course_hit_rates'=>'Returns course hit rates');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        return (object)array(
            'outputs' => array(
                new Reportdash_field_info('name', PARAM_TEXT, get_string('name', 'block_reportsdash')),
                new Reportdash_field_info('hits', PARAM_TEXT, get_string('hits', 'block_reportsdash')),
            ),
            'inputs' => array(
                new Reportdash_field_info('fromfilter',PARAM_INT, 'Unix timestamp, count access from this date',time()-86400*30),
                new Reportdash_field_info('tofilter',PARAM_INT,'Unix timestamp, count access up until this date',time()),
                new Reportdash_field_info('catid',PARAM_INT,'Category ID'))
            );
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

        $startoftheday = floor(time()/86400)*86400; //calculate start of today
        $endoftheday = ceil(time()/86400)*86400 - 1; //calculate end of today

        $fromfilter = (isset($f->fromfilter)? $f->fromfilter:$startoftheday);
        $tofilter = (isset($f->tofilter))? $f->tofilter+ 86399:$endoftheday;

        // Check for 'All'
        if ($f->levelfilter == 0)
        {
            $this->sql = 'select rgn.id,rgn.name,count(*) as hits
                          from {block_reportsdash_regcats} rdr
                          join {block_reportsdash_region} rgn on rgn.visible=1 and rdr.rid=rgn.id
                          join {course_categories} cc on rdr.cid=cc.id
                          and (cc.path like concat(" %/", rdr.cid, "/%") or cc.id=rdr.cid)
                          join {course} c on c.category=cc.id
                          join {logstore_standard_log} log on log.courseid=c.id
                          where target="course" and action="viewed"
                          AND log.timecreated BETWEEN '.$fromfilter.' AND '.$tofilter.'
                          group by rdr.rid';
        }
        else
        {
            // Is this a department report?
            if ($f->levelfilter < 0)
            {
                $r = -($f->levelfilter);
                $this->sql = '
                     SELECT
                         c.id,c.fullname as name,count(*) as hits
                     FROM
                         {block_reportsdash_regcats} rdr
                             JOIN
                         {course_categories} cc ON (rdr.cid = cc.id OR cc.path LIKE CONCAT("%/", rdr.cid, "/%"))
                             JOIN
                         {course} c ON c.category = cc.id
                             JOIN
                         {logstore_standard_log} log ON log.courseid = c.id
                             AND log.eventname = "\\\\core\\\\event\\\\course_viewed"
                     WHERE
                         rdr.rid = '.$r.'
                         AND log.timecreated BETWEEN '.$fromfilter.' AND '.$tofilter.'
                         GROUP BY c.id
                         ORDER BY count(*) DESC
                     LIMIT 10';
            }
            // This must be a category report then.
            else
            {

                $cat = $f->levelfilter;
                $this->sql = '
                     SELECT
                         c.id,c.fullname as name,count(*) as hits
                     FROM
                         {course_categories} cc
                             JOIN
                         {course} c ON c.category = cc.id
                             JOIN
                         {logstore_standard_log} log ON log.courseid = c.id
                             AND log.eventname = "\\\\core\\\\event\\\\course_viewed"
                     WHERE
                         (cc.id = '.$cat.' OR cc.path LIKE "%/'.$cat.'/%")
                         AND log.timecreated BETWEEN '.$fromfilter.' AND '.$tofilter.'
                         GROUP BY c.id
                         ORDER BY count(*) DESC
                     LIMIT 10';
            }
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

        if($this->filters->levelfilter>0)
        {
            block_reportsdash::wrap($rowdata->name,"$CFG->wwwroot/course/view.php?id=$rowdata->id");
        }
        
        return $rowdata;
    }

    protected function preprocessExport($rowdata)
    {
        return $rowdata;
    }

    function get_filter_form()
    {
        global $PAGE;

        $this->filterform= new block_reportsdash_departmenthitrates_filter_form($PAGE->url, array('rptname'=>$this->reportname(),
                                                                                                  'db'=>$this->mydb, 'notopencourses'=>true),
                                                                                '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

    static function get_report_category(){
        return 'courseuse';
    }

   static function heavy()
   {
      return static::heavydb_active();
   }

    function reportGraph($dataStart = 0, $dataEnd = 0)  {

        $dataEnd = empty($dataEnd)?$this->records:$dataEnd;
        $f=$this->filters;

        $data = array();
        foreach ($this->getData() as $row)
        {
            $data[] = $row;
        }

        // Check for 'All'
        if ($f->levelfilter == 0)
        {
            $this->chart = new block_reportdash_graph_pie();

            $this->chart->getReportData($data, $dataStart, $dataEnd);
            $this->chart->addGraphPoints(array('hits'), 'data1', 'piedata');
            $this->chart->addGraphPoints(array('name'), '', '', false, true);

            $this->chart->setGraphPosition(160, 180, 320, 320);
            $this->chart->createGraph();
            $this->chart->setLegend(20, 20, true);
        }
        else
        {
            $this->chart = new block_reportdash_graph_bar();

            foreach ($data as $row)
            {
                $this->chart->getReportData(array($row), $dataStart, $dataEnd);
                $this->chart->addGraphPoints(array('hits'), $row->name);
            }

            $this->chart->setGraphPosition(0, 0, 640, 320);
            $this->chart->createGraph();
        }

        $this->chart->displayGraph();
        $this->chart->createImageMap();
    }
}
