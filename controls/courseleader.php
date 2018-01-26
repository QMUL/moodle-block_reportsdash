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
//require_once($CFG->dirroot.'/blocks/reportsdash/classes/event/courseleader_assigned.php');

//Globals and pseudo-globals

global $OUTPUT,$PAGE;

$H= new HTML_writer;
$L = 'block_reportsdash';  // Language

$context=context_system::instance();

$url = new moodle_url('/blocks/reportsdash/controls/courseleader.php');
$returnurl = new moodle_url('/blocks/reportsdash/report_settings.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$PAGE->navbar->add(get_string('controlpanellink',$L),$returnurl);
$PAGE->navbar->add(get_string('courseleader',$L));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));
$PAGE->set_pagelayout('standard');

// who is staff form
$leader_form = new block_reportsdash_define_courseleader_form($url);

$roledata='';

$leaderroles=array();

if($roledata=$leader_form->get_data() ) {
   $rec = new stdClass();

   set_config('leaderroles',$roledata->leaderrole,'block_reportsdash');

   // Trigger an event for this review.
   $params = array('userid' => $USER->id,
                   'context' => $context,
                   'other'=>array('roledata'=>$roledata)
   );

   $event = \block_reportsdash\event\courseleader_assigned::create($params);
   $event->trigger();
}

if($leader_form->is_cancelled() or $roledata)
{
   redirect($returnurl);
   exit;
}

print $OUTPUT->header();

$editbutton=new single_button($returnurl,get_string('backtoctlpanel',$L));
print $OUTPUT->render($editbutton);

print $H->tag('h1',get_string('leaderassignment',$L));
$content2    = $H->tag('p',get_string('leaderexplained', $L));
$wis_content = $H->tag('div',$content2,array('class'=>'section-intro'));

echo $H->tag('div',$wis_content,array('id'=>'settings-sections') );

echo $leader_form->display();

echo $OUTPUT->footer();