<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
require_once("$CFG->libdir/formslib.php");

class block_reportsdash_login_filter_form extends moodleform
{
   function definition() {

       global $DB;

       $imports = (object)$this->_customdata;

       $L = 'block_reportsdash'; //Default language file

       $mform =& $this->_form;

       $buttonarray = array();

       $firstlog=$DB->get_field_sql('select (firstaccess) as t from {user} where firstaccess>0',null,IGNORE_MULTIPLE);

       $options=array('startyear'=>date('Y',$firstlog),
                      'stopyear'=>date('Y',time()));

       $mform->addElement('date_selector', 'fromfilter', get_string('fromdate', $L),$options);
       $mform->setDefault('fromfilter',max($firstlog,$imports->filters->fromfilter));

       $mform->addElement('checkbox', 'onlynever', 'Not logged in since');

       $radio=array();

       $radio[]=&$mform->createElement('radio','usergroup','','All users ',0);
       $radio[]=&$mform->createElement('radio','usergroup','','Non-staff ',1);
       $radio[]=&$mform->createElement('radio','usergroup','','Staff ',2);

      // $mform->addGroup($radio,'lalala','Show ',array(),false);

       $mform->addElement('hidden', 'rptname', $imports->rptname);

       $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));

       $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
       $mform->closeHeaderBefore('buttonar');
   }
}