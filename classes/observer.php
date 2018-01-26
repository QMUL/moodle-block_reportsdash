<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Observer class for reportsdash
 *
 * @package    block_reportsdash
 * @copyright  2015 University of London Computer Centre
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/blocks/reportsdash/locallib.php');

/**
 * Event handler for course categories in reportsdash plugin.
 */
class block_reportsdash_observer {

    // triggered when new course category is created
    public static function create_category(core\event\course_category_created $event){

          update_categories($event->objectid);

    }

    // triggered when course category is updated
    public static function update_categories(core\event\course_category_updated $event){

        update_categories($event->objectid);

    }

    // triggered when course category is deleted
    public static function remove_category(core\event\course_category_deleted $event){

        update_deleted_cids($event->objectid);

    }

}