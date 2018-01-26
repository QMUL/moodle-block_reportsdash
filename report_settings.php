<?php
/**
 * Main entry point for the Reports Dashboard control panel
 *
 * @copyright &copy; 2013 University of London Computer Centre
 * @author http://www.ulcc.ac.uk, http://moodle.ulcc.ac.uk
 * @author Thomas Worthington
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ReportsDash
 * @version 1.0
 */

require(dirname(__FILE__).'/../../config.php');

global $CFG;

require($CFG->dirroot.'/blocks/reportsdash/locallib.php');
require_once($CFG->dirroot.'/blocks/reportsdash/settings_form.php');
require_once("$CFG->dirroot/course/lib.php");

global $PAGE,$OUTPUT;

$H= new HTML_writer;
$L = 'block_reportsdash';  // Language

$context      = context_system::instance();

$settings_url = new moodle_url('/blocks/reportsdash/report_settings.php' );
$returnurl = new moodle_url('/blocks/reportsdash/index.php' );

$PAGE->navbar->add(get_string('controlpanellink',$L));
$PAGE->set_url($settings_url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('controlpaneltitle',$L));
$PAGE->set_heading(get_string('controlpanellink',$L));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

require_capability('block/reportsdash:configure_reportsdash',$context);
$exitbutton=new single_button($returnurl,get_string('backtoreportsdash',$L));

print $OUTPUT->render($exitbutton);

print $H->tag('h2',get_string('controlpaneltitle',$L));

$output='';

foreach(array('regions'=>get_string('regions',$L),
              'staff'=>get_string('staffassignment',$L),
	      'terms'=>get_string('termtimes',$L),
	      'permissions'=>get_string('permissionspanel',$L),
          'courseleader'=>get_string('courseleader',$L))
	as $link=>$text)
{
   $img='';
   $imgfile="/blocks/reportsdash/pix/settings.png";
   if(file_exists($CFG->dirroot.$imgfile))
   {
      $img = $H->tag('img','',array('src'=>$CFG->wwwroot.$imgfile,'id'=>$link,'title'=>$text,'class'=>'reporticon'));
   }

   $hlink=$H->link("$CFG->wwwroot/blocks/reportsdash/controls/$link.php",$text,array('class'=>'reportdash_control_link'));
   $output.=$H->tag('li',$hlink, array( 'id'=>'rpt-list-item-'.$link));
}

print $H->tag('div',$H->tag('ul',$output), array('id'=>'dash-controls-container' ));

echo $OUTPUT->footer();