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

namespace mod_adaptivereview;

use mod_adaptivereview\local\review_manager;

/**
 * Repository identity and sharing tests.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class repository_test extends \advanced_testcase {

    /**
     * A shared card keeps one mastery row when used by multiple activities.
     */
    public function test_mastery_follows_shared_card_across_activities(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        $firstactivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'Shared card source',
            'masterygoal' => 3.0,
        ]);
        $secondactivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'Shared card consumer',
            'masterygoal' => 4.5,
        ]);

        $cardids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?',
            [$firstactivity->setid]);
        $cardid = reset($cardids);
        $this->assertNotFalse($cardid);
        $card = $DB->get_record('adaptivereview_cards', ['id' => $cardid], '*', MUST_EXIST);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $secondactivity->setid,
            'cardid' => $cardid,
            'sortorder' => 99,
            'timecreated' => time(),
        ]);

        $session = review_manager::start_session(
            (int) $firstactivity->id,
            (int) $student->id,
            review_manager::MODE_NEW,
            1000000
        );
        review_manager::record_attempt($session, $card, review_manager::RESPONSE_KNOW, 1000010);

        $this->assertEquals(1, $DB->count_records('adaptivereview_mastery', [
            'userid' => $student->id,
            'cardid' => $cardid,
        ]));
        $this->assertTrue($DB->record_exists('adaptivereview_set_cards', [
            'setid' => $firstactivity->setid,
            'cardid' => $cardid,
        ]));
        $this->assertTrue($DB->record_exists('adaptivereview_set_cards', [
            'setid' => $secondactivity->setid,
            'cardid' => $cardid,
        ]));

        $this->assertEquals(3.0, (float) $firstactivity->masterygoal);
        $this->assertEquals(4.5, (float) $secondactivity->masterygoal);
    }
}
