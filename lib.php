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
 * Core callbacks for the Adaptive Review activity module.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Adds a new adaptivereview instance
 *
 * @param stdClass $adaptivereview
 * @return int The instance ID
 */
function adaptivereview_add_instance($adaptivereview) {
    global $DB, $USER;
    $transaction = $DB->start_delegated_transaction();
    $now = time();

    $setid = (int) ($adaptivereview->setid ?? 0);
    $createset = $setid === 0;
    if ($createset) {
        $setid = $DB->insert_record('adaptivereview_sets', (object) [
            'name' => $adaptivereview->name,
            'description' => $adaptivereview->intro ?? '',
            'descriptionformat' => $adaptivereview->introformat ?? FORMAT_HTML,
            'defaultmasterygoal' => $adaptivereview->masterygoal ?? 4.0,
            'archived' => 0,
            'createdby' => $USER->id ?? 0,
            'modifiedby' => $USER->id ?? 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    } else {
        $DB->get_record('adaptivereview_sets', ['id' => $setid, 'archived' => 0], 'id', MUST_EXIST);
    }

    $adaptivereview->setid = $setid;
    $adaptivereview->masterygoal ??= 4.0;
    $adaptivereview->timecreated = time();
    $adaptivereview->timemodified = $adaptivereview->timecreated;
    $id = $DB->insert_record('adaptivereview', $adaptivereview);

    // Insert tutorial demo cards using language-neutral marker keys.
    // The markers (e.g. ##demo_q1##) are resolved to the user's language
    // at display time in external.php, so the correct language is always shown.
    $democards = [
        ['adaptivereviewid' => $id, 'category' => 'demo', 'question' => '##demo_q1##', 'answer' => '##demo_a1##', 'hint' => '##demo_h1##'],
        ['adaptivereviewid' => $id, 'category' => 'demo', 'question' => '##demo_q2##', 'answer' => '##demo_a2##', 'hint' => '##demo_h2##'],
        ['adaptivereviewid' => $id, 'category' => 'demo', 'question' => '##demo_q3##', 'answer' => '##demo_a3##', 'hint' => '##demo_h3##'],
        ['adaptivereviewid' => $id, 'category' => 'demo', 'question' => '##demo_q4##', 'answer' => '##demo_a4##', 'hint' => '##demo_h4##'],
        ['adaptivereviewid' => $id, 'category' => 'demo', 'question' => '##demo_q5##', 'answer' => '##demo_a5##', 'hint' => '##demo_h5##'],
    ];

    foreach ($createset ? $democards : [] as $sortorder => $card) {
        unset($card['adaptivereviewid']);
        $card += [
            'archived' => 0,
            'createdby' => $USER->id ?? 0,
            'modifiedby' => $USER->id ?? 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $cardid = $DB->insert_record('adaptivereview_cards', (object) $card);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $setid,
            'cardid' => $cardid,
            'sortorder' => $sortorder,
            'timecreated' => $now,
        ]);
    }

    $transaction->allow_commit();
    return $id;
}

/**
 * Updates an existing adaptivereview instance
 *
 * @param stdClass $adaptivereview
 * @return bool
 */
function adaptivereview_update_instance($adaptivereview) {
    global $DB;
    if (!empty($adaptivereview->setid)) {
        $DB->get_record('adaptivereview_sets', [
            'id' => $adaptivereview->setid,
            'archived' => 0,
        ], 'id', MUST_EXIST);
    }
    $adaptivereview->timemodified = time();
    $adaptivereview->id = $adaptivereview->instance;
    return $DB->update_record('adaptivereview', $adaptivereview);
}

/**
 * Deletes a adaptivereview instance
 *
 * @param int $id The instance ID
 * @return bool
 */
function adaptivereview_delete_instance($id) {
    global $DB;
    if (!$adaptivereview = $DB->get_record('adaptivereview', ['id' => $id])) {
        return false;
    }

    $sessionids = $DB->get_fieldset_select('adaptivereview_sessions', 'id', 'adaptivereviewid = ?', [$id]);
    if ($sessionids) {
        [$insql, $params] = $DB->get_in_or_equal($sessionids);
        $DB->delete_records_select('adaptivereview_attempts', "sessionid $insql", $params);
    }

    $DB->delete_records('adaptivereview_sessions', ['adaptivereviewid' => $id]);
    $DB->delete_records('adaptivereview', ['id' => $id]);

    // Shared repository content and lifelong mastery must survive course deletion.
    if (!$DB->record_exists('adaptivereview', ['setid' => $adaptivereview->setid])) {
        $DB->set_field('adaptivereview_sets', 'archived', 1, ['id' => $adaptivereview->setid]);
        $DB->set_field('adaptivereview_sets', 'timemodified', time(), ['id' => $adaptivereview->setid]);
    }
    return true;
}

/**
 * Returns features supported by the module.
 *
 * @param string $feature
 * @return mixed
 */
function adaptivereview_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Called by Moodle to get module info for course display.
 * This is the OFFICIAL Moodle 4 mechanism to inject custom completion rules
 * into the cm_info cache's customdata array.
 *
 * @param stdClass $coursemodule
 * @return cached_cm_info|false
 */
function adaptivereview_get_coursemodule_info($coursemodule) {
    global $DB;

    $adaptivereview = $DB->get_record(
        'adaptivereview',
        ['id' => $coursemodule->instance],
        'id, name, completion_min_items, completion_min_mastered, completion_all_mastered'
    );
    if (!$adaptivereview) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $adaptivereview->name;

    // Only write ACTIVE rules (value > 0) to customdata for automatic completion.
    // Moodle evaluates get_state() for EVERY rule in customcompletionrules and expects
    // ALL to return COMPLETE. Inactive rules (value = 0) must NOT be in the array –
    // otherwise they permanently block completion.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        if (!empty($adaptivereview->completion_min_items)) {
            $result->customdata['customcompletionrules']['completion_min_items'] =
                (int)$adaptivereview->completion_min_items;
        }
        if (!empty($adaptivereview->completion_min_mastered)) {
            $result->customdata['customcompletionrules']['completion_min_mastered'] =
                (int)$adaptivereview->completion_min_mastered;
        }
        if (!empty($adaptivereview->completion_all_mastered)) {
            $result->customdata['customcompletionrules']['completion_all_mastered'] = 1;
        }
    }

    return $result;
}

/**
 * Obtains the custom completion rules for this module.
 * Required by Moodle for FEATURE_COMPLETION_HAS_RULES.
 *
 * @return array Array of rule name strings
 */
function adaptivereview_get_custom_completion_rules() {
    return ['completion_min_items', 'completion_min_mastered', 'completion_all_mastered'];
}

/**
 * Returns the custom completion rules for this module.
 *
 * @param stdClass $course
 * @param cm_info $cm
 * @return array
 */
function adaptivereview_get_completion_active_rule_descriptions($course, $cm) {
    $rules = [];
    if (!empty($cm->customdata['customcompletionrules']['completion_min_items'])) {
        $rules[] = get_string('completion_min_cards_desc', 'mod_adaptivereview') . ' ' . $cm->customdata['customcompletionrules']['completion_min_items'];
    }
    if (!empty($cm->customdata['customcompletionrules']['completion_min_mastered'])) {
        $rules[] = get_string('completion_min_mastered_desc', 'mod_adaptivereview') . ' ' . $cm->customdata['customcompletionrules']['completion_min_mastered'];
    }

    if (!empty($cm->customdata['customcompletionrules']['completion_all_mastered'])) {
        $rules[] = get_string('completion_all_mastered', 'mod_adaptivereview');
    }
    return $rules;
}



/**
 * Extends the settings navigation for the adaptivereview module.
 *
 * @param settings_navigation $settings
 * @param navigation_node $adaptivereviewnode
 */
function adaptivereview_extend_settings_navigation(settings_navigation $settings, navigation_node $adaptivereviewnode) {
    global $PAGE;

    if (has_capability('moodle/course:manageactivities', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/adaptivereview/manage.php', ['id' => $PAGE->cm->id]);
        $adaptivereviewnode->add(
            get_string('managecards', 'mod_adaptivereview'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'managecards',
            new pix_icon('i/settings', '')
        );
    }

    if (has_capability('mod/adaptivereview:manageglobalrepository', context_system::instance())) {
        $adaptivereviewnode->add(
            get_string('repositorytitle', 'mod_adaptivereview'),
            new moodle_url('/mod/adaptivereview/repository.php', ['cmid' => $PAGE->cm->id]),
            navigation_node::TYPE_SETTING,
            null,
            'adaptivereviewrepository',
            new pix_icon('i/folder', '')
        );
    }
}
