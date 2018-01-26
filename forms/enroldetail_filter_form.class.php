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

class block_reportsdash_enroldetail_filter_form extends moodleform
{
   function definition() {
      global $DB,$CFG;

      $imports = (object)$this->_customdata;

      $L = 'block_reportsdash'; //Default language file

      $mform =& $this->_form;

      $buttonarray = array();

      if($imports->item>0)
      {
         $enrolcounts=$DB->get_records_sql("select enrol, count(*) n
                                                   FROM {enrol} e
                                                   JOIN {user_enrolments} ue ON ue.enrolid=e.id
                                                   WHERE e.courseid={$imports->item}
                                                   GROUP BY enrol");
         $tot=array_reduce($enrolcounts,function ($total,$item){return ($total+$item->n);},0);
      }
      else //category
      {
         $test=-$imports->item;

         $wherebit=($test!==0)? "WHERE (cc.path like '%/$test/%' or cc.path like'%/$test')": '';

         $enrolcounts=$DB->get_records_sql("select enrol, count(distinct u.id) n
                                                   FROM {enrol} e
                                                   JOIN {user_enrolments} ue ON ue.enrolid=e.id
                                                   JOIN {user} u on (u.id=ue.userid and u.deleted=0)
                                                   JOIN {course} c on c.id=e.courseid
                                                   JOIN {course_categories} cc on c.category=cc.id
                                                   $wherebit
                                                   GROUP BY enrol WITH ROLLUP");

         $temp=array_pop($enrolcounts);
         $tot=$temp->n;
      }

      $select = $mform->addElement('select', 'enrolfilter', get_string('enrolfilter', $L));

      $select->addOption('All ('.$tot.')',0,array());

      foreach(enrol_get_plugins(false) as $name=>$dummy)
      {
         if(isset($enrolcounts[$name]))
         {
            $select->addOption(ucfirst($name)." ({$enrolcounts[$name]->n})",$name,array());
            unset($enrolcounts[$name]);
         }
         else
         {
            $select->addOption(ucfirst($name),$name,array('disabled'=>'disabled'));
         }
      }

      $select->setSelected($imports->enrolfilter);

      $radio=array();

      $radio[]=&$mform->createElement('radio','usergroup','','All users ',0);
      $radio[]=&$mform->createElement('radio','usergroup','','Non-staff ',1);
      $radio[]=&$mform->createElement('radio','usergroup','','Staff ',2);

//         $mform->addGroup($radio,'lalala','Show ',array(),false);


      $mform->addElement('hidden', 'rptname', $imports->rptname);
      $mform->addElement('hidden', 'item', $imports->item);

      $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));
      $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
      $mform->closeHeaderBefore('buttonar');

   }
}