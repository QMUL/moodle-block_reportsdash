<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/externallib.php");
require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_line.class.php");

class block_reportsdash_activitydeadlines_report extends block_reportsdash_report
{
    static function services()
    {
        return array('reportsdash_activity_deadlines'=>'Returns activity deadlines');
    }

//Instance

//Define the input and output fields for this report, whether webservice or not
//webservice is array of fields only output for the webservice
    protected static function fields()
    {
        $defaulttime = floor((time() - 60 * 60 * 24 * 7) / 86400) * 86400; 
        return (object)array(
            'outputs' => array(
                new Reportdash_field_info('coursename', PARAM_TEXT, get_string('course')),
                new Reportdash_field_info('activityname', PARAM_TEXT, get_string('activity')),
                new Reportdash_field_info('deadline', PARAM_TEXT, get_string('deadline', 'block_reportsdash')),
            ),
            'inputs' => array(new Reportdash_field_info('fromfilter',
                                                        PARAM_INT,
                                                        'Unix timestamp, show courses starting from this date',
                                                        $defaulttime),
                              new Reportdash_field_info('tofilter',
                                                        PARAM_INT,
                                                        'Unix timestamp, show courses starting up until this date',
                                                        time())),
            'outputs'=>array(new Reportdash_field_info('coursename',PARAM_TEXT,'Course name'),
                             new Reportdash_field_info('activityname',PARAM_TEXT,'Activity name'),
                             new Reportdash_field_info('deadline',PARAM_INT,'Deadline')),

            'webservice' => array(new Reportdash_field_info('courseid', PARAM_INT, "Course id")),
        );
    }

    protected $target_criteria;
    function __construct()
    {
        parent::__construct(static::column_names(),false);
    }

    protected function dataSources()
    {
        return array(
            'assign' => array('table' => 'assign', 'field' => 'duedate'),
            'quiz' => array('table' => 'quiz', 'field' => 'timeclose'),
            'workshop' => array('table' => 'workshop', 'field' => 'submissionend'),
        );
    }

    protected function setSql($usesort=true)
    {
        global $CFG;

        $pfx=$CFG->prefix;

        $f=$this->filters;
        $order_by = "";
        $sql = array();
        foreach ($this->dataSources() as $activity => $config)
        {
            $table = $config['table'];
            $field = $config['field'];
            $conditions = array(
                "({$table}.{$field} >= $f->fromfilter AND {$table}.{$field} <= $f->tofilter)"
            );
            if (!empty($f->coursefilter))
            {
                $conditions[] = "c.shortname = '$f->coursefilter'";
            }
            if (!empty($f->levelfilter) && $f->levelfilter != -1)
            {
                $conditions[] = "cc.id =$f->levelfilter";
            }
            $conditions = implode(" AND ", $conditions);

            if ($usesort and $this->sort()) {
                $order_by = " order by " . $this->sort();
            }

            $sql[] = <<<END_SQL
SELECT
    c.id AS courseid,
    c.shortname AS coursename,
    gi.itemname AS activityname,
    gi.itemmodule AS activitytype,
    gi.iteminstance AS activityinstance,
    cm.id AS cmid,
    UNIX_TIMESTAMP(DATE(FROM_UNIXTIME({$table}.{$field}))) AS deadline
FROM
                        {$pfx}course c
                   JOIN {$pfx}grade_items gi ON gi.courseid = c.id AND gi.itemtype = 'mod' AND gi.itemmodule = '$activity'
                   JOIN {$pfx}modules m ON m.name = '$activity'
                   JOIN {$pfx}course_modules cm ON cm.module = m.id AND cm.course = c.id AND cm.instance = gi.iteminstance
                   JOIN {$pfx}{$table} {$table} ON gi.iteminstance = {$table}.id
                   JOIN {$pfx}course_categories cc on cc.id=c.category
WHERE $conditions
END_SQL;
        }
        $this->sql=implode(" UNION ", $sql);
        $this->sql .= "{$order_by}";
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
        $rowdata->coursename = html_writer::link(new moodle_url('/course/view.php', array('id' => $rowdata->courseid)), $rowdata->coursename);
        $rowdata->activityname = html_writer::link(new moodle_url('/mod/'.$rowdata->activitytype.'/view.php', array('id' => $rowdata->cmid)), $rowdata->activityname);
        $rowdata->deadline = strftime("%d/%m/%g", $rowdata->deadline);
        return $rowdata;
    }

    protected function preprocessExport($rowdata)
    {
        $rowdata->deadline = strftime("%d/%m/%g", $rowdata->deadline);
        return $rowdata;
    }

    function get_filter_form()
    {
        global $PAGE;

        $this->filterform= new block_reportsdash_activitydeadlines_filter_form($PAGE->url, array('rptname'=>$this->reportname(),
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
        $this->chart = new block_reportdash_graph_line();

        $innersql = $this->sql;



        if (empty($this->filters->tofilter))   $this->filters->tofilter    =   time();
        if (empty($this->filters->fromfilter)) $this->filters->fromfilter    =   time() - 2592000;

        $count = (($this->filters->tofilter - $this->filters->fromfilter) / (60 * 60 * 24)) + 1;
        if ($count < 45) {
            $interval = "1 day";
        } else if ($count < 365) {
            $interval = "1 week";
        } else if ($count < 732) {
            $interval = "1 month";
        } else {
            $interval = "1 year";
        }

        $date = strtotime('today midnight',$this->filters->fromfilter);

        $sql = "SELECT deadline, activitytype, count(*) AS total FROM ($innersql) rollup GROUP BY deadline,activitytype";
        $rs = $this->mydb->get_recordset_sql($sql);
        $data = array('total' => array());
        foreach ($this->dataSources() as $activitytype => $config)
        {
            $data[$activitytype] = array();
        }
        while ($date <= $this->filters->tofilter)
        {
            $r = $rs->current();
            $total = array('total' => 0);
            foreach ($this->dataSources() as $activitytype => $config)
            {
                $total[$activitytype] = 0;
            }
            $fromdate = $date;
            $nextdate = strtotime($interval, $date);
            while ($date < $nextdate)
            {
                if ($rs->valid() && $r->deadline < $date)
                {
                    $rs->next();
                    $r = $rs->current();
                }
                while ($rs->valid() && $r->deadline == $date)
                {
                    $total[$r->activitytype] += $r->total;
                    $total['total'] += $r->total;
                    $rs->next();
                    $r = $rs->current();
                }
                $date = strtotime("1 day", $date);
            }
            $lastdate = strtotime("-1 day", $date);
            switch ($interval)
            {
            case '1 day':
            case '1 week':
                $label = strftime("%d/%m", $lastdate);
                break;
            case '1 month':
                $label = strftime("%b %g", $lastdate);
                break;
            case '1 year':
                $label = strftime("%G", $lastdate);
            }
            $data['total'][$label] = $total['total'];
            foreach ($this->dataSources() as $activitytype => $config)
            {
                $data[$activitytype][$label] = $total[$activitytype];
            }
        }
        $rs->close();

        $this->chart->chartData->addPoints($data['total'], get_string('total'));
        foreach ($this->dataSources() as $activitytype => $config)
        {
            $this->chart->chartData->addPoints($data[$activitytype], get_string('pluginname', $activitytype));
        }
        $this->chart->chartData->addPoints(array_keys($data[$activitytype]), 'xaxis');
        $this->chart->chartData->setAbscissa('xaxis');

        $width = count($data[$activitytype]) * 60;
        $this->chart->setGraphPosition(45, 45, $width, 500);
        $this->chart->createGraph();
        $this->chart->createImageMap();
        $this->chart->setLegend();
        $this->chart->displayGraph();
    }
}
