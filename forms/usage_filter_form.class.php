<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

//Default filter block

require_once("$CFG->libdir/formslib.php");

class block_reportsdash_usage_filter_form extends moodleform
{
   function definition() {
      global $DB,$CFG;

      $imports = (object)$this->_customdata;

      $L = 'block_reportsdash'; //Default language file

      $mform =& $this->_form;

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

	 $sel=&$mform->createElement('hierselect','termend','End term');
	 $sel->setOptions(array($yeararray,$termarray));
	 $temparray[]=$sel;

	 $g = $mform->addGroup($temparray, 'termrange', get_string('terms', $L), ' Until&nbsp;', false);

      }

//Get minimum login year, allowing for possibility of truncated log file.
      $options=array('startyear'=>min(date('Y',$DB->get_field_sql('select time from {log} limit 0,1')),
                                      $DB->get_field_sql('select min(currentlogin) from {user} where currentlogin>0')),
                     'stopyear'=>date('Y',time()));

      $temparray = array();
      $temparray[] =& $mform->createElement('date_selector', 'fromfilter', get_string('fromdate', $L),$options);
      $temparray[] =& $mform->createElement('date_selector', 'tofilter', get_string('todate', $L),$options);

      $g = $mform->addGroup($temparray, 'daterange', get_string('daterange', $L), ' &nbsp;', false);

      $mform->disabledIf('termrange','timetoggle');
      $mform->disabledIf('daterange','timetoggle','checked');

      $mform->addElement('hidden', 'rptname', $imports->rptname);

      $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));
      $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
      $mform->closeHeaderBefore('buttonar');
   }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (isset($data['fromfilter']) && isset($data['tofilter']) && $data['fromfilter'] > $data['tofilter']){
            $errors['daterange'] = get_string('daterangeerror', 'block_reportsdash');
        }
        return $errors;
    }
}