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

global $CFG;
require_once($CFG->dirroot . '/mod/adaptivereview/backup/moodle2/restore_adaptivereview_stepslib.php');

class restore_adaptivereview_activity_task extends restore_activity_task {

    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    protected function define_my_steps() {
        // Add the adaptivereview structure step.
        $this->add_step(new restore_adaptivereview_activity_structure_step('adaptivereview_structure', 'adaptivereview.xml'));
    }

    static public function define_decode_contents() {
        $contents = [];
        $contents[] = new restore_decode_content('adaptivereview', ['intro'], 'adaptivereview');
        $contents[] = new restore_decode_content('adaptivereview_cards', ['question', 'answer', 'hint'],
            'adaptivereview_cards');
        return $contents;
    }

    static public function define_decode_rules() {
        $rules = [];
        // New rules for Adaptive Review backups
        $rules[] = new restore_decode_rule('ADAPTIVEREVIEWINDEX', '/mod/adaptivereview/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('ADAPTIVEREVIEWVIEWBYID', '/mod/adaptivereview/view.php?id=$1', 'course_module');
        // Old rules for compatibility with pre-v1.0.0 Smartcards backups
        $rules[] = new restore_decode_rule('SMARTCARDSINDEX', '/mod/adaptivereview/index.php?id=$1', 'course');
        $rules[] = new restore_decode_rule('SMARTCARDSVIEWBYID', '/mod/adaptivereview/view.php?id=$1', 'course_module');
        return $rules;
    }

    static public function define_restore_log_rules() {
        $rules = [];
        $rules[] = new restore_log_rule('adaptivereview', 'add', 'view.php?id={course_module}', '{adaptivereview}');
        $rules[] = new restore_log_rule('adaptivereview', 'update', 'view.php?id={course_module}', '{adaptivereview}');
        $rules[] = new restore_log_rule('adaptivereview', 'view', 'view.php?id={course_module}', '{adaptivereview}');
        return $rules;
    }

    static public function define_restore_log_rules_for_course() {
        $rules = [];
        $rules[] = new restore_log_rule('adaptivereview', 'view all', 'index.php?id={course}', null);
        return $rules;
    }
}
