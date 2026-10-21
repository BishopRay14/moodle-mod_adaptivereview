<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_adaptivereview;

use mod_adaptivereview\local\review_manager;

/**
 * Backup and restore coverage for repository-backed activities.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_restore_test extends \advanced_testcase {

    /**
     * Restore creates an isolated set, preserves archives, and excludes
     * mastery belonging only to another activity that shares the card.
     */
    public function test_activity_backup_restores_bounded_repository_data(): void {
        global $CFG, $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');

        $sourcecourse = $this->getDataGenerator()->create_course();
        $destinationcourse = $this->getDataGenerator()->create_course();
        $includedstudent = $this->getDataGenerator()->create_user();
        $otherstudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($includedstudent->id, $sourcecourse->id, 'student');
        $this->getDataGenerator()->enrol_user($otherstudent->id, $sourcecourse->id, 'student');

        $source = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $sourcecourse->id,
            'name' => 'Backup source',
        ]);
        $otheractivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $sourcecourse->id,
            'name' => 'Other shared activity',
            'setid' => $source->setid,
        ]);
        $cardids = array_values($DB->get_fieldset_select('adaptivereview_set_cards', 'cardid',
            'setid = ?', [$source->setid], 'sortorder ASC'));
        $reviewcard = $DB->get_record('adaptivereview_cards', ['id' => $cardids[0]], '*', MUST_EXIST);
        $archivedcardid = (int) $cardids[1];
        $DB->set_field('adaptivereview_cards', 'question', 'Backup identity card', ['id' => $reviewcard->id]);
        $reviewcard->question = 'Backup identity card';
        $DB->set_field('adaptivereview_cards', 'archived', 1, ['id' => $archivedcardid]);

        $includedsession = review_manager::start_session((int) $source->id, (int) $includedstudent->id,
            review_manager::MODE_NEW, 1000000);
        review_manager::record_attempt($includedsession, $reviewcard, review_manager::RESPONSE_KNOW, 1000010);
        $othersession = review_manager::start_session((int) $otheractivity->id, (int) $otherstudent->id,
            review_manager::MODE_NEW, 1000020);
        review_manager::record_attempt($othersession, $reviewcard, review_manager::RESPONSE_KNOW, 1000030);

        $CFG->backup_file_logger_level = \backup::LOG_NONE;
        $backup = new \backup_controller(
            \backup::TYPE_1ACTIVITY,
            $source->cmid,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backup->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $backup->get_plan()->get_setting('users')->set_value(true);
        $backup->get_plan()->get_setting('adaptivereview_' . $source->cmid . '_userinfo')
            ->set_status(\backup_setting::NOT_LOCKED);
        $backup->get_plan()->get_setting('adaptivereview_' . $source->cmid . '_userinfo')->set_value(true);
        $backupid = $backup->get_backupid();
        $backup->execute_plan();
        $backup->destroy();

        $restore = new \restore_controller(
            $backupid,
            $destinationcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_CURRENT_ADDING
        );
        $restore->get_plan()->get_setting('users')->set_status(\backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('users')->set_value(true);
        $restore->get_plan()->get_setting('adaptivereview_' . $source->cmid . '_userinfo')
            ->set_status(\backup_setting::NOT_LOCKED);
        $restore->get_plan()->get_setting('adaptivereview_' . $source->cmid . '_userinfo')->set_value(true);
        $this->assertTrue($restore->execute_precheck());
        $restore->execute_plan();
        $restore->destroy();

        $restored = $DB->get_record('adaptivereview', ['course' => $destinationcourse->id], '*', MUST_EXIST);
        $this->assertNotEquals($source->setid, $restored->setid);
        $this->assertEquals(5, $DB->count_records('adaptivereview_set_cards', ['setid' => $restored->setid]));
        $this->assertEquals(1, $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {adaptivereview_set_cards} sc
               JOIN {adaptivereview_cards} c ON c.id = sc.cardid
              WHERE sc.setid = :setid AND c.archived = 1",
            ['setid' => $restored->setid]
        ));

        $restoredcardid = (int) $DB->get_field_sql(
            "SELECT c.id
               FROM {adaptivereview_cards} c
               JOIN {adaptivereview_set_cards} sc ON sc.cardid = c.id
              WHERE sc.setid = :setid AND c.question = :question",
            ['setid' => $restored->setid, 'question' => 'Backup identity card'],
            MUST_EXIST
        );
        $this->assertTrue($DB->record_exists('adaptivereview_mastery', [
            'userid' => $includedstudent->id,
            'cardid' => $restoredcardid,
        ]));
        $this->assertFalse($DB->record_exists('adaptivereview_mastery', [
            'userid' => $otherstudent->id,
            'cardid' => $restoredcardid,
        ]));
        $this->assertTrue($DB->record_exists('adaptivereview_sessions', [
            'adaptivereviewid' => $restored->id,
            'userid' => $includedstudent->id,
        ]));
        $this->assertEquals(1, $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {adaptivereview_attempts} a
               JOIN {adaptivereview_sessions} s ON s.id = a.sessionid
              WHERE s.adaptivereviewid = :activityid",
            ['activityid' => $restored->id]
        ));
    }
}
