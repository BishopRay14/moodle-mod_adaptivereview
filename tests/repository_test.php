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
use mod_adaptivereview\local\repository_manager;
use mod_adaptivereview\local\student_summary;

/**
 * Repository identity and sharing tests.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class repository_test extends \advanced_testcase {

    /**
     * Repository operations preserve sharing, ordering, and archival rules.
     */
    public function test_repository_authoring_operations(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $adminid = (int) get_admin()->id;

        $set = repository_manager::create_set('Church History', 'Reusable material', 4.0, $adminid, 1000);
        $first = repository_manager::create_card(
            $set->id,
            'Who convened Nicaea?',
            'Constantine',
            '',
            'History',
            $adminid,
            1001
        );
        $second = repository_manager::create_card(
            $set->id,
            'When was Nicaea?',
            'AD 325',
            '',
            'History',
            $adminid,
            1002
        );

        repository_manager::move_card($set->id, $second->id, -1);
        $orderedids = $DB->get_fieldset_select(
            'adaptivereview_set_cards',
            'cardid',
            'setid = ?',
            [$set->id],
            'sortorder ASC, id ASC'
        );
        $this->assertSame([(string) $second->id, (string) $first->id], array_map('strval', $orderedids));

        $copy = repository_manager::duplicate_set($set->id, 'Church History Copy', $adminid, 1100);
        $sourceids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?', [$set->id]);
        $copyids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?', [$copy->id]);
        $this->assertCount(2, $copyids);
        $this->assertEmpty(array_intersect($sourceids, $copyids));

        repository_manager::remove_card_from_set($set->id, $first->id);
        $this->assertFalse($DB->record_exists('adaptivereview_set_cards', [
            'setid' => $set->id,
            'cardid' => $first->id,
        ]));
        $this->assertEquals(0, $DB->get_field('adaptivereview_cards', 'archived', ['id' => $first->id]));

        repository_manager::archive_card($first->id, $adminid, 1200);
        $this->assertEquals(1, $DB->get_field('adaptivereview_cards', 'archived', ['id' => $first->id]));
        repository_manager::archive_set($copy->id, $adminid, 1201);
        $this->assertEquals(1, $DB->get_field('adaptivereview_sets', 'archived', ['id' => $copy->id]));
    }

    /**
     * A set attached to an activity cannot be archived.
     */
    public function test_cannot_archive_set_in_use(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $activity = $this->getDataGenerator()->create_module('adaptivereview', ['course' => $course->id]);

        $this->expectException(\moodle_exception::class);
        repository_manager::archive_set((int) $activity->setid, (int) get_admin()->id);
    }

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

    /**
     * Archiving hides a card without destroying mastery or retrieval history.
     */
    public function test_archiving_card_preserves_history(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $activity = $this->getDataGenerator()->create_module('adaptivereview', ['course' => $course->id]);
        $cardids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?',
            [$activity->setid], 'sortorder ASC');
        $card = $DB->get_record('adaptivereview_cards', ['id' => reset($cardids)], '*', MUST_EXIST);

        $session = review_manager::start_session((int) $activity->id, (int) $student->id,
            review_manager::MODE_NEW, 1000000);
        review_manager::record_attempt($session, $card, review_manager::RESPONSE_KNOW, 1000010);
        repository_manager::archive_card((int) $card->id, (int) get_admin()->id, 1000020);

        $this->assertTrue($DB->record_exists('adaptivereview_set_cards', [
            'setid' => $activity->setid,
            'cardid' => $card->id,
        ]));
        $this->assertTrue($DB->record_exists('adaptivereview_mastery', [
            'userid' => $student->id,
            'cardid' => $card->id,
        ]));
        $this->assertEquals(1, $DB->count_records('adaptivereview_sessions'));
        $this->assertEquals(1, $DB->count_records('adaptivereview_attempts'));
        $summary = student_summary::get((int) $activity->id, (int) $student->id, 1000030);
        $this->assertEquals(4, $summary->totalcards);
    }

    /**
     * Students can study but only system repository authors can edit globally.
     */
    public function test_repository_capability_and_student_visibility(): void {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $activity = $this->getDataGenerator()->create_module('adaptivereview', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('adaptivereview', $activity->id, $course->id, false, MUST_EXIST);
        $modulecontext = \context_module::instance($cm->id);
        $systemcontext = \context_system::instance();

        $this->assertTrue(has_capability('mod/adaptivereview:view', $modulecontext, $student));
        $this->assertFalse(has_capability('moodle/course:manageactivities', $modulecontext, $student));
        $this->assertFalse(has_capability('mod/adaptivereview:manageglobalrepository', $systemcontext, $student));
        $this->assertTrue(has_capability('moodle/course:manageactivities', $modulecontext, $teacher));
        $this->assertFalse(has_capability('mod/adaptivereview:manageglobalrepository', $systemcontext, $teacher));

        $manager = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
        role_assign($managerrole->id, $manager->id, $systemcontext->id);
        $this->assertTrue(has_capability('mod/adaptivereview:manageglobalrepository', $systemcontext, $manager));
    }
}
