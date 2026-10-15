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
 * Library unit tests for mod_adaptivereview.
 *
 * @package   mod_adaptivereview
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivereview;

defined('MOODLE_INTERNAL') || die();

/**
 * Library unit tests for mod_adaptivereview.
 */
final class lib_test extends \advanced_testcase {

    public static function setUpBeforeClass(): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/adaptivereview/lib.php');
        parent::setUpBeforeClass();
    }

    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test adding, updating, and deleting an instance.
     */
    public function test_instance_lifecycle() {
        global $DB;

        $course = $this->getDataGenerator()->create_course();

        // 1. Test Add
        $adaptivereview = new \stdClass();
        $adaptivereview->course = $course->id;
        $adaptivereview->name = 'Adaptive Review Test Activity';
        $adaptivereview->intro = 'Intro text';
        $adaptivereview->introformat = FORMAT_HTML;
        $adaptivereview->itemorder = 0;

        $module = $this->getDataGenerator()->create_module('adaptivereview', (array)$adaptivereview);
        $this->assertNotEmpty($module->id);

        // Verify demo cards were created by our custom add_instance logic.
        $cardcount = $DB->count_records('adaptivereview_set_cards', ['setid' => $module->setid]);
        $this->assertEquals(5, $cardcount); // add_instance inserts 5 demo cards

        // 2. Test Update
        $module->name = 'Updated Adaptive Review Name';
        $module->instance = $module->id;
        $result = adaptivereview_update_instance($module);
        $this->assertTrue($result);

        $updated = $DB->get_record('adaptivereview', ['id' => $module->id]);
        $this->assertEquals('Updated Adaptive Review Name', $updated->name);

        // Create retained review history so deletion covers every user-data table.
        $cards = $DB->get_records(
            'adaptivereview_cards',
            [],
            'id ASC',
            '*',
            0,
            1
        );
        $card = reset($cards);
        $this->assertNotFalse($card);
        $session = \mod_adaptivereview\local\review_manager::start_session(
            (int) $module->id,
            (int) get_admin()->id,
            \mod_adaptivereview\local\review_manager::MODE_NEW
        );
        \mod_adaptivereview\local\review_manager::record_attempt(
            $session,
            $card,
            \mod_adaptivereview\local\review_manager::RESPONSE_KNOW
        );

        // 3. Test Delete
        $result = adaptivereview_delete_instance($module->id);
        $this->assertTrue($result);

        $deleted = $DB->get_record('adaptivereview', ['id' => $module->id]);
        $this->assertFalse($deleted);

        // Global cards and lifelong mastery survive course activity deletion.
        $this->assertEquals(5, $DB->count_records('adaptivereview_cards'));
        $this->assertEquals(5, $DB->count_records('adaptivereview_set_cards', ['setid' => $module->setid]));
        $this->assertEquals(1, $DB->get_field('adaptivereview_sets', 'archived', ['id' => $module->setid]));
        $this->assertEquals(1, $DB->count_records('adaptivereview_mastery'));
        $this->assertEquals(0, $DB->count_records('adaptivereview_attempts'));
        $this->assertEquals(0, $DB->count_records('adaptivereview_sessions'));
    }
}
