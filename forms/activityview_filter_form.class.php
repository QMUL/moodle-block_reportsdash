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

class block_reportsdash_activityview_filter_form extends moodleform
{
    function definition() {
        global $DB,$CFG, $USER;

        $imports = (object)$this->_customdata;

        $L = 'block_reportsdash'; //Default language file

        $mform =& $this->_form;

        $buttonarray = array();

        if(empty($imports->singlemode))
        {
            $is_admin = is_siteadmin($USER);
            if(!empty($is_admin)){
                $course = $DB->get_records_sql("SELECT  DISTINCT shortname,id as cid FROM {course}");
            } else {
                $course = $DB->get_records_sql("SELECT  DISTINCT shortname ,c.id as cid
                   FROM {course} c
                   JOIN {enrol} e ON c.id= e.courseid
                   JOIN {user_enrolments} ue ON e.id = ue.enrolid");
            }

            $select = $mform->addElement('select', 'coursefilter', get_string('coursefilter', $L));
            $select->addOption('Select course',0,array());
            foreach($course as $name=>$value)
            {
                $select->addOption($name,$value->cid,array());
            }

            $participants = $DB->get_records_sql("SELECT  CONCAT(firstname,' ', lastname) as fullname, u.id as participantfilter
                   FROM {user} u
                   JOIN {user_enrolments} ue ON u.id = ue.userid");
            $select = $mform->addElement('select', 'participantfilter', get_string('participantfilter', $L));
            $select->addOption('All Participants',0,array());
            foreach($participants as $name=>$value)
            {
                $select->addOption($name,$value->participantfilter,array());

            }

            $actions = $DB->get_records_sql("SELECT action
                                            FROM {logstore_standard_log}
                                            GROUP BY action");

            $select = $mform->addElement('select', 'actionfilter', get_string('actionfilter', $L));
            $select->addOption('All Actions',0,array());
            foreach($actions as $name=>$value)
            {
                $select->addOption($name,$name,array());
            }

            $roles = $DB->get_records_sql("SELECT  shortname
                   FROM {role} r ");
            $select = $mform->addElement('select', 'rolefilter', get_string('rolefilter', $L));
            $select->addOption('All Roles',0,array());
            foreach($roles as $name=>$value)
            {
                $select->addOption($name,$name,array());

            }

            $select = $mform->addElement('select', 'targetfilter', get_string('targetfilter', $L));
            $select->addOption('All targets',0,array());
            $targets = $DB->get_records_sql("SELECT target
                                         FROM {logstore_standard_log}
                                         GROUP BY target");
            foreach($targets as $name=>$value)
            {
                $select->addOption($name,$name,array());

            }


            if($years=$DB->get_records('block_reportsdash_years'))
            {
                $mform->addElement('checkbox', 'timetoggle', 'Use term dates');

                $terms=$DB->get_records('block_reportsdash_terms');

                $yeararray=$termarray=array();

                foreach($terms as $t)
                {
                    $termarray[$t->year][$t->id]=$t->termname;
                }

                foreach($years as $y)
                {
                    $yeararray[$y->id]=$y->yearname;
                    $termarray[$y->id][YEAR_START]='Year start';
                    $termarray[$y->id][YEAR_END]='Year end';
                }

                $sel=&$mform->createElement('hierselect','termstart','Start term');
                $sel->setOptions(array($yeararray,$termarray));
                $temparray[]=$sel;

                $sel=&$mform->createElement('hierselect','termend','End term');
                $sel->setOptions(array($yeararray,$termarray));
                $temparray[]=$sel;

                $g = $mform->addGroup($temparray, 'termrange', get_string('terms', $L), ' Until&nbsp;', false);

            }

            $options=array('fromfilter'=>date('Y',$DB->get_field_sql('select time from {log} limit 0,1')),
                'tofilter'=>date('Y',time()));

            $temparray = array();
            $temparray[] =& $mform->createElement('date_selector', 'fromfilter', get_string('fromdate', $L),$options);
            $temparray[] =& $mform->createElement('date_selector', 'tofilter', get_string('todate', $L));

            $g = $mform->addGroup($temparray, 'daterange', get_string('daterange', $L), ' &nbsp;', false);

            $mform->disabledIf('termrange','timetoggle');
            $mform->disabledIf('daterange','timetoggle','checked');

            $mform->addElement('hidden', 'rptname', $imports->rptname);

            $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        }
    }


    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['fromfilter']) && isset($data['tofilter']) && $data['fromfilter'] > $data['tofilter']){
            $errors['daterange'] = get_string('daterangeerror', 'block_reportsdash');
        }
        return $errors;
    }
}