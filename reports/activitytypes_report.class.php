<?php

global $CFG;
require_once($CFG->dirroot."/blocks/reportsdash/classes/graph_bar.class.php");
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_reportsdash_activitytypes_report extends block_reportsdash_withmaterials {
    /**
     *
     */
    function __construct() {
        parent::__construct(array('subject',
                                  'activitytypes',
                                  'startdate'),
                            true);
    }

    /**
     * @return array
     */
    protected function getClassifications() {
        /**
         * @var array $activities
         */
        extract(static::classify_activities_resources());
        return $activities;
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
     * @return block_reportsdash_activitytypes_filter_form
     */
    function get_filter_form()
    {
        $this->filterform=new block_reportsdash_activitytypes_filter_form(null, array('rptname'=>$this->reportname(),
                                                                          'db'=>$this->mydb),
                                                                          '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }


    /**
     * @param bool $usesort
     */
    public function setSql($usesort = true) {

        global $DB;
        $filters = $this->filters;
        $from = min($filters->tofilter,$filters->fromfilter);
        $to = max($filters->tofilter,$filters->fromfilter);
        $order_by = "";

        $from--;
        $to++;

        $this->params['from']=$from;
        $this->params['to']=$to;

        if (!empty($filters->opencourses)) {
            $filtersql = " AND ((c.startdate>:from and c.startdate<:to) or c.startdate=0)";
        } else {
            $filtersql = " AND (c.startdate>:from and c.startdate<:to)";
        }

        $activityfilter = "";

        if (empty($filters->activitytypesfilter)){
            $activities = $DB->get_records('modules');
            foreach ($activities as $activity){
                $activityfilter .= $activity->id.',';
            }
        } else {
            $activities = $filters->activitytypesfilter;
            foreach ($activities as $activity=>$id){
                $activityfilter .= $id.',';
          }
      }

         //remove last comma
         $activityfilter = rtrim($activityfilter,',');


        $columns = array('subject',
                         'startdate');
        $select = "SELECT * FROM (SELECT c.fullname AS subject,
                          c.id as cid,
                          c.startdate,
                          substring_index(substring_index(cc.path, '/', 2),'/',-1) as tlcat,
                          cc.path,
                          r.id as rid,
        ";
        $from = "    FROM {course} c
                LEFT JOIN {course_modules} cm
                       ON cm.course = c.id
                LEFT JOIN {modules} m
                       ON cm.module = m.id
                LEFT JOIN {forum} f
                       ON f.id = cm.instance
                      AND f.type = 'news'
                      AND m.name = 'forum'
                JOIN {course_categories} cc ON c.category = cc.id
                JOIN {block_reportsdash_regcats} rc on rc.cid=substring_index(substring_index(cc.path, '/', 2),'/',-1)
                JOIN {block_reportsdash_region} r on r.id=rc.rid
                ";

        $where = 'WHERE c.id > 1 AND m.id IN('. $activityfilter.')';
        $this->sql = "";
        $select_bits = array();
        foreach ($this->get_selected_activities($activityfilter) as $activity) {
            $columns[] = "{$activity->name}";
            $select_bits[] = "sum(case when m.name = '{$activity->name}' AND (m.name != 'forum' OR f.id IS NULL) then 1 else 0 end) as {$activity->name}";
        }
        $select .= implode(', ', $select_bits);
        $select .= ", sum(case when m.name IN({$this->activity_names($activityfilter)})  AND (m.name != 'forum' OR f.id IS NULL) then 1 else 0 end) as total";
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
        $this->sql = "{$select} {$from} {$where} {$filtersql} GROUP BY c.id ) a {$sqllevel} {$order_by}";

    }

    /**
     * @param $selected
     * @return string
     */
    public function activity_names($selected){
        $activities = $this->get_selected_activities($selected);
        $activitiesnames ='';
        foreach ($activities as $activity){
            $activitiesnames .= "'".$activity->name."'".',';
        }
        return $activitiesnames = rtrim($activitiesnames,',');
    }

    /**
     * @param $selected
     * @return array
     */
    public function get_selected_activities($selected){
        global $DB;

        $sql = "SELECT * FROM {modules}
               WHERE id IN($selected)";

        return $DB->get_records_sql($sql);
    }

    function reportGraph($dataStart=0,$dataEnd=0)  {

        $dataStart  =   0;
        $dataEnd    =   $this->records;
        echo $this->display_graph_label();

        $totalValues  =   0;
        $this->chart    =   new block_reportdash_graph_bar();

        //I have had to put the following lines into a loop and called the getData function a number of times.
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
        $col	    =   array();
        $colnames	=	array();
        $coldesc	=	array();

        foreach($dbColumns    as $n => $v)   {
            $v = ucfirst($v);
            array_push($coldesc,"{$n}data");
            $colnames[$n] = $v;
            array_push($col,$n);
        }

        $this->chart->getReportData($this->data,$dataStart,$dataEnd);
        $this->chart->addGraphPoints($col,$colnames,$coldesc,true,false,false,true);

        $width = 45 * $colcount;
        $width = ($colcount<10)? $width + 450 : $width;


        $fontsize   =    ($colcount<10) ? 10  : 20  ;
		
		//increase the height of the graph to accommodate more labels in the legend
		$height 	=	count($colnames)	* 26;
		if ($height < 500)	$height = 500;
		if ($height > 1500)	$height = 1500;

        $this->chart->setGraphPosition(250, 45, $width, $height, $fontsize);

        $this->chart->setLegend(20,20,false);


        $this->chart->createGraph();
        $this->chart->displayGraph();
        $this->chart->createImageMap();

    }



}