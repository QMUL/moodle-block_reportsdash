<?php

// Copyright 2015 ULCC 

/**
 * The report_viewed event.
 *
 * @package    block_reportsdash
 * @copyright  2015 ULCC (T Worthington)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_reportsdash\event;
defined('MOODLE_INTERNAL') || die();
/**
 * The report_viewed event class.
 **/

class report_viewed extends \core\event\base
{
    protected function init()
    {
        global $USER;
        $this->data['crud'] = 'r'; // c(reate), r(ead), u(pdate), d(elete)
        $this->data['edulevel'] = self::LEVEL_OTHER; //No educational value
        $this->data['objecttable'] = null;
    }
    
    protected function get_legacy_logdata()
    {
        global $USER;
        $data=(object)$this->data['other'];
        return array(SITEID,'reportsdash','view',
            "/blocks/reportsdash/report.php?rptname=$data->report",
            $data->report,0,$USER->id);
    }
    
    public function get_url()
    {
        return '/blocks/reportsdash/controls/report_viewed.php';
    }
    
    public function get_description()
    {
        return "Records the viewing of a report dash report";
    }
    
    public static function get_explanation()
    {
        $data=$this->data->other;
        return "The $data->reportname was viewed by $this->userid";
    }
}