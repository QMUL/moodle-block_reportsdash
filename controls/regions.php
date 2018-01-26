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

$url = new moodle_url('/blocks/reportsdash/controls/regions.php');
$returnurl = new moodle_url('/blocks/reportsdash/report_settings.php');
$editurl = new moodle_url('/blocks/reportsdash/controls/edit_regions.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$PAGE->navbar->add(get_string('controlpanellink',$L),$returnurl);
$PAGE->navbar->add(get_string('regions',$L));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));
$PAGE->set_pagelayout('standard');

$region_form = new block_reportsdash_define_regions_form($url);

if($regiondata=(array)$region_form->get_data())
{
   $trans=$DB->start_delegated_transaction();

   $DB->delete_records('block_reportsdash_regcats');

   foreach($DB->get_records('course_categories',array('depth'=>1)) as $cid=>$cat)
   {
      $rid=$regiondata["map_$cid"];

      $o=new stdClass;
      $o->rid=$rid;
      $o->cid=$cid;

      $DB->insert_record('block_reportsdash_regcats',$o);
   }

   $trans->allow_commit();
}

print $OUTPUT->header();

$returnbutton=new single_button($returnurl,get_string('backtoctlpanel',$L));
$editbutton=new single_button($editurl,get_string('editregions',$L));

print $OUTPUT->render($returnbutton);

print $H->tag('h2',get_string('regions',$L));

$wis_content = $H->tag('p',get_string('regionssubtitle', $L));

print $OUTPUT->render($editbutton);

echo $H->tag('div',$wis_content,array('id'=>'settings-sections') );

print $region_form->display();

echo $OUTPUT->footer();