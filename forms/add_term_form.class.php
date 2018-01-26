<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

class block_reportsdash_add_term_form extends moodleform
{
    function definition()
    {
        global $DB;

        $L='block_reportsdash';

        $mform =&$this->_form;

        $imports=$this->_customdata;

        $mform->addElement('hidden','formname','add_year_form');
        $mform->setType('formname',PARAM_TEXT);

        $existingyears=$DB->get_records('block_reportsdash_years',array(),'yearstart desc');

        $existingterms=block_reportsdash::reindex2d($DB->get_records('block_reportsdash_terms',array(),'termstart'),
                                                    'year');

        $options=array();
        foreach($existingyears as $year)
        {
            $options[$year->id]=$year->yearname;
        }

        $recent=reset($existingyears);

        $mform->addElement('select','year',get_string('academicyear',$L),$options);

        $mform->addElement('text','termname',get_string('termname',$L));
        $mform->setType('termname',PARAM_TEXT);
        $mform->addRule('termname',get_string('termnamerequired',$L),'required',null,'client');

        $mform->addElement('date_selector','termstart',get_string('termstart',$L));
        $mform->setDefault('termstart',isset($recent->yearstart)?$recent->yearstart : 0);
        $mform->addElement('date_selector','termend',get_string('termend',$L));
        $mform->setDefault('termend',isset($recent->yearend)? $recent->yearend : 0);

        $this->add_action_buttons($imports['cancelbutton'],$imports['actionbutton']);
    }
}