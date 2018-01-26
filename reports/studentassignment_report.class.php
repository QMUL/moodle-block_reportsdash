<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("$CFG->libdir/externallib.php");

class block_reportsdash_studentassignment_report extends block_reportsdash_report {

    static function services()
    {
        return array('reportsdash_student_assignment'=>'Returns Student Assignments');
    }


    protected static function fields()
    {
        return (object)array('outputs'=>array(new Reportdash_field_info('coursename',PARAM_TEXT,"Course name"),
                                              new Reportdash_field_info('assessmentname',PARAM_TEXT,'Assessment name'),
                                              new Reportdash_field_info('grade',PARAM_INT,'Grade'),
                                              new Reportdash_field_info('assessmenttype',PARAM_TEXT,'Assessment type'),
                                              new Reportdash_field_info('submissiondate',PARAM_TEXT,'Submission Date'),
                                              new Reportdash_field_info('duedate',PARAM_TEXT,'Due Date'),
                                              new Reportdash_field_info('assessmentlink',PARAM_TEXT,'Assessment Link'),
                                              new Reportdash_field_info('feedbacklink',PARAM_TEXT,'Feedback Link')),
                            );
    }


    function __construct()
    {
        parent::__construct(static::column_names(),false);
    }

    protected function setSql($usesort=true)
    {
        global $CFG;
        $f = $this->filters;

        $courseid = (!empty($f->coursefilter)? "and gi.courseid='$f->coursefilter'":'');
        $studentfilter = (!empty($f->studentfilter)? "and gg.userid='$f->studentfilter'":"and gg.userid ='0'");
        $assessmentfilter = (!empty($f->assessmentfilter)? "and gi.itemmodule='$f->assessmentfilter'":'');
        $suuserid = (!empty($f->studentfilter)? "and su.userid='$f->studentfilter'":'');


        // Moodle and TII assignments
        $sql = "SELECT c.id, c.fullname as coursename,gi.itemname as assessmentname,
                       CONVERT(gg.finalgrade,DECIMAL(3,0)) as grade,
                       gi.itemmodule as assessmenttype,gi.idnumber,su.assignment as iteminstance,
					   su.timemodified as submissiondate,
					   a.duedate as duedate, a.name as assessmentlink,'' as tiiobjid,'' as subid,
					   '' as subpart,'' as feedbacklink, a.grade as gradetype
                FROM {course} c JOIN {grade_items} gi ON c.id=gi.courseid
                $assessmentfilter
                $courseid
                JOIN {grade_grades} gg ON gi.id=gg.itemid
                $studentfilter
				JOIN {assign} a ON a.id=gi.iteminstance
				JOIN {assign_submission} su ON a.id = su.assignment
                $suuserid";
         if (core_plugin_manager::instance()->get_plugin_info("mod_turnitintool") != null)
         {
             $sql .= "
				UNION
                SELECT c.id, c.fullname as coursename,gi.itemname as assessmentname,
                       CONVERT(gg.finalgrade,DECIMAL(3,0)) as grade,
                       gi.itemmodule as assessmenttype,gi.idnumber,gi.iteminstance,
		    		   su.submission_modified as submissiondate, t.defaultdtdue as duedate,
		    		   su.submission_title as assessmentlink,su.submission_objectid as tiiobjid,su.id as subid,
		    		   su.submission_part as subpart,'' as feedbacklink,t.grade as gradetype
                FROM {course} c JOIN {grade_items} gi ON c.id=gi.courseid AND itemtype='mod'
                $assessmentfilter
                $courseid
                JOIN {grade_grades} gg ON gi.id=gg.itemid
                $studentfilter
                JOIN {turnitintool} t ON t.id=gi.iteminstance
                JOIN {turnitintool_submissions} su ON t.id = su.turnitintoolid
                $suuserid";
         }

        $sortby = 'submissiondate ASC'; // default value submission date desc
        if($usesort && isset($_GET['tsort'])){
           // swap feedback with grade as we will use grade to sort feedback link column (Feedback/Not Graded)
           $sortby = str_replace('feedbacklink','grade',$this->sort());
        }

        // set filter to default value if no other value is chosen
        if ($sortby == 'submissiondate ASC'){
            $this->table->sort_default_column = 'submissiondate';
            $this->table->sort_default_order = 4; //3 DESC, 4 ASC
        }
        $sql="$sql order by ".$sortby;

        $this->sql=$sql;
    }


    protected static function timeformat()
    {
        return get_string('strftimedatetime','langconfig');
    }


    protected function setColumnStyles()
    {
        parent::setColumnStyles();
        $this->table->column_style_all('text-align','left');
    }


    protected function preprocessShow($rowdata)
    {
        global $CFG,$DB, $USER;
        $f=$this->filters;
        $modtype = $rowdata->assessmenttype;
        $userid = $f->studentfilter;
        $asessmentid = $rowdata->iteminstance;
        $L = 'block_reportsdash'; //Default language file
        $rowdata->feedbacklink = get_string('feedback', $L);

        if (!isset($rowdata->grade)){
            $rowdata->grade = '--';
        }

        // id is not always present in grade_items table, so we should retrieve it instead
        $idresult = $DB->get_record_sql("SELECT cm.id FROM mdl_course_modules cm JOIN mdl_modules m ON m.id=cm.module
                                         WHERE m.name = '$modtype' AND cm.instance = $asessmentid");
        $id = $idresult->id;

        // Moodle Assignment
        if ($modtype == 'assign'){
            $rowdata->assessmenttype = get_string('moodleassign', $L);

            // links
            block_reportsdash::wrap($rowdata->assessmentlink,"$CFG->wwwroot/blocks/reportsdash/moodleassign.php?id=$id&userid=$userid");
            ($rowdata->grade == '--')?$rowdata->feedbacklink = get_string('notgraded', $L):block_reportsdash::wrap($rowdata->feedbacklink,"$CFG->wwwroot/blocks/reportsdash/moodleassign.php?id=$id&userid=$userid");
            block_reportsdash::wrap($rowdata->assessmentname,"$CFG->wwwroot/mod/assign/view.php?id=$id");
        }

        // Turnitin Assignment
        if ($modtype == 'turnitintool'){
            $rowdata->assessmenttype = get_string('tiiassign', $L);

            $filelink = "$CFG->wwwroot/mod/turnitintool/view.php?id=$id&jumppage=submission&userid=$USER->id&utp=2&partid=$rowdata->subpart&objectid=$rowdata->tiiobjid";
            $autoupdates = 1;

            // links
            block_reportsdash::wrap($rowdata->assessmentlink,$filelink, array('target'=>'_blank','class'=>"fileicon",'onclick="screenOpen(\''.$filelink.'\',\''.$rowdata->subid.'\',\''.$autoupdates.'\');return false;"'));
            ($rowdata->grade == '--')?$rowdata->feedbacklink = get_string('notgraded', $L):
            block_reportsdash::wrap($rowdata->feedbacklink,"$CFG->wwwroot/mod/turnitintool/view.php?id=$id&do=notes&s=$rowdata->subid");
            block_reportsdash::wrap($rowdata->assessmentname,"$CFG->wwwroot/mod/turnitintool/view.php?id=$id");
        }


        //handle grades that are using scales
        if ($rowdata->gradetype < 0 && ($rowdata->grade != '--')){
            $value = $this->get_scale_grade($rowdata->gradetype);
            $rowdata->grade = $value[$rowdata->grade];
        }

        ($rowdata->submissiondate == '0')? $rowdata->submissiondate = get_string('notset', $L):$rowdata->submissiondate = userdate($rowdata->submissiondate,static::timeformat());
        ($rowdata->duedate == '0')? $rowdata->duedate = get_string('notset', $L):$rowdata->duedate = userdate($rowdata->duedate,static::timeformat());

        return $rowdata;
    }


    protected function preprocessExport($rowdata)
    {
        $L = 'block_reportsdash'; //Default language file
        ($rowdata->assessmenttype == 'assign')?$rowdata->assessmenttype = get_string('moodleassign', $L):$rowdata->assessmenttype = get_string('tiiassign', $L);
        ($rowdata->submissiondate == '0')? $rowdata->submissiondate = get_string('notset', $L):$rowdata->submissiondate = userdate($rowdata->submissiondate,static::timeformat());
        ($rowdata->duedate == '0')? $rowdata->duedate = get_string('notset', $L):$rowdata->duedate = userdate($rowdata->duedate,static::timeformat());
        (empty($rowdata->grade))?$rowdata->feedbacklink = get_string('notgraded', $L):$rowdata->feedbacklink = get_string('feedback', $L);


        //handle grades that are using scales
        if ($rowdata->gradetype < 0 && ($rowdata->grade != '--')){
            $value = $this->get_scale_grade($rowdata->gradetype);
            $rowdata->grade = $value[$rowdata->grade];
        }

        return $rowdata;
    }


    function get_filter_form()
    {
        global $PAGE;
        $jsmodule = array(
            'name'     	=> 'studentassignment_functions',
            'fullpath' 	=> "/blocks/reportsdash/reports/studentassignment_report.js",
            'requires'  	=> array('event','dom','node','io-form')
        );

        $PAGE->requires->js_init_call('M.studentassignment_functions.init', null, true, $jsmodule);
        $this->filterform= new block_reportsdash_studentassignment_filter_form(null, array('rptname'=>$this->reportname(),
                'db'=>$this->mydb),
            '','',array('id'=>'rptdashfilter'));
        return $this->filterform;
    }

   function get_scale_grade($scaleid){

    global $DB;
       $scale = $DB->get_record('scale', array('id'=>-($scaleid)));
       $scalevalues = array_reverse(explode(',', $scale->scale), true);
       foreach ($scalevalues as $key => $item) {
           $value[$key+1] = trim($item);
       }

       return  $value;
    }

    static function get_report_category(){
        return 'userspecific';
    }

}
