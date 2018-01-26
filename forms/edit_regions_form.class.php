<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

class block_reportsdash_edit_regions_form extends moodleform
{
   function definition()
   {
      $mform =& $this->_form;

      $L = 'block_reportsdash';  // Language

      $regions = block_reportsdash::get_regions();

      $mform->addElement('text','newregion',get_string('newregion',$L),array('size'=>20));
      $mform->setType('newregion',PARAM_NOTAGS);

      $regionoptions=array();
      foreach($regions as $id=>$reg)
      {
         $regionoptions[$id]=$reg->name;
      }

      $W=new String_Container(array('region','visible','delete','moveregioncontent'),$L);

      if(!empty($regions))
      {
         $mform->addElement('html',"<table class='flexible' width='100%'><thead><tr><th>$W->region</th><th>$W->visible</th><th>$W->delete</th><th>$W->moveregioncontent</th></tr></thead>");
         foreach($regions as $reg)
         {
            $mform->addElement('html',"<tr>");

            $mform->addElement('html',"<td>");
            $mform->addElement('text',"name[{$reg->id}]",'',array('size'=>20));
            $mform->setType("name[{$reg->id}]",PARAM_NOTAGS);
            $mform->setDefault("name[{$reg->id}]",$reg->name);
            $mform->addElement('html',"</td>");

            $mform->addElement('html',"<td>");
            $mform->addElement('checkbox',"vis[{$reg->id}]");
            $mform->setDefault("vis[{$reg->id}]",$reg->visible);

            $mform->addElement('html',"</td>");
            if($reg->id!=1)
            {
               $mform->addElement('html',"<td>");
               $mform->addElement('checkbox',"del[{$reg->id}]");
               $mform->addElement('html',"</td>");
               $theseoptions=$regionoptions;

//Don't show this region as a possible target for moving this region's contents
               unset($theseoptions[$reg->id]);
               if(!empty($theseoptions))
               {
                  $mform->addElement('html',"<td>");
                  $mform->addElement('select',"moveto[{$reg->id}]",'',$theseoptions);
                  $mform->setDefault("moveto[{$reg->id}]",1);
                  $mform->addElement('html',"</td>");
               }
               $mform->disabledIf("moveto[{$reg->id}]", "del[{$reg->id}]", 'notchecked');
            }
            $mform->addElement('html',"</tr>");
         }
         $mform->addElement('html','</table>');
      }

      $this->add_action_buttons();

      return $mform;
   }
}