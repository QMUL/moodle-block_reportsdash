<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
require_once("$CFG->libdir/formslib.php");

class report_filter_form extends moodleform
{
    function definition() {
        global $DB, $CFG;

        $imports = (object)$this->_customdata;

        $L = 'block_reportsdash'; //Default language file

        $mform =& $this->_form;

        $buttonarray = array();

        if (!$imports->singlemode) {

            $options = array('0' => 'All');

            $regions = get_regions_category_list();

            $prevreg = 0;

            $select = $mform->addElement('select', 'levelfilter', get_string('levelfilter', $L), $options);

            foreach ($regions as $reg) {
                if ($reg->rid != $prevreg) {
                    $prevreg = $reg->rid;
                    //$options[-$reg->rid]=$reg->regionname;
                    $select->addOption($reg->regionname, -$reg->rid, array('class' => 'bold'));
                }

                //$options[$reg->id]=str_repeat('-',$reg->depth).$reg->name;
                $optname = str_repeat('-', $reg->depth) . $reg->name;

                $select->addOption($optname, $reg->id, array());
            }

            $temparray = array();
            $temparray[] =& $mform->createElement('date_selector', 'fromfilter', get_string('fromdate', $L));
            $temparray[] =& $mform->createElement('date_selector', 'tofilter', get_string('todate', $L));
            $temparray[] =& $mform->createElement('checkbox', 'opencourses', 'Hi1', ' Include open');

            $g = $mform->addGroup($temparray, 'daterange', get_string('daterange', $L), ' &nbsp;', false);

            $mform->addElement('hidden', 'rptname', $imports->rptname);
            $mform->addElement('hidden', 'tsort', $imports->tsort);

            $buttonarray[] = & $mform->createElement('submit', 'submitbutton', get_string('filter', $L));
            $buttonarray[] = & $mform->createElement('submit', 'backbutton', get_string('backtodash', $L));
            $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
            $mform->closeHeaderBefore('buttonar');
        }
    }
}

$filterform=new report_filter_form( null, array('tsort'=>optional_param('tsort','',PARAM_CLEAN),
                                                'rptname'=>$rptname,
                                                'singlemode'=>optional_param('singlemode',0,PARAM_INT)),
                                   '','',array('id'=>'rptdashfilter'));

if(!isset($SESSION->reportsdashfilters[$rptname]))
{
    $now=time();
    $SESSION->reportsdashfilters[$rptname]=array('tofilter'=>$now,'fromfilter'=>$now-60*60*24*365);
}

$filterform->set_data($SESSION->reportsdashfilters[$rptname]);

$filterform->display();

if($filters=$filterform->get_data())
{
    if($filters->fromfilter>$filters->tofilter)
    {
        $t=$filters->fromfilter;
        $filters->fromfilter=$filters->tofilter;
        $filters->tofilter=$t;
    }

    $SESSION->reportsdashfilters[$rptname]=$filters;
}
else
{
    $filters=(object)$SESSION->reportsdashfilters[$rptname];
}