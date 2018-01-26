<?php

/**
<<<<<<< HEAD
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_bar.class.php");

class block_reportsdash_resourcetypes_report extends block_reportsdash_withmaterials {
    /**
     *
     */
    function __construct() {
        parent::__construct(array('subject',
                                  'resourcetypes',
                                  'startdate'),
                            true);
    }

    /**
     * @return array
     */
    protected function getClassifications() {
        /**
         * @var array $resources
         */
        extract(static::classify_activities_resources());
        return $resources;
    }

    /**
     * @param $columns
     */
    function setColumns($columns,$headers=false) {
        parent::setColumns($columns);

        $this->table->setup();
    }

    /**
     * @return string
     */
    static function get_report_category() {
        return 'coursecontent';
    }

    /**
     * @param bool $usesort
     */
    public function setSql($usesort = true) {

        $filters = $this->filters;


        $from = min($filters->tofilter,$filters->fromfilter);
        $to = max($filters->tofilter,$filters->fromfilter);

        $from--;
        $to++;

        $this->params['from']=$from;
        $this->params['to']=$to;
        $order_by = "";

        if (!empty($filters->opencourses)) {
            $filtersql = " AND ((c.startdate>:from and c.startdate<:to) or c.startdate=0)";
        } else {
            $filtersql = " AND (c.startdate>:from and c.startdate<:to)";
        }


        $columns = array('subject',
                         'startdate');
        $select = "SELECT * FROM (SELECT c.fullname AS subject,
                          c.id as cid,
                          c.startdate,
                          substring_index(substring_index(cc.path, '/', 2),'/',-1) as tlcat,
                          cc.path,
                          r.id as rid,
        ";
        $from = "     FROM {course} c
                LEFT JOIN {course_modules} cm
                        ON cm.course = c.id
                LEFT JOIN {modules} m
                        ON cm.module = m.id
                JOIN {course_categories} cc ON c.category = cc.id
                JOIN {block_reportsdash_regcats} rc on rc.cid=substring_index(substring_index(cc.path, '/', 2),'/',-1)
                JOIN {block_reportsdash_region} r on r.id=rc.rid
                ";
        $where = "WHERE c.id > 1";
        $this->sql = "";
        $select_bits = array();
        foreach ($this->resources() as $name) {
            $columns[] = "{$name}";
            $select_bits[] = "SUM(CASE WHEN m.name = '{$name}' THEN 1 ELSE 0 END) AS {$name}";
        }
        $select .= implode(', ', $select_bits);
        $select .= ", SUM(CASE WHEN m.name IN({$this->resource_names_with_single_quotes()}) THEN 1 ELSE 0 END) AS total";
        $columns[] = 'total';

        if ($this->course_id()) {
            $where .= " AND cm.course = :courseid";
            $this->params['courseid'] = $this->course_id();
        }


        $sqllevel = '';
        if (!empty($filters->levelfilter)) {
            if ($filters->levelfilter > 0) {
                $filtercat = $this->mydb->get_record('course_categories', array('id' => $filters->levelfilter));

                if ($filtercat->depth == 1) {
                    $sqllevel = " having tlcat=$filters->levelfilter";
                } else {
                    $bits = explode('/', $filtercat->path);
                    $tlc = $bits[1];
                    $sqllevel= " having tlcat=$tlc and (path like('%/$filters->levelfilter/%') or path like('%/$filters->levelfilter')) ";
                }
                $this->params['levelfilter'] = $filters->levelfilter;
            } else {
                $sqllevel = " having rid=" . -$filters->levelfilter;
                $this->params['levelfilter'] = -$filters->levelfilter;
            }
        }

        $this->setColumns($columns);

        if ($usesort and $this->sort()) {
            $order_by = " order by " . $this->sort();
        }
        $this->sql = "{$select} {$from} {$where} {$filtersql} GROUP BY c.id) a {$sqllevel} {$order_by}";

    }

    function reportGraph($dataStart=0,$dataEnd=0)  {


        $dataStart  =   0;
        $dataEnd    =   $this->records;

        $this->chart    =   new block_reportdash_graph_bar();


        //I have had to put the following lines into a loop and call the getData function a number of times.
        //This is because the moodle_database class does not support rewind/reset...pity me I no I shall pay
        //for this somewhere down the line

        // retrieve columns dynamically
        $columns = $this->columns;
        $dbColumns    =   array();
        $colcount = 0;
        foreach($columns as $column){
            if ($column != 'subject' && $column !='cid' && $column != 'startdate' && $column != 'total') { // don't include these 4 columns
                $dbColumns[$column] = $column;
                $colcount ++;
            }
        }

        $this->getData();
        $columns	=   array();
        $colnames	=	array();
        $coldesc	=	array();

        foreach($dbColumns    as $n => $v)   {
            $v = ucfirst($v);
			array_push($coldesc,"{$n}data");
			$colnames[$n] = $v;
			array_push($columns,$n);
        }
		
        $this->chart->getReportData($this->data,$dataStart,$dataEnd);
        $this->chart->addGraphPoints($columns,$colnames,$coldesc,true,false,false,true);


        $width = 60 * $colcount;
        $this->chart->setGraphPosition(150, 45, $width, 500);
        $this->chart->setLegend(20,20,false);
        $this->chart->createGraph();
        $this->chart->displayGraph();
        $this->chart->createImageMap();

    }

}