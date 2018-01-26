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

class block_reportsdash_enrolments_filter_form extends moodleform
{
   function definition() {
      global $DB,$CFG;

      $imports = (object)$this->_customdata;

      $L = 'block_reportsdash'; //Default language file

      $mform =& $this->_form;

      block_reportsdash_report::make_filter($mform,$DB);

      $buttonarray = array();

      $enrolcounts=$DB->get_records_sql('select enrol, count(*) n
                                                   FROM {enrol} e
                                                   JOIN {user_enrolments} ue ON ue.enrolid=e.id
                                                   GROUP BY enrol');

      $select = $mform->addElement('select', 'enrolfilter', get_string('enrolfilter', $L));

      $select->addOption('All',0,array());

      foreach(enrol_get_plugins(false) as $name=>$dummy)
      {
         if(isset($enrolcounts[$name]))
         {
            $select->addOption(ucfirst($name),$name,array());
            unset($enrolcounts[$name]);
         }
         else
         {
            $select->addOption(ucfirst($name),$name,array('disabled'=>'disabled', 'class'=>'notinuse'));
         }
      }

      $radio=array();

      $radio[]=&$mform->createElement('radio','usergroup','','All users ',0);
      $radio[]=&$mform->createElement('radio','usergroup','','Non-staff ',1);
      $radio[]=&$mform->createElement('radio','usergroup','','Staff ',2);

//         $mform->addGroup($radio,'lalala','Show ',array(),false);


      $mform->addElement('hidden', 'rptname', $imports->rptname);

      $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));
      $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
      $mform->closeHeaderBefore('buttonar');
   }
}