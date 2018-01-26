<?php
/**
* Version details
*
* @package    reportsdash
* @copyright  2015 ULCC, University of London
* @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
*/

 

defined('MOODLE_INTERNAL') || die();

$observers = array(

    array (
        'eventname' => 'core\event\course_category_created',
        'callback'  => 'block_reportsdash_observer::create_category',
    ),

    array (
        'eventname' => 'core\event\course_category_updated',
        'callback'  => 'block_reportsdash_observer::update_categories',
    ),

    array (
        'eventname' => 'core\event\course_category_deleted',
        'callback'  => 'block_reportsdash_observer::remove_category',
    ),

);
