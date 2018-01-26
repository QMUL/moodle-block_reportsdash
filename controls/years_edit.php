<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

include_once(__DIR__.'/../../../config.php');
require_once("$CFG->libdir/weblib.php");
require_once("$CFG->libdir/formslib.php");
require_once($CFG->dirroot.'/blocks/reportsdash/locallib.php');

//Globals and pseudo-globals

global $OUTPUT,$PAGE;

$H= new HTML_writer;
$L = 'block_reportsdash';  // Language
$D=get_string('strftimedatefullshort','langconfig'); // Date format

$context=context_system::instance();

$returnurl = new moodle_url('/blocks/reportsdash/controls/terms.php');
$url = new moodle_url('/blocks/reportsdash/controls/terms_edit.php');

require_login();

$action=required_param('action',PARAM_CLEAN);
$type=required_param('type',PARAM_CLEAN);
$id=required_param('id',PARAM_INT);

$returnurl->params(array('action'=>$action,'type'=>$type,'id'=>$id));

if($type!='terms' and $type!='years')
{
   print_error(get_string('nosuchtype',$L));
   exit;
}

if(!$oldrec=$DB->get_record("block_reportsdash_{$type}",array('id'=>$id)))
{
   redirect($returnurl);
   exit;
}

$formname='block_reportsdash_add_'.substr($type,0,-1).'_form';

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading('Terms');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
print $H->tag('h2',get_string('edityear',$L));
print $H->tag('p',get_string('explaineditterm',$L));

$form=new $formname($returnurl,array('actionbutton'=>get_string('edityear',$L)));
$form->set_data($oldrec);
$form->display();

echo $OUTPUT->footer();
