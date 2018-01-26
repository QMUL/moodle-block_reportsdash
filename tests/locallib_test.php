<?php
/**
 * Unit tests for (some of) mod/assign/lib.php.
 *
 * @package    mod_assign
 * @category   phpunit
 * @copyright  2015 ULCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once(__DIR__.'/../locallib.php');
require_once("$CFG->dirroot/mod/assign/tests/base_test.php");

class block_reportsdash_locallib_testcase extends basic_testcase
{
    public function setUp()
    {
        parent::setUp();
    }

    public function test_Reportdash_field_info()
    {
        $item=new Reportdash_field_info('Fred',
                                        PARAM_INT,
                                        'A description',
                                        999,
                                        '/\d+/',
                                        true);

        //        $this->assertEquals(999,$item->value());
        $this->assertEquals(true,$item->valid());
    }
}