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
 * Activity restore structure for Adaptive Review.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Restores an activity into an isolated repository set.
 */
class restore_adaptivereview_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define restore paths for activity and optional user data.
     *
     * @return array Restore path elements.
     */
    protected function define_structure() {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('adaptivereview', '/activity/adaptivereview');
        $paths[] = new restore_path_element('adaptivereview_item', '/activity/adaptivereview/items/item');

        if ($userinfo) {
            $paths[] = new restore_path_element('adaptivereview_mastery', '/activity/adaptivereview/items/item/masteries/mastery');
            $paths[] = new restore_path_element('adaptivereview_session', '/activity/adaptivereview/sessions/session');
            $paths[] = new restore_path_element(
                'adaptivereview_attempt',
                '/activity/adaptivereview/sessions/session/attempts/attempt'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restore the activity record and its isolated set.
     *
     * @param array $data Decoded backup data.
     */
    protected function process_adaptivereview($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        // Any changes to the list of dates that needs to be rolled should be happening here.
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Restores intentionally create an isolated imported set. Text matches
        // never connect restored activities to unrelated repository content.
        $now = time();
        $data->setid = $DB->insert_record('adaptivereview_sets', (object) [
            'name' => $data->name,
            'description' => $data->intro ?? '',
            'descriptionformat' => $data->introformat ?? FORMAT_HTML,
            'defaultmasterygoal' => $data->masterygoal ?? 4.0,
            'archived' => 0,
            'createdby' => 0,
            'modifiedby' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // insert the adaptivereview record.
        $newitemid = $DB->insert_record('adaptivereview', $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restore a repository card and set membership.
     *
     * @param array $data Decoded backup data.
     */
    protected function process_adaptivereview_item($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $activityid = $this->get_new_parentid('adaptivereview');
        $setid = $DB->get_field('adaptivereview', 'setid', ['id' => $activityid], MUST_EXIST);
        $sortorder = $data->sortorder ?? 0;
        unset($data->sortorder);
        // Preserve archival state so hidden repository content does not become
        // student-visible merely because an activity was restored.
        $data->archived = (int) ($data->archived ?? 0);
        $data->createdby = 0;
        $data->modifiedby = 0;
        $data->timecreated = time();
        $data->timemodified = time();

        $newcardid = $DB->insert_record('adaptivereview_cards', $data);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $setid,
            'cardid' => $newcardid,
            'sortorder' => $sortorder,
            'timecreated' => time(),
        ]);
        $this->set_mapping('adaptivereview_item', $oldid, $newcardid);
        $this->set_mapping('adaptivereview_cards', $oldid, $newcardid);
    }

    /**
     * Restore a student's lifelong mastery record.
     *
     * @param array $data Decoded backup data.
     */
    protected function process_adaptivereview_mastery($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Ensure user ID mapping is found
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return; // Don't restore if the user isn't found
        }

        $data->cardid = $this->get_new_parentid('adaptivereview_item');
        $data->last_reviewed = $this->apply_date_offset($data->last_reviewed);
        $data->nextreviewdate = $this->apply_date_offset($data->nextreviewdate);
        $data->lastreviewdate = $this->apply_date_offset($data->lastreviewdate);

        $newmasteryid = $DB->insert_record('adaptivereview_mastery', $data);
        $this->set_mapping('adaptivereview_mastery', $oldid, $newmasteryid);
    }

    /**
     * Restore a review session.
     *
     * @param array $data Decoded backup data.
     */
    protected function process_adaptivereview_session($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->adaptivereviewid = $this->get_new_parentid('adaptivereview');
        $data->userid = $this->get_mappingid('user', $data->userid);
        if (!$data->userid) {
            return;
        }

        $data->sessionkey = bin2hex(random_bytes(32));
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timecompleted = $this->apply_date_offset($data->timecompleted);

        $newsessionid = $DB->insert_record('adaptivereview_sessions', $data);
        $this->set_mapping('adaptivereview_session', $oldid, $newsessionid);
        $this->set_mapping('adaptivereview_sessions', $oldid, $newsessionid);
    }

    /**
     * Restore an append-only attempt record.
     *
     * @param array $data Decoded backup data.
     */
    protected function process_adaptivereview_attempt($data) {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;
        $data->sessionid = $this->get_new_parentid('adaptivereview_session');
        $data->cardid = $this->get_mappingid('adaptivereview_cards', $data->cardid);
        if (!$data->sessionid || !$data->cardid) {
            return;
        }

        $data->previousreviewdate = $this->apply_date_offset($data->previousreviewdate);
        $data->previousduedate = $this->apply_date_offset($data->previousduedate);
        $data->nextreviewdate = $this->apply_date_offset($data->nextreviewdate);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        $newattemptid = $DB->insert_record('adaptivereview_attempts', $data);
        $this->set_mapping('adaptivereview_attempts', $oldid, $newattemptid);
    }

    /**
     * Restore files after database records exist.
     */
    protected function after_execute() {
        // Add adaptivereview related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_adaptivereview', 'intro', null);
    }
}
