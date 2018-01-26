<?php

// Copyright 2015 ULCC 

/**
 * The term_added event.
 *
 * @package    block_reportsdash
 * @copyright  2015 ULCC (T Worthington)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportsdash\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The term_added event class.
 **/

class term_added extends \core\event\base
{
    protected function init()
    {
        global $USER;
        $this->data['crud'] = 'c'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER; //No educational value
        $this->data['objecttable'] = 'block_reportsdash_terms';
    }
    
    protected function get_legacy_logdata()
    {
        $data=$this->data->other;
        return array(SITEID,'reportsdash','termadd',"/blocks/reportsdash/controls/terms.php",
                   "{$data->termname} {$data->startstr}-{$data->endstr}",0,$USER->id);
    }
    
    public function get_url()
    {
        return '/blocks/reportsdash/controls/term_added.php';
    }
    
    public function get_description()
    {
        $data=$this->data->other;
        return "Term {$this->data->termname} defined as {$data->startstr}-{$data->endstr}";
    }
    
    public static function get_explanation()
    {
        return "Create a term as part of a year";
    }
}