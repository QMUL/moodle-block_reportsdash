<?php

// Copyright 2015 ULCC 

/**
 * The term_updated event.
 *
 * @package    block_reportsdash
 * @copyright  2015 ULCC (T Worthington)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportsdash\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The term_updated event class.
 **/

class term_updated extends \core\event\base
{
    protected function init()
    {
        global $USER;
        $this->data['crud'] = 'u'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER; //No educational value
        $this->data['objecttable'] = 'block_reportsdash_terms';
    }
    
    protected function get_legacy_logdata()
    {
        $data=$this->data->other;
        return array(SITEID,'reportsdash','termedit',"/blocks/reportsdash/controls/terms.php",
                       "{$data->olddata->termname}->{$data->termname} {$data->startstr}-{$data->endstr}",0,$USER->id);
    }
    
    public function get_url()
    {
        return '/blocks/reportsdash/controls/term_updated.php';
    }
    
    public function get_description()
    {
        return "An academic term's definition was changed";
    }
    
    public static function get_explanation()
    {
        $data=$this->data->other;
        return "Term {$this->objectid} was updated to '$data->termname, $data->startstr - $data->endstr'";
    }
}