<?php /**
* Version details
*
* @package    reportsdash
* @copyright  2013 ULCC
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

defined('MOODLE_INTERNAL') || die;

$plugin->version   = 2016030104;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2014111000;        // Requires this Moodle version
$plugin->component = 'block_reportsdash'; // Full name of the plugin (used for diagnostics)
$plugin->cron      = 300;               //Check my kids' crons every five minutes
