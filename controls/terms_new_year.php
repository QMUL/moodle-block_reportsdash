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

$url = new moodle_url('/blocks/reportsdash/controls/terms_new_year.php');
$returnurl = new moodle_url('/blocks/reportsdash/controls/terms.php');
$reportsurl = new moodle_url('/blocks/reportsdash/report_settings.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$PAGE->navbar->add(get_string('controlpanellink',$L),$reportsurl);
$PAGE->navbar->add(get_string('termtimes',$L),$returnurl);
$PAGE->navbar->add(get_string('makeyear',$L));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));
$PAGE->set_pagelayout('standard');

$year_form=new block_reportsdash_add_year_form($url,array('actionbutton'=>get_string('makeyear',$L),'cancelbutton'=>true));

if($year_form->is_cancelled())
{
    redirect($returnurl);
    exit;
}

$error=false;

if($data=$year_form->get_data()) // New year
{

    $existingyears=$DB->get_records('block_reportsdash_years',array(),'yearstart');

    //Make year name in format of 2012/13 or just 2013 if no calendar overlap
    if($data->yearstart>$data->yearend)
    {
        $temp=$data->yearstart;
        $data->yearstart=$data->yearend;
        $data->yearend=$temp;
    }

    $y1=date('Y',$data->yearstart);
    $y2=date('y',$data->yearend);

    if($y1!=$y2)
    {
        $data->yearname="$y1/$y2";
    }
    else
    {
        $data->yearname=$y1;
    }

    foreach($existingyears as $y)
    {
        if($y->yearstart <= $data->yearend and $data->yearstart <= $y->yearend)
        {
            $error=$H->tag('div',get_string('yearoverlap',$L,$y),array('class'=>'error'));
            break;
        }
    }

    if(!$error)
    {
        $startstr=date("D jS M Y",$data->yearstart);
        $endstr=date("D jS M Y",$data->yearend);

        $objectid=$DB->insert_record('block_reportsdash_years',$data);

        $params=array('context'=>$context,
                      'objectid'=>$objectid,
                      'other'=>array('yearname'=>$data->yearname,
                                     'startstr'=>$startstr,
                                     'endstr'=>$endstr)
        );

        $event=\block_reportsdash\event\term_year_added::create($params);
        $event->trigger();

        redirect($returnurl);
    }

}

echo $OUTPUT->header();

print $H->tag('h3',get_string('makeyear',$L));

if($error)
    print $error;

print $year_form->display();


print $OUTPUT->footer();
