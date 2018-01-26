<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG,$DB,$USER;

require_once("../../../config.php");
require_once($CFG->dirroot.'/lib/externallib.php');

$pfx = $CFG->prefix;
$courseid = required_param('courseid', PARAM_INT);

$coursesql = ($courseid == 0)? " c.id IN (SELECT instanceid
                                          FROM {context} ctx
                                          JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                          JOIN {role} AS r ON r.id = ra.roleid
                                          WHERE r.archetype = 'editingteacher'
                                          AND userid = $USER->id)":" c.id = $courseid";

$students = $DB->get_records_sql("SELECT CONCAT(u.firstname,' ', u.lastname,' | ',u.idnumber) as fullname, u.id
                                              FROM {course} AS c
                                              JOIN {context} AS ctx ON c.id = ctx.instanceid
                                              JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
                                              JOIN {user} AS u ON u.id = ra.userid
                                              JOIN {role} AS r ON r.id = ra.roleid
                                              WHERE $coursesql
                                              AND r.shortname = 'student'
                                              ORDER BY u.lastname ");

// all students taught by the current teacher
$allstudents = $DB->get_records_sql("SELECT DISTINCT  CONCAT(u.firstname,' ', u.lastname,' | ',u.idnumber) as fullname, u.id as studentfilter
                                              FROM {course} AS c
                                              JOIN {context} AS ctx ON c.id = ctx.instanceid
                                              JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
                                              JOIN {user} AS u ON u.id = ra.userid
                                              JOIN {role} AS r ON r.id = ra.roleid
                                              WHERE c.id IN (SELECT instanceid
                                                                 FROM {context} ctx
                                                                 JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                                                 JOIN {role} AS r ON r.id = ra.roleid
                                                                 WHERE r.archetype = 'editingteacher'
                                                                AND userid = $USER->id)
                                              AND r.shortname = 'student'
                                              ORDER BY u.lastname");

foreach($allstudents as $name=>$value){
    $mystudentsarr[] = $value->studentfilter;
}

foreach($students as $name)
{
    if (in_array($name->id,$mystudentsarr)){
         $student    =   new stdClass();
         $student->name = $name->fullname;
         $student->id = $name->id;

         $studentsarray[]  =   (array) $student;
    }
}

echo json_encode($studentsarray);
