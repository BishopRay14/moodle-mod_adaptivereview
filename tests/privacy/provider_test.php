<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_adaptivereview\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use mod_adaptivereview\local\review_manager;

/**
 * Privacy provider tests for global mastery and activity history.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {

    /**
     * Create shared global mastery and activity-specific history.
     *
     * @return array
     */
    private function create_scenario(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $first = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'First activity',
        ]);
        $second = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'Second activity',
            'setid' => $first->setid,
        ]);
        $cardids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid',
            'setid = ?', [$first->setid], 'sortorder ASC');
        $cardid = (int) reset($cardids);
        $card = $DB->get_record('adaptivereview_cards', ['id' => $cardid], '*', MUST_EXIST);
        $DB->set_field('adaptivereview_cards', 'createdby', $student->id, ['id' => $cardid]);
        $DB->set_field('adaptivereview_cards', 'modifiedby', $student->id, ['id' => $cardid]);
        $DB->set_field('adaptivereview_sets', 'createdby', $student->id, ['id' => $first->setid]);
        $DB->set_field('adaptivereview_sets', 'modifiedby', $student->id, ['id' => $first->setid]);

        $session = review_manager::start_session((int) $first->id, (int) $student->id,
            review_manager::MODE_NEW, 1000000);
        review_manager::record_attempt($session, $card, review_manager::RESPONSE_KNOW, 1000010);
        review_manager::start_session((int) $second->id, (int) $student->id,
            review_manager::MODE_EARLY, 1000020);

        $firstcm = get_coursemodule_from_instance('adaptivereview', $first->id, $course->id, false, MUST_EXIST);
        $secondcm = get_coursemodule_from_instance('adaptivereview', $second->id, $course->id, false, MUST_EXIST);

        return [$student, $first, $second, $card, $firstcm, $secondcm];
    }

    /**
     * Global mastery is disclosed at system context and history at module context.
     */
    public function test_contexts_and_exports_follow_data_ownership(): void {
        $this->resetAfterTest();
        [$student, $first, $second, $card, $firstcm, $secondcm] = $this->create_scenario();

        $contextlist = provider::get_contexts_for_userid((int) $student->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains(\context_system::instance()->id, $contextids);
        $this->assertContains(\context_module::instance($firstcm->id)->id, $contextids);
        $this->assertContains(\context_module::instance($secondcm->id)->id, $contextids);

        $approved = new approved_contextlist($student, 'mod_adaptivereview', $contextids);
        provider::export_user_data($approved);
        $this->assertTrue(writer::with_context(\context_system::instance())->has_any_data());
        $this->assertTrue(writer::with_context(\context_module::instance($firstcm->id))->has_any_data());
    }

    /**
     * Erasing one activity removes its history without erasing lifelong mastery.
     */
    public function test_module_deletion_preserves_global_mastery(): void {
        global $DB;

        $this->resetAfterTest();
        [$student, $first, $second, $card, $firstcm] = $this->create_scenario();

        provider::delete_data_for_all_users_in_context(\context_module::instance($firstcm->id));

        $this->assertTrue($DB->record_exists('adaptivereview_mastery', [
            'userid' => $student->id,
            'cardid' => $card->id,
        ]));
        $this->assertFalse($DB->record_exists('adaptivereview_sessions', [
            'adaptivereviewid' => $first->id,
        ]));
        $this->assertEquals(0, $DB->count_records('adaptivereview_attempts'));
        $this->assertTrue($DB->record_exists('adaptivereview_sessions', [
            'adaptivereviewid' => $second->id,
            'userid' => $student->id,
        ]));
    }

    /**
     * Erasing system-context data removes mastery and anonymises authorship.
     */
    public function test_system_deletion_preserves_repository_content(): void {
        global $DB;

        $this->resetAfterTest();
        [$student, $first, $second, $card] = $this->create_scenario();
        $approved = new approved_contextlist($student, 'mod_adaptivereview', [
            \context_system::instance()->id,
        ]);

        provider::delete_data_for_user($approved);

        $this->assertFalse($DB->record_exists('adaptivereview_mastery', ['userid' => $student->id]));
        $this->assertTrue($DB->record_exists('adaptivereview_cards', ['id' => $card->id]));
        $this->assertTrue($DB->record_exists('adaptivereview_sets', ['id' => $first->setid]));
        $this->assertEquals(0, $DB->get_field('adaptivereview_cards', 'createdby', ['id' => $card->id]));
        $this->assertEquals(0, $DB->get_field('adaptivereview_cards', 'modifiedby', ['id' => $card->id]));
        $this->assertEquals(0, $DB->get_field('adaptivereview_sets', 'createdby', ['id' => $first->setid]));
        $this->assertEquals(0, $DB->get_field('adaptivereview_sets', 'modifiedby', ['id' => $first->setid]));
        $this->assertEquals(2, $DB->count_records('adaptivereview_sessions', ['userid' => $student->id]));
    }

    /**
     * Bulk system-context erasure follows the same global-data policy.
     */
    public function test_system_userlist_deletion_removes_global_data(): void {
        global $DB;

        $this->resetAfterTest();
        [$student, $first, $second, $card] = $this->create_scenario();
        $approved = new approved_userlist(
            \context_system::instance(),
            'mod_adaptivereview',
            [$student->id]
        );

        provider::delete_data_for_users($approved);

        $this->assertFalse($DB->record_exists('adaptivereview_mastery', ['userid' => $student->id]));
        $this->assertEquals(0, $DB->get_field('adaptivereview_cards', 'createdby', ['id' => $card->id]));
        $this->assertEquals(0, $DB->get_field('adaptivereview_sets', 'createdby', ['id' => $first->setid]));
        $this->assertEquals(2, $DB->count_records('adaptivereview_sessions', ['userid' => $student->id]));
    }
}
