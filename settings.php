<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 
global $CFG;

$settings->add(new admin_setting_configcheckbox('block_reportsdash/useheavyhost',get_string('useheavydutydb','block_reportsdash'),'','',PARAM_BOOL));

//$settings->add(new admin_setting_configtext('block_reportsdash/heavyhost',get_string('heavydutydb','block_reportsdash'),'','',PARAM_CLEAN));
//$settings->add(new admin_setting_configtext('block_reportsdash/heavyuser','Heavy-duty DB username','','',PARAM_CLEAN));
//$settings->add(new admin_setting_configtext('block_reportsdash/heavypass','Heavy-duty DB password','','',PARAM_CLEAN));