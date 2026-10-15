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
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class backup_adaptivereview_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {

        // Define each element separated.
        $adaptivereview = new backup_nested_element('adaptivereview', ['id'], [
            'course', 'name', 'intro', 'introformat', 'masterygoal', 'itemorder',
            'completion_min_items', 'completion_min_mastered', 'completion_all_mastered', 'timecreated', 'timemodified'
        ]);

        $items = new backup_nested_element('items');
        $item = new backup_nested_element('item', ['id'], [
            'question', 'answer', 'hint', 'category', 'sortorder'
        ]);

        $masteries = new backup_nested_element('masteries');
        $mastery = new backup_nested_element('mastery', ['id'], [
            'userid', 'box_number', 'status', 'count_correct', 'count_wrong', 'last_reviewed',
            'masteryscore', 'requiredintervaldays', 'nextreviewdate', 'lastreviewdate'
        ]);

        $sessions = new backup_nested_element('sessions');
        $session = new backup_nested_element('session', ['id'], [
            'userid', 'mode', 'timecreated', 'timecompleted'
        ]);
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'cardid', 'attemptnumber', 'response', 'wasdue', 'isremediation',
            'masteryeligible', 'masterybefore', 'masterygain', 'masteryafter',
            'intervalbefore', 'intervalafter', 'previousreviewdate', 'previousduedate',
            'nextreviewdate', 'algorithmversion', 'timecreated'
        ]);

        // Build the tree.
        $adaptivereview->add_child($items);
        $items->add_child($item);

        $item->add_child($masteries);
        $masteries->add_child($mastery);
        $adaptivereview->add_child($sessions);
        $sessions->add_child($session);
        $session->add_child($attempts);
        $attempts->add_child($attempt);

        // Define sources.
        $adaptivereview->set_source_table('adaptivereview', ['id' => backup::VAR_ACTIVITYID]);
        $item->set_source_sql(
            "SELECT c.*, sc.sortorder
               FROM {adaptivereview_cards} c
               JOIN {adaptivereview_set_cards} sc ON sc.cardid = c.id
               JOIN {adaptivereview} ar ON ar.setid = sc.setid
              WHERE ar.id = ?",
            [backup::VAR_ACTIVITYID]
        );

        // Export user progress only if userinfo is included in the backup.
        if ($this->get_setting_value('userinfo')) {
            $mastery->set_source_table('adaptivereview_mastery', ['cardid' => backup::VAR_PARENTID]);
            $session->set_source_table('adaptivereview_sessions', ['adaptivereviewid' => backup::VAR_PARENTID]);
            $attempt->set_source_table('adaptivereview_attempts', ['sessionid' => backup::VAR_PARENTID]);
        }

        // Define id annotations to ensure accurate restoration of users and contexts.
        $mastery->annotate_ids('user', 'userid');
        $session->annotate_ids('user', 'userid');

        // Annotate file areas (intro text files).
        $adaptivereview->annotate_files('mod_adaptivereview', 'intro', null); // Intro might have embedded images

        return $this->prepare_activity_structure($adaptivereview);
    }
}
