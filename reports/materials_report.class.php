<?php

/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_bar.class.php");

class block_reportsdash_materials_report extends block_reportsdash_withmaterials {

    /**
     * @return array
     */
    static function services() {
        return array('reportsdash_course_materials' => "Report on courses' activitues and resources");
    }

    /**
     * @return object
     */
    protected static function fields() {
        $defaulttime = floor((time() - 60 * 60 * 24 * 7) / 86400) * 86400;
        return (object)array('outputs' => array(new Reportdash_field_info('subject', PARAM_TEXT, "Course name"),
                                                new Reportdash_field_info('startdate',
                                                                          PARAM_INT,
                                                                          "Time course is due to start"),
                                                new Reportdash_field_info('atotal',
                                                                          PARAM_INT,
                                                                          "Number of activities set up on course"),
                                                new Reportdash_field_info('rtotal',
                                                                          PARAM_INT,
                                                                          "Number of resources set up on course"),
                                                new Reportdash_field_info('total',
                                                                          PARAM_INT,
                                                                          "Number of activities and resources set up on course")),
                             'webservice' => array(new Reportdash_field_info('cid', PARAM_TEXT, "Course id")),
                             'inputs' => array(new Reportdash_field_info('fromfilter',
                                                                         PARAM_INT,
                                                                         'Unix timestamp, show courses starting from this date',
                                                                         $defaulttime),
                                               new Reportdash_field_info('tofilter',
                                                                         PARAM_INT,
                                                                         'Unix timestamp, show courses starting up until this date',
                                                                         time()),
                                               new Reportdash_field_info('opencourses',
                                                                         PARAM_BOOL,
                                                                         '1=Include courses with no start date; 0= do not',
                                                                         0)
                             ));
    }


    // Instance
    // Cached info used for cross linking.

    /**
     * @var
     */
    protected $otherreports;

    /**
     * @var bool
     */
    protected $viewactivityreport;

    /**
     * @var bool
     */
    protected $viewresourcereport;

    /**
     * @throws Exception
     * @throws dml_exception
     */
    function __construct() {
        global $USER;
        parent::__construct(static::column_names(), false);

        $this->viewactivityreport = block_reportsdash_activitytypes_report::can_view_report($USER->id);
        $this->viewresourcereport = block_reportsdash_resourcetypes_report::can_view_report($USER->id);
    }

    /**
     * @return array
     */
    protected function getClassifications() {
        return static::classify_activities_resources();
    }

    /**
     * @param bool $usesort
     */
    function setSql($usesort = true) {
        $this->setColumns(array(
                              'subject',
                              'startdate',
                              'atotal',
                              'rtotal',
                              'total',
                          ));
        $this->sql = "
           SELECT * FROM (SELECT c.fullname AS subject,
                   c.startdate,
                   c.id AS cid,
                   substring_index(substring_index(cc.path, '/', 2),'/',-1) as tlcat,
                   cc.path,
                   r.id as rid,
                   COALESCE(activities.itemcount, 0) AS atotal,
                   COALESCE(resources.itemcount, 0) AS rtotal,
                   COALESCE(activities.itemcount, 0) + COALESCE(resources.itemcount, 0) AS total
              FROM {course} c
              LEFT JOIN ({$this->activities_sub_table_sql()}) AS activities
              ON c.id = activities.course
              LEFT JOIN ({$this->resources_sub_table_sql()}) AS resources
              ON c.id = resources.course
              JOIN {course_categories} cc ON c.category = cc.id
              JOIN {block_reportsdash_regcats} rc on rc.cid=substring_index(substring_index(cc.path, '/', 2),'/',-1)
              JOIN {block_reportsdash_region} r on r.id=rc.rid
              WHERE c.id > 1
        ";

        $this->set_sql_from_and_to_dates();
        $this->add_sql_filters();

        if ($usesort and $this->sort()) {
            $this->add_sql_sort();
        }
    }

    protected function setColumnStyles() {
        parent::setColumnStyles();
        $this->table->column_style('subject', 'text-align', 'left');
    }

    /**
     * @return string
     * @throws coding_exception
     */
    protected function exportname() {
        return $this->reportname . '_' . userdate($this->filters->fromfilter,
                                                  get_string('strftimedaydate', 'langconfig')) . ' - ' .
        userdate($this->filters->tofilter, get_string('strftimedaydate', 'langconfig'));
    }

    /**
     * @param $rowdata
     * @return mixed
     */
    protected function preprocessExport($rowdata) {
        if ($rowdata->startdate ) {
            $rowdata->startdate = userdate($rowdata->startdate, static::timeformat());
        } else {
            $rowdata->startdate = '-';
        }
        return $rowdata;
    }

    /**
     * @param $rowdata
     * @return mixed
     */
    protected function preprocessShow($rowdata) {
        global $CFG;

        if ($rowdata->startdate) {
            $rowdata->startdate = userdate($rowdata->startdate, static::timeformat());
        } else {
            $rowdata->startdate = '-';
        }


        if ($this->viewactivityreport) {
            block_reportsdash::wrap($rowdata->atotal,
                                    "$CFG->wwwroot/blocks/reportsdash/report.php?rptname=activitytypes_report&singlemode=1&courseid=$rowdata->cid");
        }

        if ($this->viewresourcereport) {
            block_reportsdash::wrap($rowdata->rtotal,
                                    "$CFG->wwwroot/blocks/reportsdash/report.php?rptname=resourcetypes_report&singlemode=1&courseid=$rowdata->cid");
        }

        $tip = static::full_path($this->mydb, $rowdata->cid, false);
        $rowdata->subject = html_writer::tag('span', $rowdata->subject, array('title' => $tip));

        block_reportsdash::wrap($rowdata->subject, "$CFG->wwwroot/course/view.php?id=$rowdata->cid");

        return $rowdata;
    }

    /**
     * @return string
     */
    static function get_report_category() {
        return 'coursecontent';
    }


    /**
     */
    private function set_sql_from_and_to_dates() {
        $from = min($this->filters->tofilter, $this->filters->fromfilter);
        $to = max($this->filters->tofilter, $this->filters->fromfilter);
        $from--;
        $to++;
        if ($from and $to) {
            $this->params['from'] = $from;
            $this->params['to'] = $to;
            if (!empty($this->filters->opencourses)) {
                $this->sql .= " AND ((c.startdate>:from and c.startdate<:to) or c.startdate=0)";
            } else {
                $this->sql .= " AND (c.startdate>:from and c.startdate<:to)";
            }
        }
    }

    /**
     *
     */
    private function add_sql_filters() {
        $filters = $this->filters;
        $this->sql .= ' )a';
        if (!empty($filters->levelfilter)) {
            if ($filters->levelfilter > 0) {
                $filtercat = $this->mydb->get_record('course_categories', array('id' => $filters->levelfilter));
                if ($filtercat->depth == 1) {
                    $this->sql .= " having tlcat=$filters->levelfilter";
                } else {
                    $bits = explode('/', $filtercat->path);
                    $tlc = $bits[1];
                    $this->sql .= " having tlcat=$tlc and (path like('%/$filters->levelfilter/%') or path like('%/$filters->levelfilter')) ";
                }
                $this->params['levelfilter'] = $filters->levelfilter;
            } else {
                $this->sql .= " having rid=" . -$filters->levelfilter;
                $this->params['levelfilter'] = -$filters->levelfilter;
            }
        }
    }

    /**
     * @return string
     */
    private function activities_sub_table_sql() {
        return "
                  SELECT COUNT(cma.id) as itemcount,
                         cma.course
                    FROM {course_modules} cma
              INNER JOIN {modules} cmam
                      ON cma.module = cmam.id
               LEFT JOIN {forum} f
                      ON f.id = cma.instance
                     AND f.type = 'news'
                     AND cmam.name = 'forum'
                   WHERE cmam.name IN({$this->activity_names_with_single_quotes()})
                     AND f.id IS NULL
                GROUP BY cma.course
        ";
    }

    /**
     * @return string
     */
    private function resources_sub_table_sql() {
        return "
                  SELECT COUNT(cma.id) as itemcount,
                         cma.course
                    FROM {course_modules} cma
              INNER JOIN {modules} cmam
                      ON cma.module = cmam.id
                   WHERE cmam.name IN({$this->resource_names_with_single_quotes()})
                GROUP BY cma.course
        ";
    }

    private function add_sql_sort() {
        $this->sql .= ' ORDER BY ' . $this->sort();
    }

    /**
     * @return string "'link', 'page', etc..."
     */
    protected function activity_names_with_single_quotes() {
        /**
         * @var array $activities
         */
        extract(static::classify_activities_resources());
        return implode(', ', $activities);
    }

    /**
     * @return string "'link', 'page', etc..."
     */
    protected function resource_names_with_single_quotes() {
        /**
         * @var array $resources
         */
        extract(static::classify_activities_resources());
        return implode(', ', $resources);
    }

    function reportGraph($dataStart=0,$dataEnd=0)  {

        $dataStart  =   0;
        $dataEnd    =   $this->records;

        $this->chart    =   new block_reportdash_graph_bar();

        //I have had to put the following lines into a loop and called the getData function a number of times.
        //This is because the moodle_database class does not support rewind/reset...pity me I no I shall pay
        //for this somewhere down the line

        echo $this->display_graph_label();
        $dbColumns    =   array('rtotal'=>'Resources','atotal'=>'Activities');

        foreach($dbColumns    as $n => $v)   {
            $this->getData();
            $this->chart->getReportData($this->data,$dataStart,$dataEnd);
            $this->chart->addGraphPoints(array($n),$v,"{$n}data",true);
        }

        //displays the word total in front of the material name
        $this->chart->setToolTipTitleMask('Total %s');
        $this->chart->setToolTipDescMask('%s');

        $this->chart->setGraphPosition(150, 45, 400, 500);
        $this->chart->setLegend(20,20,false);
        $this->chart->createGraph();
        $this->chart->displayGraph();
        $this->chart->createImageMap();
        
    }
}