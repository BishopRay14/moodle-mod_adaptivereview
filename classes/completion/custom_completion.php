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
 * Custom completion class for mod_adaptivereview.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivereview\completion;

defined('MOODLE_INTERNAL') || die();

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass that calculates the completion state
 * of the Adaptive Review activity.
 *
 * HOW MOODLE 4.x CUSTOM COMPLETION WORKS:
 * ----------------------------------------
 * 1. adaptivereview_cm_info_static() in lib.php populates $cm->customdata with the
 *    active rules for this instance (e.g. ['completion_min_items' => 10]).
 *
 * 2. Moodle calls get_state() for each rule defined in get_defined_custom_rules().
 *    IMPORTANT: get_state() is only called for rules that are ACTIVE in this
 *    instance (present in $cm->customdata['customcompletionrules']).
 *    Inactive rules are simply not evaluated.
 *
 * 3. If ALL active rules return COMPLETION_COMPLETE, the activity is complete.
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the list of custom completion rule names that this module defines.
     * This is the full list of possible rules — not which are active per instance.
     * Active rules per instance are determined by adaptivereview_cm_info_static() in lib.php.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completion_min_items',
            'completion_min_mastered',
            'completion_all_mastered',
        ];
    }

    /**
     * Returns the completion state for a given rule for the current user.
     *
     * Moodle guarantees this is only called for rules that are active in this
     * instance (i.e. present in cm->customdata['customcompletionrules']).
     * We still validate defensively.
     *
     * @param string $rule The completion rule identifier.
     * @return int COMPLETION_COMPLETE or COMPLETION_INCOMPLETE
     */
    public function get_state(string $rule): int {
        global $DB;

        // Validate that this is a known rule — Moodle best practice.
        $this->validate_rule($rule);

        $userid      = $this->userid;
        $cm          = $this->cm;
        $instanceid  = $cm->instance;
        $setid       = $DB->get_field('adaptivereview', 'setid', ['id' => $instanceid], MUST_EXIST);
        $customrules = $cm->customdata['customcompletionrules'] ?? [];

        if ($rule === 'completion_min_items') {
            // Not in customdata = rule disabled = treat as complete.
            if (empty($customrules['completion_min_items'])) {
                return COMPLETION_COMPLETE;
            }

            $target = (int)$customrules['completion_min_items'];

            // Counts cards that have been answered correctly at least once (box_number >= 1).
            // A card only reaches Box 1+ if it has been rated green at least once.
            // This is the first real quality indicator in the Leitner system —
            // as opposed to counting every attempted card, including incorrect responses.
            $sql = "SELECT COUNT(DISTINCT p.cardid)
                      FROM {adaptivereview_mastery} p
                     WHERE p.userid      = :userid
                       AND p.box_number >= 1
                       AND p.cardid IN (
                           SELECT cardid FROM {adaptivereview_set_cards} WHERE setid = :setid
                       )";
            $count = (int)($DB->get_field_sql($sql, [
                'userid'     => $userid,
                'setid' => $setid,
            ]) ?? 0);
            return ($count >= $target) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        if ($rule === 'completion_min_mastered') {
            if (empty($customrules['completion_min_mastered'])) {
                return COMPLETION_COMPLETE;
            }
            
            $target = (int)$customrules['completion_min_mastered'];
            $sql = "SELECT COUNT(DISTINCT cardid)
                      FROM {adaptivereview_mastery}
                     WHERE userid     = :userid
                       AND box_number = 5
                       AND cardid IN (
                           SELECT cardid FROM {adaptivereview_set_cards} WHERE setid = :setid
                       )";
            $mastered = (int)($DB->get_field_sql($sql, [
                'userid'     => $userid,
                'setid' => $setid,
            ]) ?? 0);
            return ($mastered >= $target) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        if ($rule === 'completion_all_mastered') {
            if (empty($customrules['completion_all_mastered'])) {
                return COMPLETION_COMPLETE;
            }
            
            $total = (int) $DB->count_records_sql(
                "SELECT COUNT(*)
                   FROM {adaptivereview_set_cards} sc
                   JOIN {adaptivereview_cards} c ON c.id = sc.cardid
                  WHERE sc.setid = :setid AND c.archived = 0",
                ['setid' => $setid]
            );
            if ($total === 0) {
                return COMPLETION_INCOMPLETE; // No cards exist yet.
            }
            
            $sql = "SELECT COUNT(DISTINCT p.cardid)
                      FROM {adaptivereview_mastery} p
                      JOIN {adaptivereview_set_cards} sc ON p.cardid = sc.cardid
                      JOIN {adaptivereview_cards} c ON c.id = sc.cardid
                     WHERE sc.setid = :setid
                       AND c.archived = 0
                       AND p.userid    = :userid
                       AND p.box_number = 5";
            $mastered = (int)($DB->get_field_sql($sql, [
                'setid' => $setid,
                'userid'     => $userid,
            ]) ?? 0);
            return ($mastered === $total) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        // Unknown rule — fail safe.
        return COMPLETION_INCOMPLETE;
    }

    /**
     * Returns human-readable descriptions of active completion rules.
     * These are shown to students in the activity completion requirements.
     *
     * @return array rule_name => description string
     */
    public function get_custom_rule_descriptions(): array {
        $customrules = $this->cm->customdata['customcompletionrules'] ?? [];
        $descriptions = [];

        if (!empty($customrules['completion_min_items'])) {
            $descriptions['completion_min_items'] =
                get_string('completion_min_cards_desc', 'mod_adaptivereview') . ' ' .
                $customrules['completion_min_items'];
        }
        if (!empty($customrules['completion_min_mastered'])) {
            $descriptions['completion_min_mastered'] =
                get_string('completion_min_mastered_desc', 'mod_adaptivereview') . ' ' .
                $customrules['completion_min_mastered'];
        }
        if (!empty($customrules['completion_all_mastered'])) {
            $descriptions['completion_all_mastered'] =
                get_string('completion_all_mastered_desc', 'mod_adaptivereview');
        }

        return $descriptions;
    }

    /**
     * Returns all completion rules in display order.
     * Include core conditions (completionview) alongside custom ones.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completion_min_items',
            'completion_min_mastered',
            'completion_all_mastered',
        ];
    }
}
