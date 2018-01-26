<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

//Default filter block

class block_reportsdash_courseavailability_filter_form extends moodleform
{
   function definition()
   {
      global $DB;

      $maxdepth=$DB->get_field_sql('select max(depth) from {course_categories} cc1
                                           JOIN {block_reportsdash_regcats} rc
                                                on rc.cid=substring_index(substring_index(cc1.path, "/", 2),"/",-1)
                                           JOIN {block_reportsdash_region} r on (r.id=rc.rid)
                                           WHERE r.visible=1 and cc1.visible=1');

      $imports = (object)$this->_customdata;

      $L = 'block_reportsdash'; //Default language file

      $mform =& $this->_form;

      $mform->addElement('select', 'depthlimit', get_string('depthlimit', $L), range(1,$maxdepth));

      $mform->addElement('hidden', 'rptname', $imports->rptname);

      $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));

      $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
      $mform->closeHeaderBefore('buttonar');

   }
}
