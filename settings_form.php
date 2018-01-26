<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
require_once(dirname(__FILE__).'/../../config.php');
require_once("$CFG->libdir/formslib.php");

class reportsdash_WIstaff_form extends moodleform {
    function definition(){
        global $CFG,$DB;

        $mform =& $this->_form;

        $roles = rd_get_user_roles();
        $staffroles=$DB-> get_records('block_reportsdash_staff',null,'','roleid');

        if(!empty($roles) ){
            foreach($roles as $role){
                if( $role->shortname != 'guest' ){

                    $mform->addElement( 'advcheckbox','staff_'.$role->shortname.'_'.$role->id, '',$role->name, null, array(0,1));
                }

                if( isset($staffroles[$role->id] )) {
                    $mform->setDefault('staff_'.$role->shortname.'_'.$role->id,'1');
                }
            }
        }

        $mform->addElement( 'submit','regcat_submit', get_string('regions_parentcatbut','block_reportsdash') );

        return $mform;
    }
}

class reportsdash_add_year_form extends moodleform
{
    function definition()
    {
        $L='block_reportsdash';
        $mform =&$this->_form;

        $mform->addElement('hidden','formname','add_year_form');

        $mform->addElement('date_selector','yearstart',get_string('yearstart',$L));
        $mform->addElement('date_selector','yearend',get_string('yearend',$L));

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('createyear'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');

    }
}

class reportsdash_add_term_form extends moodleform
{
    function definition()
    {
        global $DB;

        $L='block_reportsdash';

        $mform =&$this->_form;

        $mform->addElement('hidden','formname','add_year_form');

        $existingyears=$DB->get_records('block_reportsdash_years',array(),'yearstart desc');

        $existingterms=block_reportsdash_reindex2d($DB->get_records('block_reportsdash_terms',array(),'termstart'),
                                                   'year');

        $options=array();
        foreach($existingyears as $year)
        {
            $options[$year->id]=$year->yearname;
        }

        $recent=reset($existingyears);

        $mform->addElement('select','year',get_string('academicyear',$L),$options);

        $mform->addElement('text','termname',get_string('termname',$L));

        $mform->addElement('date_selector','termstart',get_string('termstart',$L));
        $mform->setDefault('termstart',$recent->yearstart);
        $mform->addElement('date_selector','termend',get_string('termend',$L));
        $mform->setDefault('termend',$recent->yearend);

        $buttonarray=array();
        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('createterm'));
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');

    }
}