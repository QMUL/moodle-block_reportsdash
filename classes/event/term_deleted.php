<?php

// Copyright 2015 ULCC 

/**
 * The term_deleted event.
 *
 * @package    block_reportsdash
 * @copyright  2015 ULCC (T Worthington)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportsdash\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The term_deleted event class.
 **/

class term_deleted extends \core\event\base
{
    protected function init()
    {
        global $USER;
        $this->data['crud'] = 'd'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER; //No educational value
        $this->data['objecttable'] = 'block_reportsdash_terms';
    }
    
    protected function get_legacy_logdata()
    {
        $data=$this->data->other;
        return array(SITEID,'reportsdash',"termdeleted","/blocks/reportsdash/controls/terms.php",
                     $data->rec->$fieldname.": {$data->startstr} - {$data->endstr}",0,$USER->id);
    }
    
    public function get_url()
    {
        return '/blocks/reportsdash/controls/term_deleted.php';
    }
    
    public function get_description()
    {
        return "Records the removal of an academic term";
    }
    
    public static function get_explanation()
    {
        $data=$this->data->other;
        return "The term {$data->rec->$fieldname} was deleted";
    }
}