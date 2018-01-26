<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
require_once("$CFG->libdir/formslib.php");

class block_reportsdash_useractivity_filter_form extends moodleform
{
    function definition() {
        global $DB, $CFG;

        $imports = (object)$this->_customdata;

        $L = 'block_reportsdash'; //Default language file

        $mform =& $this->_form;

        $buttonarray = array();

        $mform->addElement('hidden', 'rptname', 'useractivity_report');
        $mform->addElement('hidden', 'uid', $imports->uid);
        $mform->addElement('hidden','before',(isset($imports->onlynever)?1:0));

        if(!empty($imports->onlynever))
        {
            $label='todate';
        }
        else
        {
            $label='fromdate';
        }

        $options=array('startyear'=>date('Y',$DB->get_field_sql('select time from {log} where module="user" and action="login" and userid=:uid limit 0,1',array('uid'=>$imports->uid))),
                       'stopyear'=>date('Y',time()));

        $mform->addElement('date_selector', 'fromfilter', get_string($label, $L),$options);

        $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));

        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
}