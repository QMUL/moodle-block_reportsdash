<?php

// Copyright 2015 ULCC 

/**
 * The staff_roles_assigned event.
 *
 * @package    block_reportsdash
 * @copyright  2015 ULCC (T Worthington)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportsdash\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The staff_roles_assigned event class.
 **/

class staff_roles_assigned extends \core\event\base
{
    protected function init()
    {
        global $USER;
        $this->data['crud'] = 'u'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER; //No educational value
        $this->data['objecttable'] = 'block_reportsdash_staff';
    }
    
    protected function get_legacy_logdata()
    {
        $data=$this->data->other;
        return array(SITEID,'reportsdash','staffdefined',
                     "/blocks/reportsdash/controls/staff.php",
                     implode(',',array_keys($other)),0,$USER->id);
    }
    
    public function get_url()
    {
        return '/blocks/reportsdash/controls/staff_roles_assigned.php';
    }
    
    public function get_description()
    {
        return "Updates the report dashboard's definition of who is a member of staff.";
    }
    
    public static function get_explanation()
    {
        $data=$this->data->other;
        return "The report dashboard's definition of who is a member of staff was updated";
    }
}