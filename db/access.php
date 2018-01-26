<?php
/*
* @package    reportsdash
* @copyright  2013 ULCC
* @author Thomas Wortington
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

/**
 * Capabilities
 *
 * @package    reportsdash
 * @copyright  2013 ULCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $DB,$CFG;

require_once(__DIR__ . '/../locallib.php');

$capabilities = array();

$capabilities = array(
    'block/reportsdash:configure_reportsdash' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array('manager' => CAP_ALLOW)
    ),
    'block/reportsdash:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'user' => CAP_ALLOW
        ),


        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    ),
    'block/reportsdash:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'user' => CAP_ALLOW
        ),


        'clonepermissionsfrom' => 'moodle/site:manageblocks'
    )
);