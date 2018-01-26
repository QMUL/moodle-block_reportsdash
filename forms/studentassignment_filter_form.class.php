<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once("$CFG->libdir/enrollib.php");
//Default filter block

class block_reportsdash_studentassignment_filter_form extends moodleform
{
    function definition() {
        global $DB,$CFG,$USER;

        $imports = (object)$this->_customdata;

        $L = 'block_reportsdash'; //Default language file

        $mform =& $this->_form;
        $selectedcourse = (!empty($_GET['coursefilter']))? "c.id=". $_GET['coursefilter']: "c.id IN (SELECT instanceid
                                                                   FROM {context} ctx
                                                                   JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                                                   JOIN {role} AS r ON r.id = ra.roleid
                                                                   WHERE r.archetype = 'editingteacher'
                                                                   AND userid = $USER->id)";

        $buttonarray = array();

        if(empty($imports->singlemode))
        {
           $select = $mform->addElement('select', 'coursefilter', get_string('coursefilter', $L));

            $select->addOption(get_string('all', $L),0,array());

            // Students for the selected course
            $selectstudents = $mform->addElement('select', 'studentfilter', get_string('studentfilter', $L));
            $selectstudents->addOption( get_string('selectstudent', $L),0,array());

            $students = $DB->get_records_sql("SELECT  CONCAT(u.firstname,' ', u.lastname,' | ',u.idnumber), u.id as studentfilter
                                              FROM {course} AS c
                                              JOIN {context} AS ctx ON c.id = ctx.instanceid
                                              JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
                                              JOIN {user} AS u ON u.id = ra.userid
                                              JOIN {role} AS r ON r.id = ra.roleid
                                              WHERE $selectedcourse
                                              AND r.shortname = 'student'
                                              ORDER BY u.lastname");


            // all students taught by the current tutor
             $allstudents = $DB->get_records_sql("SELECT DISTINCT CONCAT(u.firstname,' ', u.lastname,' | ',u.idnumber), u.id as studentfilter
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

            $mystudents = '0,';

            foreach($allstudents as $name=>$value)
            {
                $mystudents .= "$value->studentfilter,";
                $mystudentsarr[] = $value->studentfilter;
            }

            foreach($students as $name=>$value)
            {
                if (in_array($value->studentfilter,$mystudentsarr)){
                    $selectstudents->addOption($name,$value->studentfilter,array());
                }
            }

            $mystudents = rtrim($mystudents, ",");

            // All courses
            $course = $DB->get_records_sql("SELECT distinct CONCAT(c.fullname,' | ',c.shortname) as coursename,c.id
                                            FROM {course} AS c
                                            JOIN {context} AS ctx ON c.id = ctx.instanceid
						                    JOIN {role_assignments} AS ra ON ra.contextid = ctx.id
						                    JOIN {user} AS u ON u.id = ra.userid
						                    JOIN {role} AS r ON r.id = ra.roleid
					                  	    WHERE r.shortname = 'student'
					                  	    AND u.id IN ($mystudents)
					                  	    ORDER BY c.fullname");



            foreach($course as $name=>$value)
            {
                $select->addOption($name,$value->id,array());
            }

            // Assessment Type
            $select = $mform->addElement('select', 'assessmentfilter', get_string('assessmentfilter', $L));
            $info = core_plugin_manager::instance()->get_plugin_info("mod_turnitintool");
            if ($info && $info->is_enabled())
            {
                $select->addOption(get_string('all', $L),0,array());
            }
            $select->addOption(get_string('moodleassign', $L),'assign',array());
            if ($info && $info->is_enabled())
            {
                $select->addOption(get_string('tiiassign', $L),'turnitintool',array());
            }
            if ($info && !$info->is_enabled() && is_siteadmin())
            {
                $select->addOption(get_string('tiiassign', $L),'turnitintool',array('disabled' => 'disabled', 'class'=>'notinuse'));
            }

            $mform->addRule('studentfilter', get_string('selectstudent', $L), 'nonzero', '', 'client');
            $mform->addElement('hidden', 'rptname', $imports->rptname);

            $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        }
    }
}
