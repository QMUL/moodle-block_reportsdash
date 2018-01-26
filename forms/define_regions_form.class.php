<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_define_regions_form extends moodleform
{
   function definition(){
      global $CFG,$DB;

      $mform =& $this->_form;
      $mform->addElement('header', 'miscellaneoussettingshdr', '', '',array('style'=>'border:3px solid black'));
//      $mform->addElement('header', 'miscellaneoussettingshdr2', get_string('miscellaneoussettings', 'form'));


      $L = 'block_reportsdash';  // Language

      $regions = block_reportsdash::get_regions();

//Notice that this will automatically ignore deleted categories because of the join
      $regcats=$DB->get_records_sql('select rc.cid,rc.rid,rc.id
                                            FROM {block_reportsdash_regcats} rc
                                            JOIN {block_reportsdash_region} r on r.id=rc.rid
                                            JOIN {course_categories} cc on cc.id=rc.cid
                                            order by cc.name');

      $unassignedid=1;

      $tlcs=$DB->get_records('course_categories',array('depth'=>1));

//Make sure all top level categories are assigned to at least
//the 'unassigned' region.
      foreach($tlcs as $cid=>$cat)
      {
         if(!isset($regcats[$cid]))
         {
            $o=new stdClass;
            $o->cid=$cat->id;
            $o->rid=$unassignedid;

            $o->id=$DB->insert_record('block_reportsdash_regcats',$o);

            $regcats[$cid]=$o;

         }
      }

      $oldreg=$regcats;

      foreach($oldreg as $cid=>$cat)
      {
         if(!isset($tlcs[$cid]))
         {
            $DB->delete_records('block_reportsdash_regcats',array('cid'=>$cid));
            unset($regcats[$cid]);
         }
      }

      $n=count($regions);
      $mform->addElement('html',"<table class='flexible'><tr><th >".get_string('category').'</th>');
      $mform->addElement('html',"<th colspan=$n>".get_string('region',$L).'</th></tr><tr><th></th>');

      foreach($regions as $region)
      {
         $radioarray=array();
         $mform->addElement('html',"<th>{$region->name}</th>");
      }
      $mform->addElement('html','</tr>');

      foreach($regcats as $catid=>$map)
      {
         $radioarray=array();
         $mform->addElement('html','<tr>');
         $label=$tlcs[$catid]->name;
         block_reportsdash::wrap($label,"$CFG->wwwroot/course/category.php?id=$catid");
         $mform->addElement('html',"<td class='inactive'>$label</td>");

         foreach($regions as $rid=>$region)
         {
            $mform->addElement('html','<td>');
            $mform->addElement('radio',"map_$catid",null,null, $rid);
            $mform->addElement('html','</td>');
         }
         $mform->setDefault("map_$catid",$map->rid);

         $mform->addElement('html','</tr>');
      }

      $mform->addElement('html','</table>');

      $buttonarray=array();
      $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('savechanges'));
      $buttonarray[] = &$mform->createElement('reset', 'resetbutton', get_string('revert'));

      $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
      $mform->closeHeaderBefore('buttonar');

      return $mform;
   }
}
