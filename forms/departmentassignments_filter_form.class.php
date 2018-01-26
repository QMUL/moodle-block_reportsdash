<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

//Default filter block

class block_reportsdash_departmentassignments_filter_form extends moodleform
{
   function definition() {
      global $CFG;

      $imports = (object)$this->_customdata;

      $DB=$imports->db;

      $L = 'block_reportsdash'; //Default language file

      $mform =& $this->_form;

      block_reportsdash_report::make_filter($mform,$DB);

      $buttonarray = array();

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

         $g = $mform->addGroup($temparray, 'termrange', get_string('startfromterm', $L), ' &nbsp;', false);

      }

      $options=array('startyear'=>date('Y',$DB->get_field_sql('select time from {log} limit 0,1')),
                     'stopyear'=>date('Y',time()));

      $mform->addElement('date_selector', 'fromfilter', get_string('fromdate', $L),$options);

//	 $g = $mform->addGroup($temparray, 'daterange', get_string('startfromdate', $L), ' &nbsp;', false);

      $mform->addElement('duration','markingwindow',get_string('markingwindow',$L));
      $mform->setDefault('markingwindow',86400*7);

      $mform->addElement('checkbox', 'opencourses', get_string('includeopen',$L), ' ('.get_string('nodueby',$L).')');

      $mform->disabledIf('termrange','timetoggle');
      $mform->disabledIf('fromfilter','timetoggle','checked');

      $mform->addElement('hidden', 'rptname', $imports->rptname);

      $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));
      $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
      $mform->closeHeaderBefore('buttonar');
   }
}