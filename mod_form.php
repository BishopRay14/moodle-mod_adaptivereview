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
 * Activity settings form for Adaptive Review.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Defines the Adaptive Review activity settings form.
 */
class mod_adaptivereview_mod_form extends moodleform_mod {
    /**
     * Build the activity settings form.
     */
    public function definition() {
        global $CFG, $DB;

        $mform = $this->_form;

        // General section.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('adaptivereviewname', 'mod_adaptivereview'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        // Plugin-specific settings.
        $mform->addElement('header', 'adaptivereviewfieldset', get_string('settings', 'mod_adaptivereview'));

        $setoptions = [];
        if (empty($this->_instance)) {
            $setoptions[0] = get_string('setcreatenew', 'mod_adaptivereview');
        }
        $sets = $DB->get_records('adaptivereview_sets', ['archived' => 0], 'name ASC', 'id, name');
        foreach ($sets as $set) {
            $setoptions[$set->id] = format_string($set->name);
        }
        $mform->addElement('autocomplete', 'setid', get_string('setselection', 'mod_adaptivereview'), $setoptions);
        $mform->setDefault('setid', 0);
        $mform->addHelpButton('setid', 'setselection', 'mod_adaptivereview');

        $mform->addElement('select', 'itemorder', get_string('cardorder', 'mod_adaptivereview'), [
            0 => get_string('cardorder_random', 'mod_adaptivereview'),
            1 => get_string('cardorder_sequential', 'mod_adaptivereview'),
        ]);
        $mform->setDefault('itemorder', 0);
        $mform->addHelpButton('itemorder', 'cardorder', 'mod_adaptivereview');

        $mform->addElement('text', 'masterygoal', get_string('masterygoal', 'mod_adaptivereview'), ['size' => 5]);
        $mform->setType('masterygoal', PARAM_FLOAT);
        $mform->setDefault('masterygoal', 4.0);
        $mform->addRule('masterygoal', null, 'required', null, 'client');
        $mform->addHelpButton('masterygoal', 'masterygoal', 'mod_adaptivereview');

        // Completion rules are added by add_completion_rules() below.
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Validate the activity-specific mastery goal.
     *
     * @param array $data Submitted form values.
     * @param array $files Submitted files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        $masterygoal = (float) ($data['masterygoal'] ?? 0);
        if ($masterygoal < 0.1 || $masterygoal > 5.9) {
            $errors['masterygoal'] = get_string('masterygoalerror', 'mod_adaptivereview');
        }
        $setid = (int) ($data['setid'] ?? 0);
        if (!empty($this->_instance) && $setid === 0) {
            $errors['setid'] = get_string('setselectionerror', 'mod_adaptivereview');
        }
        if ($setid > 0 && !$DB->record_exists('adaptivereview_sets', ['id' => $setid, 'archived' => 0])) {
            $errors['setid'] = get_string('setselectionerror', 'mod_adaptivereview');
        }
        return $errors;
    }

    /**
     * Add the custom completion rule form elements.
     *
     * Called by Moodle core when building the activity settings form.
     * Must return an array of the top-level element names added here.
     *
     * @return array Names of the top-level form elements for these rules.
     */
    public function add_completion_rules() {
        $mform  = $this->_form;

        // Moodle 4.3+ uses a suffix system to avoid naming collisions when
        // multiple instances of the same activity appear on one page.
        $suffix = $this->get_suffix();

        // ----------------------------------------------------------------
        // Rule 1: Minimum number of unique cards practiced
        // ----------------------------------------------------------------
        $group1name   = 'completion_min_cards_group' . $suffix;
        $enabled1name = 'completion_min_cards_enabled' . $suffix;
        $value1name   = 'completion_min_items' . $suffix;

        $group1 = [];
        $group1[] = $mform->createElement(
            'checkbox',
            $enabled1name,
            '',
            get_string('completion_min_cards_desc', 'mod_adaptivereview')
        );
        $group1[] = $mform->createElement('text', $value1name, '', ['size' => 3]);
        $mform->setType($value1name, PARAM_INT);

        $mform->addGroup(
            $group1,
            $group1name,
            get_string('completion_min_items', 'mod_adaptivereview'),
            [' '],
            false
        );
        $mform->addHelpButton($group1name, 'completion_min_items', 'mod_adaptivereview');
        $mform->hideIf($value1name, $enabled1name, 'notchecked');

        // ----------------------------------------------------------------
        // Rule 2: Minimum number of cards mastered (reached Box 5)
        // ----------------------------------------------------------------
        $group2name   = 'completion_min_mastered_group' . $suffix;
        $enabled2name = 'completion_min_mastered_enabled' . $suffix;
        $value2name   = 'completion_min_mastered' . $suffix;

        $group2 = [];
        $group2[] = $mform->createElement(
            'checkbox',
            $enabled2name,
            '',
            get_string('completion_min_mastered_desc', 'mod_adaptivereview')
        );
        $group2[] = $mform->createElement('text', $value2name, '', ['size' => 3]);
        $mform->setType($value2name, PARAM_INT);

        $mform->addGroup(
            $group2,
            $group2name,
            get_string('completion_min_mastered', 'mod_adaptivereview'),
            [' '],
            false
        );
        $mform->addHelpButton($group2name, 'completion_min_mastered', 'mod_adaptivereview');
        $mform->hideIf($value2name, $enabled2name, 'notchecked');

        // ----------------------------------------------------------------
        // Rule 3: ALL cards must be mastered (Box 5)
        // Use addGroup (like Rules 1 & 2) so the help icon renders on the left.
        // ----------------------------------------------------------------
        $allgroupname = 'completion_all_mastered_group' . $suffix;
        $allname      = 'completion_all_mastered' . $suffix;

        $group3 = [];
        $group3[] = $mform->createElement(
            'checkbox',
            $allname,
            '',
            get_string('completion_all_mastered_desc', 'mod_adaptivereview')
        );

        $mform->addGroup(
            $group3,
            $allgroupname,
            get_string('completion_all_mastered', 'mod_adaptivereview'),
            [' '],
            false
        );
        $mform->addHelpButton($allgroupname, 'completion_all_mastered', 'mod_adaptivereview');

        return [$group1name, $group2name, $allgroupname];
    }

    /**
     * Called during form validation to decide if at least one custom
     * completion rule has been configured (so Moodle knows not to complain
     * when automatic completion is selected but no standard grade/view
     * condition is ticked).
     *
     * @param array $data Form data.
     * @return bool True if at least one rule is enabled.
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();

        $mincardson   = !empty($data['completion_min_cards_enabled' . $suffix])
                          && (int)($data['completion_min_items' . $suffix] ?? 0) > 0;
        $minmasteredon = !empty($data['completion_min_mastered_enabled' . $suffix])
                           && (int)($data['completion_min_mastered' . $suffix] ?? 0) > 0;
        $allmasteredon = !empty($data['completion_all_mastered' . $suffix]);

        return $mincardson || $minmasteredon || $allmasteredon;
    }

    /**
     * Pre-populate the form when editing an existing activity.
     *
     * We need to set the "enabled" checkboxes based on whether the
     * corresponding DB field has a non-zero value, so the UI correctly
     * reflects the saved state.
     *
     * @param array $defaultvalues Reference to the array of default values.
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);
        $suffix = $this->get_suffix();

        // Rule 1: completion_min_items
        $keyval     = 'completion_min_items' . $suffix;
        $keyenabled = 'completion_min_cards_enabled' . $suffix;

        if (!empty($defaultvalues[$keyval])) {
            $defaultvalues[$keyenabled] = 1;
        } else {
            $defaultvalues[$keyval]     = 10; // Sensible default shown in the field.
            $defaultvalues[$keyenabled] = 0;
        }

        // Rule 2: completion_min_mastered
        $keyval2     = 'completion_min_mastered' . $suffix;
        $keyenabled2 = 'completion_min_mastered_enabled' . $suffix;

        if (!empty($defaultvalues[$keyval2])) {
            $defaultvalues[$keyenabled2] = 1;
        } else {
            $defaultvalues[$keyval2]     = 0;
            $defaultvalues[$keyenabled2] = 0;
        }

        // Rule 3: completion_all_mastered — nothing special needed; the
        // checkbox value comes straight from the DB field.
        if (!isset($defaultvalues['completion_all_mastered' . $suffix])) {
            $defaultvalues['completion_all_mastered' . $suffix] = 0;
        }
    }

    /**
     * Post-process form data before it is saved to the database.
     *
     * If the "enabled" checkbox for a numeric rule is unticked, we must
     * write 0 to the DB so the rule is truly disabled — otherwise a
     * leftover non-zero value would silently re-activate it.
     *
     * @param stdClass $data Form data object (modified in place).
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        $suffix = $this->get_suffix();

        // Only touch completion fields when the completion lock is open
        // (completionunlocked is set by Moodle when the admin explicitly
        // edits the completion settings).
        if (empty($data->completionunlocked)) {
            return;
        }

        $autocompletion = isset($data->{'completion' . $suffix})
                          && (int)$data->{'completion' . $suffix} === COMPLETION_TRACKING_AUTOMATIC;

        // If not using automatic completion at all, zero out all our fields.
        if (!$autocompletion) {
            $data->{'completion_min_items' . $suffix}    = 0;
            $data->{'completion_min_mastered' . $suffix} = 0;
            $data->{'completion_all_mastered' . $suffix} = 0;
            return;
        }

        // Rule 1: if the checkbox is off, write 0 so the rule is disabled.
        if (empty($data->{'completion_min_cards_enabled' . $suffix})) {
            $data->{'completion_min_items' . $suffix} = 0;
        }

        // Rule 2: same for min_mastered.
        if (empty($data->{'completion_min_mastered_enabled' . $suffix})) {
            $data->{'completion_min_mastered' . $suffix} = 0;
        }

        // Rule 3: all_mastered is a plain checkbox; no extra processing needed.
    }
}
