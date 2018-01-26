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

if($action=optional_param('action','',PARAM_CLEAN) and !optional_param('cancel','',PARAM_CLEAN))
{
    $periodtype=required_param('type',PARAM_CLEAN);
    $id=required_param('id',PARAM_INT);

    if($action=='delete' and ($periodtype=='years' or $periodtype=='terms'))
    {
        $rec=$DB->get_record("block_reportsdash_{$periodtype}",array('id'=>$id));

        //Yes, this is stupid.
        $period=substr($periodtype,0,-1);

        $fieldname=$period.'name';
        $fieldstart=$period.'start';
        $fieldend=$period.'end';

        $startstr=date("D jS M Y",$rec->$fieldstart);
        $endstr=date("D jS M Y",$rec->$fieldend);

        $DB->delete_records("block_reportsdash_{$periodtype}",array('id'=>$id));

        $params=array('context'=>context_system::instance(),
                      'userid'=>$USER->id,
                      'other'=>array('periodtype'=>$periodtype,
                                     'rec'=>$rec)
        );

        if($periodtype==='years')
        {
            $DB->delete_records("block_reportsdash_terms",array('year'=>$id));
            $event = \block_reportsdash\event\year_deleted::create($params);
        }
        else
        {
            $event = \block_reportsdash\event\term_deleted::create($params);
        }
        $event->trigger();
    }
}
else
{
    $id=0;
}

require_once($CFG->libdir.'/tablelib.php');

//Globals and pseudo-globals

global $OUTPUT,$PAGE;

$H= new HTML_writer;
$L = 'block_reportsdash';  // Language
$D=get_string('strftimedatefullshort','langconfig'); // Date format

$context=context_system::instance();

$url = new moodle_url('/blocks/reportsdash/controls/terms.php');
$editurl = new moodle_url('/blocks/reportsdash/controls/terms_edit.php');
$newyearurl = new moodle_url('/blocks/reportsdash/controls/terms_new_year.php');
$newtermurl = new moodle_url('/blocks/reportsdash/controls/terms_new_term.php');
$returnurl = new moodle_url('/blocks/reportsdash/report_settings.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$PAGE->navbar->add(get_string('controlpanellink',$L),$returnurl);
$PAGE->navbar->add(get_string('termtimes',$L));

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

$editbutton=new single_button($returnurl,get_string('backtoctlpanel',$L));

print $OUTPUT->render($editbutton);

print $H->tag('h2',get_string('termtimes',$L));

$year_form=new block_reportsdash_add_year_form($url,array('actionbutton'=>get_string('makeyears',$L),'cancelbutton'=>true));
$term_form=new block_reportsdash_add_term_form($url,array('actionbutton'=>get_string('maketerms',$L),'cancelbutton'=>true));

$existingyears=$DB->get_records('block_reportsdash_years',array(),'yearstart desc');
$existingterms=$DB->get_records('block_reportsdash_terms',array(),'termstart');

if(!$term_form->is_cancelled() and $data=$term_form->get_data())
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
        $error=true;
        print $H->tag('div',get_string('outsideyear',$L),array('class'=>'error'));
    }

    $update=false;
    if(!$error)
    {
        foreach($existingterms as $t)
        {
            if($t->id!=$id and $t->termstart<=$data->termend and $data->termstart<=$t->termend)
            {
                /// New term overlaps with an old term
                print $H->tag('div',get_string('termoverlap',$L,$t),array('class'=>'error'));
                $error=true;
                break;
            }
        }
    }

    if(!$error)
    {
        if($id)
        {
            $data->id=$id;
            $olddata=$existingterms[$id];

            $DB->update_record('block_reportsdash_terms',$data);
            $existingterms[$data->id]=$data;

            //For log
            $startstr=date("D jS M Y",$data->termstart);
            $endstr=date("D jS M Y",$data->termend);

            // Trigger an event for term_updated
            $params = array('userid' => $USER->id,
                            'context' => $context,
                            'objectid'=>$id,
                            'other'=>array('olddata'=>$olddata,
                                           'startstr'=>$startstr,
                                           'endstr'=>$endstr,
                                           'termname'=>$data->termname)
            );

            $event = \block_reportsdash\event\term_updated::create($params);
            $event->trigger();

            block_reportsdash::sort_objects($existingterms,array('termstart'=>SORT_ASC));
        }
        else
        {
            print $H->tag('div',get_string('unknownterm',$L,$t),array('class'=>'error'));
        }
    }
}

$existingterms=block_reportsdash::reindex2d($existingterms,'year');

if($data=$year_form->get_data()) // Modified year
{
    //Make year name in format of 2012/13 or just 2013 if no calendar overlap
    if($data->yearstart>$data->yearend)
    {
        $temp=$data->yearstart;
        $data->yearstart=$data->yearend;
        $data->yearend=$temp;
    }

    $y1=date('Y',$data->yearstart);
    $y2=date('y',$data->yearend);

    //For log
    $startstr=date("D jS M Y",$data->yearstart);
    $endstr=date("D jS M Y",$data->yearend);

    if($y1!=$y2)
    {
        $data->yearname="$y1/$y2";
    }
    else
    {
        $data->yearname=$y1;
    }

    if($olditem=$existingyears[$data->yid])
    {
        $data->id=$olditem->id;
        $DB->update_record('block_reportsdash_years',$data);
        $existingyears[$data->id]=$data;

        // Trigger an event for year_updated
        $params = array('userid' => $USER->id,
                        'context' => $context,
                        'objectid' => $data->id,
                        'other'=>array('olddata'=>$olddata,
                                       'startstr'=>$startstr,
                                       'endstr'=>$endstr,
                                       'yearname'=>$data->yearname)
        );
        
        $event = \block_reportsdash\event\year_updated::create($params);
        $event->trigger();
        block_reportsdash::sort_objects($existingyears,array('yearend'=>SORT_DESC));
    }
    else
    {
        print_error(get_string('unknownyear',$L));
    }
}

$existingyears=block_reportsdash::reindex($existingyears,'yearname');

$editurl->params(array('action'=>'edit'));
$editstr=get_string('edit');

$deleteurl=clone($url);
$deleteurl->params(array('action'=>'delete'));
$deletestr=get_string('delete');

print $H->tag('h2',get_string('addyearsterms',$L));

print $H->tag('p',$OUTPUT->render(new single_button($newyearurl,get_string('makeyears',$L))));
if($existingyears)
{
    print $H->tag('p',$OUTPUT->render(new single_button($newtermurl,get_string('maketerms',$L))));
}

$year_form=new block_reportsdash_add_year_form($url,array('actionbutton'=>get_string('makeyears',$L),'cancelbutton'=>false));
$term_form=new block_reportsdash_add_term_form($url,array('actionbutton'=>get_string('maketerms',$L),'cancelbutton'=>false));

//Display current terms and years
if($existingyears)
{
    print $H->tag('h2',get_string('existingyears',$L));

    $table=new html_table;

    $table->head=(array)get_strings(array('yearterm','startdate','enddate','edit','delete'),$L);

    $rows=array();

    foreach($existingyears as $y)
    {

        $deleteurl->params(array('type'=>'years','id'=>$y->id));
        $editurl->params(array('type'=>'years','id'=>$y->id));

        $editbutton=new single_button($editurl,$editstr);
        $deletebutton=new single_button($deleteurl,$deletestr);

        $deletebutton->add_confirm_action(get_string('confirmdelete',$L,
                                                     get_string('deleteyear',$L,$y->yearname)));

        $rows[]=array($y->yearname,userdate($y->yearstart,$D),userdate($y->yearend,$D),
                      $OUTPUT->render($editbutton),$OUTPUT->render($deletebutton));

        if(isset($existingterms[$y->id]))
        {
            foreach($existingterms[$y->id] as $t)
            {
                $deleteurl->params(array('type'=>'terms','id'=>$t->id));
                $editurl->params(array('type'=>'terms','id'=>$t->id));

                $editbutton=new single_button($editurl,$editstr);
                $deletebutton=new single_button($deleteurl,$deletestr);

                $deletebutton->add_confirm_action(get_string('confirmdelete',$L,
                                                             get_string('deleteterm',$L,
                                                                        (object)array('termname'=>$t->termname,'yearname'=>$y->yearname))));

                $rows[]=array("&nbsp;&nbsp;$t->termname",userdate($t->termstart,$D),userdate($t->termend,$D),
                              $OUTPUT->render($editbutton),$OUTPUT->render($deletebutton));
            }
        }
    }

    $table->data=$rows;

    print $H->table($table);

}
else
{
    print_string('noyearsdefined',$L);
}

echo $OUTPUT->footer();
