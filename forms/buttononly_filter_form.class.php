<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

//Default filter block

class block_reportsdash_buttononly_filter_form extends moodleform
{
   function definition() {
      global $DB,$CFG;

      $imports = (object)$this->_customdata;

      $L = 'block_reportsdash'; //Default language file

      $mform =& $this->_form;

      $buttonarray = array();

      $mform->addElement('hidden', 'rptname', $imports->rptname);

      $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
      $mform->closeHeaderBefore('buttonar');
   }
}