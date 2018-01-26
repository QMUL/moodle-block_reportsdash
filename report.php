<?php
/**
 * Reports Dashboard report frame
 *
 * @copyright &copy; 2013 University of London Computer Centre
 * @author http://www.ulcc.ac.uk, http://moodle.ulcc.ac.uk
 * @author Thomas Worthington
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package ReportsDash
 * @version 1.0
 */

require('../../config.php');
require_once($CFG->dirroot.'/blocks/reportsdash/locallib.php');
require_once($CFG->libdir.'/adminlib.php');

global $OUTPUT,$PAGE;

$url          = new moodle_url('/blocks/reportsdash/report.php');

$id           = optional_param('id', 0, PARAM_INT);
$rptname      = optional_param('rptname','',PARAM_CLEAN);

$singlemode=optional_param('singlemode',0,PARAM_INT);

if(optional_param('backbutton','',PARAM_CLEAN))
{
    redirect("$CFG->wwwroot/blocks/reportsdash");
    exit;
}

require_login();

$export=optional_param('download','',PARAM_ALPHA);

//Notices in particular are pointless during export, so make sure debugging
//is off unless we really know we want it during export.
if($export)
{
    @ini_set('display_errors', '0');
    $CFG->debug = 0;
    $CFG->debugdisplay = false;
}

if(empty($rptname))
{
    redirect("$CFG->wwwroot/blocks/reportsdash");
}

$rptnamestr=get_string($rptname,'block_reportsdash');

$context = context_system::instance();

$classname="block_reportsdash_$rptname";

$r=new $classname();

if(!$export)
{
    $PAGE->set_url($url);
    $PAGE->set_context($context);
    $PAGE->set_title( get_string('pluginname','block_reportsdash') );
    $PAGE->set_pagelayout('report');
    $PAGE->requires->jquery();

    $PAGE->requires->js('/blocks/reportsdash/select2/select2.js');
    $PAGE->requires->css("/blocks/reportsdash/select2/select2.css");

    echo $OUTPUT->header();
    $settings_url = new moodle_url('/blocks/reportsdash/index.php?sesskey='.$USER->sesskey);
    $exitbutton=new single_button($settings_url, get_string('backtodash', 'block_reportsdash'));
    print $OUTPUT->render($exitbutton);
    print "<h2>$rptnamestr</h2>";
    print "<p>".$r->display_report_description()."</p>";
}

if(!$export)
{
   $r->show();
}
else
{
   $r->export($export);
}

if(!$export)
    echo $OUTPUT->footer();
