<?php

/**
 *
 * @package    reportsdash
 * @copyright   2013 ULCC
 * @authors Thomas Wortington
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__).'/../../../config.php');

require_once("$CFG->dirroot/blocks/reportsdash/locallib.php");

function xmldb_block_reportsdash_install() {
   global $DB,$CFG;

   $item=new stdClass;

   $item->visible=1;
   $item->sortorder=0;

   $dirname="$CFG->dirroot/blocks/reportsdash/reports/";

   foreach(block_reportsdash::find_reports() as $filename=>$classname)
   {
      include_once($filename);

      $reportname=$classname::reportname();

      $item->name=$reportname;
      $item->sortorder++;
      $DB->insert_record('block_reportsdash_reports',$item);

      $classname::install();
   }

   $defaultid=$DB->insert_record('block_reportsdash_region',(object)(array('name'=>'Unassigned','visibility'=>1)));

//Link
   foreach($DB->get_records('course_categories',array('depth'=>1)) as $cid=>$cat)
   {
      $o=new stdClass;
      $o->rid=$defaultid;
      $o->cid=$cat->id;

      $DB->insert_record('block_reportsdash_regcats',$o);
   }
}