<?php
/**
 * Created by PhpStorm.
 * User: n.narayanan
 * Date: 19/02/14
 * Time: 16:42
 */


global $CFG,$DB;

require_once("../../../config.php");
require_once($CFG->dirroot.'/lib/externallib.php');

$pfx = $CFG->prefix;


$course_id = required_param('courseid', PARAM_ALPHANUM);

// Courses which were made visible OR created in the given period.
$sql = "  SELECT modinfo FROM {course} WHERE id = $course_id";

$courseinfo = $DB->get_records_sql($sql);
$coursemod = array();
$modulearray = array();
foreach ($courseinfo as $c)  {
    $coursemod = unserialize($c->modinfo);
    foreach ($coursemod as $cm){

        $mod    =   new stdClass();
        $mod->name = $cm->name;
        $mod->id = $cm->cm;

        $modulearray[]  =   (array) $mod;
    }
}

echo json_encode($modulearray);


