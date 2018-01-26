<?php

require('../../config.php');
require_once($CFG->dirroot.'/blocks/reportsdash/locallib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
global $OUTPUT,$PAGE,$DB,$USER;

require_login();

$url          = new moodle_url('/blocks/reportsdash/moodleassign.php');
$context_sys = context_system::instance();

$PAGE->set_url($url);
$PAGE->set_context($context_sys);
$PAGE->set_title( get_string('pluginname','block_reportsdash') );
$PAGE->set_heading(' ');
$PAGE->set_pagelayout('standard');

// breadcrumb
$PAGE->navbar->ignore_active();
$PAGE->navbar->add('Assessment/Feedback');
echo $OUTPUT->header();


$id           = optional_param('id', 0, PARAM_INT); // asiignment id
$userid       = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

// display assignment / submission / feedback

$cm = get_coursemodule_from_id('assign', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$assign = new assign($context,$cm,$course);

$submission = $assign->get_user_submission($userid, false);
$coursemod =  $DB->get_record_sql("SELECT * FROM {course_modules} WHERE id = ?",array($id));
$instance = $coursemod->instance;
$assignment =  $DB->get_record_sql("SELECT * FROM {assign} WHERE id = $instance");


$grade = $assign->get_user_grade($userid, false);

$user = $DB->get_record_sql("SELECT * FROM {user} WHERE id = ?",array($userid));

$summary = $assign->view_student_summary($user, false);

// get role Dasboard viewer
$role = $DB->get_record_sql("SELECT * FROM {role} WHERE shortname = 'report_viewer'");
$dashboard_viewer = false;
if ($role) {
    $dashboard_viewer = $DB->get_record_sql("SELECT * FROM {role_assignments} WHERE userid = $USER->id AND roleid = $role->id");
}

$capabilities = array('moodle/course:view','moodle/course:viewhiddencourses','mod/assign:grade','mod/assign:view');

if (core_plugin_manager::instance()->get_plugin_info("mod_turnitintool") != null){
    $capabilities[] = 'mod/turnitintool:grade';
    $capabilities[] = 'mod/turnitintool:view';
}

if ($dashboard_viewer || has_all_capabilities($capabilities, $context)){
        if ($summary){
            print "<h1>".$assignment->name."</h1>";
            print $OUTPUT->user_picture($user, array('courseid'=>$course->id));

            print "<a href=\"$CFG->wwwroot/user/view.php?id={$user->id}&amp;course=$course->id\">"
            ." {$user->firstname} ". "{$user->lastname}</a>";

            print $summary;

        }else{
            print 'No Assessment/Feedback found. Check parameters.';}
} else {

    print "You don't have permission to access this link, contact Administrator";
}
echo $OUTPUT->footer();