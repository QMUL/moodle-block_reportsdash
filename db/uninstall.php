<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

require_once(dirname(__FILE__).'/../../../config.php');

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/blocks/reportsdash/locallib.php");

function xmldb_block_reportsdash_uninstall() {
   global $CFG, $DB;

   $dbman = $DB->get_manager();

   $xmlds = $dbman->get_install_xml_schema();

   $xmlds->deleteTable('block_reportsdash_regcats');
   $xmlds->deleteTable('block_reportsdash_region');
   $xmlds->deleteTable('block_reportsdash_reports');
   $xmlds->deleteTable('block_reportsdash_staff');

   $DB->delete_records('events_handlers',array('component'=>'block_reportsdash'));

   $dirname="$CFG->dirroot/blocks/reportsdash/reports/";

   foreach(block_reportsdash::find_reports() as $path=>$report)
   {
       $report::uninstall();
   }

   return true;
}