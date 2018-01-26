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

$url = new moodle_url('/blocks/reportsdash/controls/staff.php');
$returnurl = new moodle_url('/blocks/reportsdash/report_settings.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$PAGE->navbar->add(get_string('controlpanellink',$L),$returnurl);
$PAGE->navbar->add(get_string('staffassignment',$L));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));
$PAGE->set_pagelayout('standard');

// who is staff form
$staff_form = new block_reportsdash_define_staff_form($url);

$roledata='';

if($roledata=$staff_form->get_data() ) {
    $DB->delete_records('block_reportsdash_staff');

    $logroles=array();

    $rec = new stdClass();
    foreach( $roledata as $name=>$dummy ){

        $role = explode( '_', $name );

        if($role[0]=='staff')
        {
            $rec->roleid=array_pop($role);
            $DB->insert_record('block_reportsdash_staff',$rec);
            $logroles[$rec->roleid]=1;
        }
    }


    // Trigger an event for staff_roles_assigned
    $params = array('userid' => $USER->id,
                    'context' => $context,
                    'other'=>$logroles
    );
    
    $event = \block_reportsdash\event\staff_roles_assigned::create($params);
    $event->trigger();

}

if($staff_form->is_cancelled() or $roledata)
{
    redirect($returnurl);
    exit;
}

print $OUTPUT->header();

$editbutton=new single_button($returnurl,get_string('backtoctlpanel',$L));
print $OUTPUT->render($editbutton);

print $H->tag('h2',get_string('staffassignment',$L));

$wis_content = $H->tag('h4',get_string('staffsubtitle', $L));
$content2    = $H->tag('p',get_string('staffexplained', $L));
$wis_content .= $H->tag('div',$content2,array('class'=>'section-intro'));

echo $H->tag('div',$wis_content,array('id'=>'settings-sections') );

echo $staff_form->display();

echo $OUTPUT->footer();