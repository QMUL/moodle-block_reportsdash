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
$D=get_string('strftimedatefullshort','langconfig'); // Date format

$context=context_system::instance();

$url = new moodle_url('/blocks/reportsdash/controls/permisssions.php');
$returnurl = new moodle_url('/blocks/reportsdash/report_settings.php');

require_capability('block/reportsdash:configure_reportsdash',$context);

$allroles=$DB->get_records('role',array(),'sortorder');
$allreports=block_reportsdash::find_reports();

if(optional_param('cancel',0,PARAM_CLEAN))
{
    redirect($returnurl);
    exit;
}
elseif(optional_param('submit',0,PARAM_CLEAN))
{
    $oldparms=$DB->get_records('block_reportsdash_perms');
    $DB->delete_records('block_reportsdash_perms');
    foreach($allroles as $role)
    {
        foreach($allreports as $path=>$report)
        {
            include_once($path);
            $rptname=$report::reportname();
            $varname="permit{$role->id}_$rptname";
            if(optional_param($varname,'',PARAM_CLEAN) or optional_param("permit{$role->id}__all",'',PARAM_CLEAN))
            {
                $DB->insert_record('block_reportsdash_perms',(object)array('roleid'=>$role->id,
                                                                           'report'=>$rptname,
                                                                           'canview'=>1));
            }
        }
    }

    $params = array('context' => $context,
                    'other'=>$oldparms);

    $event = \block_reportsdash\event\reportpermissions_updated::create($params);
    $event->trigger();

    redirect($returnurl);
    exit;
}

$PAGE->navbar->add(get_string('controlpanellink',$L),$returnurl);
$PAGE->navbar->add(get_string('permissionspanel',$L));
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname',$L));
$PAGE->set_heading(get_string('settings',$L));



echo $OUTPUT->header();

$editbutton=new single_button($returnurl,get_string('backtoctlpanel',$L));

print $OUTPUT->render($editbutton);

print $H->tag('h2',get_string('permissionspanel',$L));

$table=new html_table;

$permissions=$DB->get_recordset('block_reportsdash_perms',array(),'','roleid,report');
$permissions=block_reportsdash::reindex2d($permissions,'report');

$headarray=array(get_string('role'));
$alignarray=array('center');

foreach($allreports as $path=>$reportname)
{
    include_once($path);
    $headarray[]=$reportname::display_name();
    $alignarray[]='center';
}

$headarray[]=get_string('setall',$L);
$alignarray[]='center';

print $H->start_tag('form',array('method'=>'post'));

$table->head=$headarray;
$table->align=$alignarray;

$rows=array();

foreach($allroles as $role)
{
    //Classic Moodle Team work: make role_get_name compulsory part
    //way through the 2.x lifetime AND change how it works at the
    //same time.
    if(block_reportsdash::versionAtLeast('2.4.0'))
    {
        $role->name=role_get_name($role,$context);
    }
    else
    {
        $role->name=ucwords($role->shortname);
    }

    $row=array($role->name);

    $allids=array();

    foreach($allreports as $reportname)
    {
        $mirror=new ReflectionClass($reportname);

        $rptname=$reportname::reportname();

        $allids[]="id_permit{$role->id}_{$rptname}";

        if(!$mirror->isAbstract())
        {
            $precheck=true;
            if(isset($permissions[$rptname][$role->id]))
            {
                $row[]=$H->empty_tag('input',array('type'=>'checkbox',
                                                   'name'=>"permit{$role->id}_{$rptname}",
                                                   'onclick'=>"if(!this.checked) document.getElementById('id_permit{$role->id}__all').checked=false",
                                                   'id'=>"id_permit{$role->id}_{$rptname}",
                                                   'checked'=>1));
            }
            else
            {
                $row[]=$H->empty_tag('input',array('type'=>'checkbox',
                                                   'name'=>"permit{$role->id}_{$rptname}",
                                                   'onclick'=>"if(!this.checked) document.getElementById('id_permit{$role->id}__all').checked=false",
                                                   'id'=>"id_permit{$role->id}_{$rptname}",
                ));
                $precheck=false;
            }
        }
    }

    $js='';
    foreach($allids as $id)
    {
        $js.="document.getElementById('$id').checked=this.checked; ";
    }

    $js.='return true';

    $attrs=array('type'=>'checkbox',
                 'name'=>"permit{$role->id}__all",
                 'id'=>"id_permit{$role->id}__all",
                 'onclick'=>$js);

    if($precheck)
        $attrs['checked']='1';

    $row[]=$H->empty_tag('input',$attrs);


    $rows[]=$row;
}

$rows[]=array($H->tag('button',get_string('submit'),array('type'=>'submit','name'=>'submit','value'=>1)),
              $H->tag('button',get_string('cancel'),array('type'=>'submit','name'=>'cancel','value'=>1)),
              $H->tag('button',get_string('reset') ,array('type'=>'reset','name'=>'reset')));


$table->data=$rows;

print $H->table($table);

print $H->end_tag('form');

print $OUTPUT->footer();