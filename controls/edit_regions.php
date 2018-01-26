<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
include_once(__DIR__.'/../../../config.php');

require_once($CFG->dirroot.'/blocks/reportsdash/locallib.php');

//Globals and pseudo-globals

global $OUTPUT,$PAGE;

$H= new HTML_writer;
$L = 'block_reportsdash';  // Language

$context=context_system::instance();

$url = new moodle_url('/blocks/reportsdash/controls/edit_regions.php');
$returnurl = new moodle_url('/blocks/reportsdash/controls/regions.php');
$panelurl = new moodle_url('/blocks/reportsdash/report_settings.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$PAGE->navbar->add(get_string('controlpanellink',$L),$panelurl);
$PAGE->navbar->add(get_string('regions',$L),$returnurl);
$PAGE->navbar->add(get_string('editregions',$L));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));
$PAGE->set_pagelayout('standard');

$edit_form = new block_reportsdash_edit_regions_form($url);

if($edit_form->is_cancelled())
{
   redirect($returnurl);
   exit;
}

if($editdata=$edit_form->get_data())
{
   $regions = block_reportsdash::get_regions();

   $DB->set_field_select('block_reportsdash_region','visible',0,'1');

   $vis=$editdata->vis;
   $del=$editdata->del;
   $moveto=$editdata->moveto;
   $name=$editdata->name;
   $newregion=trim($editdata->newregion);

   foreach($regions as $id=>$reg)
   {
      $reg->visible=(isset($vis[$id]));
      if(!empty($name[$id]))
      {
         $reg->name=$name[$id];
      }
   }

   $trans=$DB->start_delegated_transaction();

   foreach($regions as $reg)
   {
      $DB->update_record('block_reportsdash_region',$reg);
   }

   if($newregion)
   {
      $n=new stdClass;
      $n->name=$newregion;
      $n->visible=1;

      $DB->insert_record('block_reportsdash_region',$n);
   }

//Never delete the 'unassigned' region
   unset($del[1]);
   if(!empty($del))
   {
      foreach($del as $rid=>$dummy)
      {
         $tgt=$moveto[$rid];

         if(isset($del[$tgt])) //If trying to move to another deleted region, move to unassigned
            $tgt=1;

         $DB->set_field('block_reportsdash_regcats','rid',$tgt,array('rid'=>$rid));
         $DB->delete_records('block_reportsdash_region',array('id'=>$rid));
      }
   }
   $trans->allow_commit();

   redirect($returnurl);
   exit;

}

print $OUTPUT->header();

$returnbutton=new single_button($returnurl,get_string('backtoregions',$L));

print $OUTPUT->render($returnbutton);

print $H->tag('h2',get_string('editregions',$L));

//echo $H->tag('div',$wis_content,array('id'=>'settings-sections') );

print $edit_form->display();

echo $OUTPUT->footer();