<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

class block_reportsdash_add_year_form extends moodleform
{
    function definition()
    {
        $L='block_reportsdash';
        $mform =&$this->_form;

        $imports=$this->_customdata;

        $mform->addElement('hidden','formname','add_year_form');
        $mform->setType('formname',PARAM_TEXT);
        $mform->addElement('hidden','yid',(isset($imports['yid'])?$imports['yid'] : 0));
        $mform->setType('yid',PARAM_INT);

        $mform->addElement('date_selector','yearstart',get_string('yearstart',$L));
        $mform->addElement('date_selector','yearend',get_string('yearend',$L));

        $this->add_action_buttons($imports['cancelbutton'],$imports['actionbutton']);

        $mform->closeHeaderBefore('buttonar');

    }
}
