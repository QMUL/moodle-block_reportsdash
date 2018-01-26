<?php

/**
 *
 * @package    reportsdash
 * @copyright  2013 ULCC
 * @author Thomas Wortington
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(dirname(__FILE__).'/../../../config.php');
require_once("$CFG->dirroot/blocks/reportsdash/locallib.php");

function xmldb_block_reportsdash_upgrade($oldversion) {
   global $DB,$CFG;
    $success=true;

    $dirname = "$CFG->dirroot/blocks/reportsdash/reports/";

    foreach (block_reportsdash::find_reports() as $filename=>$classname)
    {
        include_once($filename);

        $reportname=$classname::reportname();

        //Should do nothing if already installed
        if(!$classname::install())
        {
            $success=false;
            break;
        }

        $result = $DB->get_records_sql("SELECT * FROM {block_reportsdash_reports} WHERE name ='$reportname' ");
        if(empty($result))
        {
            // Setup the as-yet-unused table controlling sort order and visibility
            // of reports. Probably going to be removed soon
            $item=new stdClass;
            $item->visible=1;
            $item->sortorder=0;
            $item->name=$reportname;
            $sortorder = count($DB->get_records('block_reportsdash_reports'));
            $item->sortorder = $sortorder+1;

            $DB->insert_record('block_reportsdash_reports',$item);
        }

        if (!$classname::upgrade($oldversion))
        {
            $success = false;
            break;
        }
    }

    // add missing categories that were created prior to adding category events and fix for block_reportsdash_regcats
    if ($oldversion < 2015042400)	{
        global $DB;

        $categories = $DB->get_records('course_categories', array('parent'=>0),'id, name');

        // get unassigned id in regions table
        $unassigned = $DB->get_record('block_reportsdash_region', array('name'=>'Unassigned'),'id');

        if(!empty($categories)){

            foreach($categories as $cat){

                $regcat = $DB->get_record('block_reportsdash_regcats', array('cid'=>$cat->id),'id');

                if(!$regcat){

                    $dataobject = new stdClass();
                    $dataobject->cid = $cat->id;
                    $dataobject->rid = $unassigned->id;

                    $DB->insert_record('block_reportsdash_regcats', $dataobject);
                }
            }
        }
    }

    return $success;
}