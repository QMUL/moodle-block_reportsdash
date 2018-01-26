<?php
/**
 * Version details
 *
 * @package    reportsdash
 * @copyright  2013 ULCC, University of London
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../locallib.php');

//Globals and pseudo-globals

global $OUTPUT,$PAGE;

$H= new HTML_writer;
$L = 'block_reportsdash';  // Language
$D=get_string('strftimedatefullshort','langconfig'); // Date format

$context=context_system::instance();

$url = new moodle_url('/blocks/reportsdash/controls/terms_new_term.php');
$returnurl = new moodle_url('/blocks/reportsdash/controls/terms.php');
$reportsurl = new moodle_url('/blocks/reportsdash/report_settings.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$PAGE->navbar->add(get_string('controlpanellink',$L),$reportsurl);
$PAGE->navbar->add(get_string('termtimes',$L),$returnurl);
$PAGE->navbar->add(get_string('maketerms',$L));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));
$PAGE->set_pagelayout('standard');

$existingterms=$DB->get_records('block_reportsdash_terms',array(),'termstart');
$existingyears=$DB->get_records('block_reportsdash_years',array(),'yearstart');

$term_form=new block_reportsdash_add_term_form($url,array('actionbutton'=>get_string('maketerms',$L),'cancelbutton'=>true));

if($term_form->is_cancelled())
{
    redirect($returnurl);
    exit;
}

$error=false;

if($data=$term_form->get_data()) // New term
{
    if($data->termstart>$data->termend)
    {
        $data->termstart=$data->termend;
        $data->termend=$data->termstart;
    }
    else
    {
        $data->termstart=$data->termstart;
        $data->termend=$data->termend;
    }

    $error=false;

    $thisyear=$existingyears[$data->year];

    if($data->termstart< $thisyear->yearstart or $data->termend>$thisyear->yearend)
    {
        $error=$H->tag('div',get_string('outsideyear',$L),array('class'=>'error'));
    }

    if(!$error)
    {
        foreach($existingterms as $t)
        {
            if($t->termstart<=$data->termend and $data->termstart<=$t->termend)
            {
                /// New term overlaps with an old term
                $error=$H->tag('div',get_string('termoverlap',$L,$t),array('class'=>'error'));
                break;
            }
        }
    }

    if(!$error)
    {
        $startstr=date("D jS M Y",$data->termstart);
        $endstr=date("D jS M Y",$data->termend);

        $objectid=$DB->insert_record('block_reportsdash_terms',$data);

        $params=array('context'=>$context,
                      'objectid'=>$objectid,
                      'other'=>array('yearname'=>$data->yearname,
                                     'startstr'=>$startstr,
                                     'endstr'=>$endstr)
        );

        $event=\block_reportsdash\event\term_added::create($params);
        $event->trigger();      

        redirect($returnurl);
        exit;
    }

}

echo $OUTPUT->header();

print $H->tag('h3',get_string('maketerms',$L));

if($error)
    print $error;

print $term_form->display();


print $OUTPUT->footer();