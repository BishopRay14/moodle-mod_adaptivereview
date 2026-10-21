<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Student dashboard page for mod_adaptivereview.
 *
 * Uses the Moodle Output API and the bundled JavaScript application.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once(__DIR__ . '/lib.php');

$id = required_param('id', PARAM_INT); // Course module ID
$cm = get_coursemodule_from_id('adaptivereview', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$adaptivereview = $DB->get_record('adaptivereview', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);

// Ensure the user has the required capability.
require_capability('mod/adaptivereview:view', $context);

// Generate modern page layout.
$PAGE->set_url('/mod/adaptivereview/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($adaptivereview->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Export strings for Vue frontend
$vuestrings = [
    'dashboardtitle', 'dashboardsbtitle', 'howitworks', 'cards', 'systemtitle',
    'systemintro', 'known_btn', 'known_desc', 'notyet_btn', 'notyet_desc',
    'systemtip', 'gotit', 'showhint', 'hint',
    'taptoflip', 'action_back', 'action_stay', 'action_next', 'backtodashboard',
    'cardxofy_x', 'cardxofy_y', 'loadingcards', 'sessiondone', 'sessiondonedesc',
    'completed', 'error_loading_cards', 'error_saving_answer', 'box0', 'box1', 'box2', 'box3', 'box4', 'box5', 'queue_due', 'queue_new', 'queue_recent', 'queue_all',
    'newcardaccess', 'newcardaccessdue',
    'reset_progress', 'reset_progress_confirm_title', 'reset_progress_confirm_msg',
    'reset_progress_btn', 'reset_progress_cancel', 'reset_progress_done',
    'progress_label', 'progress_aria',
    'set_mastery', 'progress_toward_goal', 'cards_at_goal', 'goal',
    'feedback_grand_title', 'feedback_grand_desc', 'feedback_perfect_title', 'feedback_perfect_desc',
    'feedback_good_title', 'feedback_good_desc', 'feedback_okay_title', 'feedback_okay_desc',
    'feedback_learn_title', 'feedback_learn_desc',
];
$PAGE->requires->strings_for_js($vuestrings, 'mod_adaptivereview');

// Fire the course_module_viewed event (required for Moodle activity logging).
$event = \mod_adaptivereview\event\course_module_viewed::create([
    'objectid' => $adaptivereview->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('adaptivereview', $adaptivereview);
$event->trigger();

// Update completion state for 'view' condition.
require_once($CFG->libdir . '/completionlib.php');
$completion = new \completion_info($course);
if ($completion->is_enabled($cm)) {
    $completion->set_module_viewed($cm);
}

echo $OUTPUT->header();

// Display intro if present.
if (!empty(trim($adaptivereview->intro))) {
    echo $OUTPUT->box(format_module_intro('adaptivereview', $adaptivereview, $cm->id), 'generalbox mod_introbox', 'intro');
}

// Pass parameters to the Vue app via a data attribute.
$appdata = [
    'wwwroot'    => $CFG->wwwroot,
    'sesskey'    => sesskey(),
    'instanceid' => (int)$adaptivereview->id,
    'cmid'       => (int)$cm->id,
    'masterygoal' => (float)$adaptivereview->masterygoal,
];

// Mount point for Vue
echo \html_writer::tag('div', '', [
    'id' => 'v-app-mod-adaptivereview',
    'data-config' => json_encode($appdata),
]);

// Include Vue build scripts manually with cache-busting
if (file_exists(__DIR__ . '/dist/assets/index.js')) {
    $jsmtime = filemtime(__DIR__ . '/dist/assets/index.js');
    $jsurl = new \moodle_url('/mod/adaptivereview/dist/assets/index.js', ['v' => $jsmtime]);
    echo '<script type="module" crossorigin src="' . $jsurl->out() . '"></script>';
} else {
    echo \html_writer::tag('p', get_string('frontendnotfound', 'mod_adaptivereview'), ['class' => 'alert alert-warning mt-3']);
}

if (file_exists(__DIR__ . '/dist/assets/index.css')) {
    $cssmtime = filemtime(__DIR__ . '/dist/assets/index.css');
    $cssurl = new \moodle_url('/mod/adaptivereview/dist/assets/index.css', ['v' => $cssmtime]);
    echo '<link rel="stylesheet" href="' . $cssurl->out() . '">';
}

echo $OUTPUT->footer();
