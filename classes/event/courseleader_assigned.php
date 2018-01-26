<?php

// Copyright 2015 ULCC 
 
/**
 * The courseleaderrole_assigned event.
 *
 * @package    block_reportsdash
 * @copyright  2015 ULCC (T Worthington)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportsdash\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The courseleaderrole_assigned event class.
 **/

class courseleader_assigned extends \core\event\base
{
    static function get_name()
    {
        return get_string('eventcourseleader_assigned','block_reportsdash');
    }

    protected function init()
    {
        global $USER;
        $this->data['crud'] = 'u'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER; //No educational value
        $this->data['objecttable'] = null;
    }

    protected function get_legacy_logdata()
    {
        return array(SITEID,'reportsdash','courseleader',"/blocks/reportsdash/controls/courseleader.php",$roledata->leaderrole,0,$USER->id);
    }

    public function get_url()
    {
        return '/blocks/reportsdash/controls/courseleader.php';
    }

    public function get_description()
    {
        mylog(print_r($this,true));
        return "The user with id {$this->userid} assigned the course role to be ".$this->other['roledata']->leaderrole;
    }


}