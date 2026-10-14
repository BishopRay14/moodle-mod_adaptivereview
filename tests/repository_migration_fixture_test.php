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
 * Establishes the complete legacy-shaped fixture required by repository tests.
 *
 * This test deliberately describes the pre-repository data model. Step 2 will
 * run the repository migration against the same relationships and add the
 * post-migration assertions documented in the migration contract.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class repository_migration_fixture_test extends \advanced_testcase {

    /**
     * Verify that the migration fixture contains every protected relationship.
     */
    public function test_legacy_fixture_is_complete(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $firststudent = $this->getDataGenerator()->create_user();
        $secondstudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($firststudent->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($secondstudent->id, $course->id, 'student');

        $firstactivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'First legacy activity',
        ]);
        $secondactivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'Second legacy activity',
        ]);

        // Replace generated demo content with two deliberately similar legacy cards.
        $DB->delete_records('adaptivereview_items', ['adaptivereviewid' => $firstactivity->id]);
        $DB->delete_records('adaptivereview_items', ['adaptivereviewid' => $secondactivity->id]);

        $firstcard = (object) [
            'adaptivereviewid' => $firstactivity->id,
            'question' => 'Shared wording must not imply shared identity.',
            'answer' => 'First activity answer',
            'hint' => '',
            'category' => 'Migration fixture',
        ];
        $firstcard->id = $DB->insert_record('adaptivereview_items', $firstcard);

        $secondcard = (object) [
            'adaptivereviewid' => $secondactivity->id,
            'question' => 'Shared wording must not imply shared identity.',
            'answer' => 'Second activity answer',
            'hint' => '',
            'category' => 'Migration fixture',
        ];
        $secondcard->id = $DB->insert_record('adaptivereview_items', $secondcard);

        $firstsession = review_manager::start_session(
            (int) $firstactivity->id,
            (int) $firststudent->id,
            review_manager::MODE_NEW,
            1000000
        );
        review_manager::record_attempt(
            $firstsession,
            $firstcard,
            review_manager::RESPONSE_KNOW,
            1000010
        );

        $secondsession = review_manager::start_session(
            (int) $secondactivity->id,
            (int) $secondstudent->id,
            review_manager::MODE_NEW,
            2000000
        );
        review_manager::record_attempt(
            $secondsession,
            $secondcard,
            review_manager::RESPONSE_NOTYET,
            2000010
        );

        $this->assertEquals(2, $DB->count_records('adaptivereview', ['course' => $course->id]));
        $this->assertEquals(2, $DB->count_records('adaptivereview_items'));
        $this->assertEquals(2, $DB->count_records('adaptivereview_mastery'));
        $this->assertEquals(2, $DB->count_records('adaptivereview_sessions'));
        $this->assertEquals(2, $DB->count_records('adaptivereview_attempts'));

        $this->assertEquals(1, $DB->count_records('adaptivereview_mastery', ['userid' => $firststudent->id]));
        $this->assertEquals(1, $DB->count_records('adaptivereview_mastery', ['userid' => $secondstudent->id]));

        $this->assertNotEquals($firstcard->id, $secondcard->id);
        $this->assertEquals($firstcard->question, $secondcard->question);

        $sql = "SELECT COUNT(1)
                  FROM {adaptivereview_attempts} a
                  JOIN {adaptivereview_sessions} s ON s.id = a.sessionid
                  JOIN {adaptivereview_items} i ON i.id = a.itemid
                 WHERE s.adaptivereviewid = i.adaptivereviewid";
        $this->assertEquals(2, $DB->count_records_sql($sql));

        foreach ([[$firststudent, $firstcard], [$secondstudent, $secondcard]] as [$student, $card]) {
            $this->assertTrue($DB->record_exists('adaptivereview_mastery', [
                'userid' => $student->id,
                'itemid' => $card->id,
            ]));
            $this->assertTrue($DB->record_exists('adaptivereview_attempts', [
                'itemid' => $card->id,
            ]));
        }
    }
}
