<?php
/**
 * Main entry point for Reports Dashboard - the actual dashboard.
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

require_once(__DIR__.'/locallib.php');

global $USER;

$L='block_reportsdash';

$context      = context_system::instance();;

$url          = new moodle_url('/blocks/reportsdash/index.php', array('inpopup'=>1) );

$settings_url = new moodle_url('/blocks/reportsdash/report_settings.php?sesskey='.$USER->sesskey, array('inpopup'=>1) );

//add the page title
$PAGE->navbar->add(get_string('pluginname','block_reportsdash'));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title( get_string('pluginname','block_reportsdash') );
$PAGE->set_heading( get_string('pluginname','block_reportsdash') );
$PAGE->set_pagelayout('standard');

if(!block_reportsdash::can_view_any())
{
    print_error(get_string('nopermissions','block_reportsdash'));
}

echo $OUTPUT->header();

/** adding the report settings page to the Settings > site admin' >  reports */
echo $OUTPUT->container_start('Reports');

// admins, and managers can view the settings page.
if ( has_capability('block/reportsdash:configure_reportsdash',$context) ){
    $exitbutton=new single_button($settings_url,get_string('controlpanellink',$L));
    print $OUTPUT->render($exitbutton);
}
echo $OUTPUT->container_end();

echo html_writer::tag('h2',get_string('dash_link', 'block_reportsdash'),array('class'=>'page-title'));

echo block_reportsdash::get_dashboard();

echo $OUTPUT->footer();