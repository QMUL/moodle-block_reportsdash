<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2015 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */



class block_reportsdash_coursetotalgrades_report extends block_reportsdash_report{


    protected static function fields(){
        return (object)array('outputs'=>array(new Reportdash_field_info('course',PARAM_TEXT,'Course'),
                                              new Reportdash_field_info('firstname',PARAM_TEXT,'Firstname'),
                                              new Reportdash_field_info('lastname',PARAM_TEXT,'Lastname'),
                                              new Reportdash_field_info('emailaddress',PARAM_TEXT,'Email Address'),
                                              new Reportdash_field_info('department',PARAM_TEXT,'Department'),
                                              new Reportdash_field_info('grade',PARAM_INT,'Grade'),
                                              new Reportdash_field_info('lastgraded',PARAM_TEXT,'Last Graded')),
        );
    }

    function __construct(){
        parent::__construct(static::column_names(),false);
    }


    protected function setSql($usesort=true){

       //filters
        $f = $this->filters;

        if(isset($f->departmentfilter)){
            $department = ($f->departmentfilter == 'all')? "":"and u.department='$f->departmentfilter'";
        }else {
            $department = "and u.department=''";
        }

        $userid = (isset($f->userfilter) && !empty($f->userfilter)? "and u.id='$f->userfilter'":'');
        $courseid = (isset($f->coursefilter) && !empty($f->coursefilter)? "and c.id='$f->coursefilter'":'');
        $categoryid =(isset($f->categoryfilter) && !empty($f->categoryfilter)? "and c.category='$f->categoryfilter'":'');
        $gradeto = (isset($f->gradetofilter) && !empty($f->gradetofilter)? "and gg.finalgrade<='$f->gradetofilter'":'');
        $gradefrom = (isset($f->gradefromfilter))? "and gg.finalgrade>='$f->gradefromfilter'":'';

        $startoftheday = floor(time()/86400)*86400; //calculate start of today
        $endoftheday = ceil(time()/86400)*86400 - 1; //calculate end of today

        $lastgradedfrom = (isset($f->lastgradedfromfilter))? $f->lastgradedfromfilter:$startoftheday;
        $lastgradedto = (isset($f->lastgradedtofilter))? $f->lastgradedtofilter + 86399:$endoftheday;

        $sql ="SELECT DISTINCT u.id as userid,
                      c.id as courseid,
                      c.fullname as course,
                      u.firstname,
                      u.lastname,
                      u.email as emailaddress,
                      u.department,
                      ROUND(gg.finalgrade,2) as grade,
                      gg.timemodified as lastgraded
               FROM {course} AS c
               JOIN {context} AS ctx ON c.id = ctx.instanceid $courseid $categoryid AND ctx.contextlevel = '50'
               JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
               JOIN {user} AS u ON u.id = ra.userid $department $userid
               JOIN {grade_items} AS gi ON gi.courseid = c.id
               LEFT JOIN {grade_grades} AS gg ON gg.userid = u.id and gg.itemid=gi.id
               WHERE gi.courseid = c.id AND ((c.format <> 'singleactivity' AND gi.itemtype = 'course') OR (c.format = 'singleactivity' AND gi.itemtype <> 'course'))
               $gradefrom $gradeto
               AND  gg.timemodified BETWEEN $lastgradedfrom AND $lastgradedto";

        if($usesort && $this->sort()){
            $sql="$sql order by ".$this->sort();
        }

        $this->sql=$sql;
    }


    protected function preprocessShow($rowdata){
        global $CFG, $DB;

        $userid = $rowdata->userid;
        $courseid = $rowdata->courseid;
        if (!isset($rowdata->grade)){
            $rowdata->grade = '--';
        }

        //link for grades
        block_reportsdash::wrap($rowdata->grade,"$CFG->wwwroot/grade/report/user/index.php?id=$courseid&userid=$userid",array('title'=>'User report'));
        $lastgraded = $rowdata->lastgraded;

        if (empty($lastgraded)){
            $rowdata->lastgraded = '--';
        } else{
            $rowdata->lastgraded = date('d/m/y h:ia', $lastgraded);
        }

        return $rowdata;
    }



    protected function getData($usesort=true){

        $data = parent::getData(true);
        global $DB;
        $f = $this->filters;
        $lastgradedfrom = (isset($f->lastgradedfromfilter))? $f->lastgradedfromfilter:'';
        $lastgradedto = (isset($f->lastgradedtofilter))? $f->lastgradedtofilter:'';
        $records = array();

        if (empty($lastgradedfrom) || empty($lastgradedto)){
            return $this->data = $data;
        }
        $rows = 0;
        foreach ($data as $d) {

            // last graded data
            $lastgradedsql = $DB->get_record_sql("SELECT gg.timemodified as lastgraded
                                                  FROM {grade_grades} gg
                                                  JOIN {grade_items} gi ON gi.id=gg.itemid
                                                  WHERE userid = :userid
                                                  AND courseid = :courseid
                                                  ORDER BY gg.timemodified DESC
                                                  LIMIT 1",
                array('userid' => $d->userid, 'courseid' => $d->courseid));

            $lastgraded = $lastgradedsql->lastgraded;
            if ($lastgraded >= $lastgradedfrom && $lastgraded <= $lastgradedto + 86399) { // add a day - second for upper limit
                //add lastgraded to results
                $d->lastgraded = $lastgraded;
                $records[] = $d;
                $rows++;
            }
        }
        $this->records = $rows;
        return $this->data = $records;
    }


    function get_filter_form(){

        $this->filterform=new block_reportsdash_coursetotalgrades_filter_form(null, array('rptname'=>$this->reportname(),
         ),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }


    protected function setColumnStyles(){
        parent::setColumnStyles();
        // set text in the columns to the left
        $this->table->column_style_all('text-align', 'left');
    }

    protected function preprocessExport($rowdata){
         // missing grades
         if (!isset($rowdata->grade)){
             $rowdata->grade = '--';
         }

         //lastgraded date formatting
         $rowdata->lastgraded = date('d/m/y h:ia', $rowdata->lastgraded);

         return $rowdata;
    }

    static function get_report_category(){
        return 'courseuse';
    }

}