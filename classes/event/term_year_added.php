<?php

// Copyright 2015 ULCC 
 
/**
 * The term_year_added event.
 *
 * @package    block_reportsdash
 * @copyright  2015 ULCC (T Worthington)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportsdash\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The term_year_added event class.
 **/

class term_year_added extends \core\event\base
{
    protected function init()
    {
        global $USER;
        $this->data['crud'] = 'c'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER; //No educational value
        $this->data['objecttable'] = 'block_reportsdash_years';
    }

    protected function get_legacy_logdata()
    {
        $data=$this->data->other;
        return array(SITEID,'reportsdash','yearadd',"/blocks/reportsdash/controls/terms.php",
                     "{$data->yearname}: {$data->startstr}-{$data->endstr}",0,$USER->id);
    }

    public function get_url()
    {
        return '/blocks/reportsdash/controls/terms_new_year.php';
    }

    public function get_description()
    {
        return "The academic year {$this->other->yearname} was defined as starting at {$this->other->startstr} and ending at {$this->other->endstr}";
    }

    public static function get_explanation()
    {
        return "The start and end dates of an academic year were defined";
    }
}