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
namespace mod_adaptivereview;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use mod_adaptivereview\local\review_manager;
use mod_adaptivereview\local\review_queue_policy;
use mod_adaptivereview\local\student_summary;

class external extends external_api {

    public static function get_box_counts_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'The adaptivereview instance id'),
        ]);
    }

    public static function get_box_counts($instanceid) {
        global $USER;
        
        $params = self::validate_parameters(self::get_box_counts_parameters(), [
            'instanceid' => $instanceid,
        ]);
        
        $cm = get_coursemodule_from_instance('adaptivereview', $params['instanceid']);
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/adaptivereview:view', $context);

        $summary = student_summary::get((int) $params['instanceid'], (int) $USER->id);

        return [
            ['box_number' => 0, 'count' => $summary->duecount],
            ['box_number' => 1, 'count' => $summary->newcount],
            ['box_number' => 2, 'count' => $summary->recentcount],
            ['box_number' => 3, 'count' => $summary->totalcards],
        ];
    }

    public static function get_box_counts_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'box_number' => new external_value(PARAM_INT, 'The review category number'),
                'count' => new external_value(PARAM_INT, 'The number of cards in this category'),
            ])
        );
    }

    public static function get_student_summary_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'The Adaptive Review instance ID'),
        ]);
    }

    public static function get_student_summary($instanceid) {
        global $USER;

        $params = self::validate_parameters(self::get_student_summary_parameters(), [
            'instanceid' => $instanceid,
        ]);
        $cm = get_coursemodule_from_instance('adaptivereview', $params['instanceid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/adaptivereview:view', $context);

        $summary = student_summary::get((int) $params['instanceid'], (int) $USER->id);

        return (array) $summary;
    }

    public static function get_student_summary_returns() {
        return new external_single_structure([
            'duecount' => new external_value(PARAM_INT, 'Cards currently due'),
            'newcount' => new external_value(PARAM_INT, 'Unstarted cards in the selected set'),
            'recentcount' => new external_value(PARAM_INT, 'Learned cards not currently due'),
            'totalcards' => new external_value(PARAM_INT, 'Active cards in the selected set'),
            'setmastery' => new external_value(PARAM_FLOAT, 'Average lifelong mastery across the set'),
            'masterygoal' => new external_value(PARAM_FLOAT, 'Activity-specific mastery goal'),
            'progresspercent' => new external_value(PARAM_FLOAT, 'Average capped progress toward the goal'),
            'cardsatgoal' => new external_value(PARAM_INT, 'Cards at or above the activity goal'),
            'newavailable' => new external_value(PARAM_BOOL, 'Whether new-card review may begin'),
        ]);
    }

    public static function get_cards_by_box_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'The adaptivereview instance id'),
            'boxnumber' => new external_value(PARAM_INT, 'The review category number'),
        ]);
    }

    public static function get_cards_by_box($instanceid, $boxnumber) {
        global $DB, $USER;
        
        $params = self::validate_parameters(self::get_cards_by_box_parameters(), [
            'instanceid' => $instanceid,
            'boxnumber' => $boxnumber
        ]);
        
        $cm = get_coursemodule_from_instance('adaptivereview', $params['instanceid']);
        if (!$cm) {
            throw new \moodle_exception('invalidcoursemodule');
        }
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/adaptivereview:view', $context);

        $box = $params['boxnumber'];
        if ($box < 0 || $box > 3) {
            throw new \moodle_exception('invalidparameter');
        }

        $userid = $USER->id;
        $instanceid = $params['instanceid'];
        $now = time();

        $adaptivereview = $DB->get_record('adaptivereview', ['id' => $instanceid],
            'itemorder, setid, masterygoal', MUST_EXIST);
        $order_by = ($adaptivereview->itemorder == 1) ? "ORDER BY sc.sortorder ASC, c.id ASC" : "";

        $select = "SELECT c.*,
                        COALESCE(p.masteryscore, 0) AS masteryscore,
                        COALESCE(p.requiredintervaldays, 0) AS requiredintervaldays,
                        COALESCE(p.nextreviewdate, 0) AS nextreviewdate,
                        COALESCE(p.lastreviewdate, 0) AS lastreviewdate";

        if ($box == 0) {
            $sql = "$select
                    FROM {adaptivereview_cards} c
                    JOIN {adaptivereview_set_cards} sc ON sc.cardid = c.id
                    JOIN {adaptivereview_mastery} p
                        ON c.id = p.cardid
                    WHERE sc.setid = :setid
                    AND c.archived = 0
                    AND p.userid = :userid
                    AND p.nextreviewdate > 0
                    AND p.nextreviewdate <= :now
                    $order_by";

            $cards = $DB->get_records_sql($sql, [
                'userid' => $userid,
                'setid' => $adaptivereview->setid,
                'now' => $now,
            ]);

        } else if ($box == 1) {
            $sql = "$select
                    FROM {adaptivereview_cards} c
                    JOIN {adaptivereview_set_cards} sc ON sc.cardid = c.id
                LEFT JOIN {adaptivereview_mastery} p
                        ON c.id = p.cardid AND p.userid = :userid
                    WHERE sc.setid = :setid
                    AND c.archived = 0
                    AND p.id IS NULL
                    $order_by";

            $cards = $DB->get_records_sql($sql, [
                'userid' => $userid,
                'setid' => $adaptivereview->setid,
            ]);

            if (review_queue_policy::due_count($instanceid, $userid, $now) > 0) {
                $cards = [];
            }

        } else if ($box == 2) {
            $sql = "$select
                    FROM {adaptivereview_cards} c
                    JOIN {adaptivereview_set_cards} sc ON sc.cardid = c.id
                    JOIN {adaptivereview_mastery} p
                        ON c.id = p.cardid
                    WHERE sc.setid = :setid
                    AND c.archived = 0
                    AND p.userid = :userid
                    AND p.lastreviewdate > 0
                    AND p.nextreviewdate > :now
                    $order_by";

            $cards = $DB->get_records_sql($sql, [
                'userid' => $userid,
                'setid' => $adaptivereview->setid,
                'now' => $now,
            ]);

        } else {
            $sql = "$select
                    FROM {adaptivereview_cards} c
                    JOIN {adaptivereview_set_cards} sc ON sc.cardid = c.id
                LEFT JOIN {adaptivereview_mastery} p
                        ON c.id = p.cardid AND p.userid = :userid
                    WHERE sc.setid = :setid
                    AND c.archived = 0
                    $order_by";

            $cards = $DB->get_records_sql($sql, [
                'userid' => $userid,
                'setid' => $adaptivereview->setid,
            ]);
        }

        $result = [];
        foreach ($cards as $card) {
            $masteryscore = min(
                review_manager::MAX_MASTERY,
                max(0.0, (float) $card->masteryscore)
            );
            $resolve = function($text) {
                if (preg_match('/^##(demo_[a-z0-9]+)##$/', $text, $m)) {
                    return get_string($m[1], 'mod_adaptivereview');
                }
                return $text;
            };
            $result[] = [
                'id' => $card->id,
                'question' => $resolve($card->question),
                'answer' => $resolve($card->answer),
                'hint' => $resolve($card->hint ? $card->hint : ''),
                'category' => $card->category ? $card->category : '',
                'masteryscore' => round($masteryscore, 1),
                'progresspercent' => round(min(100.0,
                    ($masteryscore / max(0.1, (float) $adaptivereview->masterygoal)) * 100), 1),
                'atgoal' => $masteryscore >= (float) $adaptivereview->masterygoal,
                'requiredintervaldays' => (int)$card->requiredintervaldays,
                'nextreviewdate' => (int)$card->nextreviewdate,
                'lastreviewdate' => (int)$card->lastreviewdate,
            ];
        }

        if ($adaptivereview->itemorder == 0 && $box != 3) {
            shuffle($result);
        }

        return array_values($result);
    }

    public static function get_cards_by_box_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Card ID'),
                'question' => new external_value(PARAM_RAW, 'Question HTML'),
                'answer' => new external_value(PARAM_RAW, 'Answer HTML'),
                'hint' => new external_value(PARAM_RAW, 'Optional hint HTML', VALUE_OPTIONAL),
                'category' => new external_value(PARAM_TEXT, 'Optional category', VALUE_OPTIONAL),
                'masteryscore' => new external_value(PARAM_FLOAT, 'Current mastery score'),
                'progresspercent' => new external_value(PARAM_FLOAT, 'Progress toward this activity goal'),
                'atgoal' => new external_value(PARAM_BOOL, 'Whether this card has reached the activity goal'),
                'requiredintervaldays' => new external_value(PARAM_INT, 'Required interval days'),
                'nextreviewdate' => new external_value(PARAM_INT, 'Next review timestamp'),
                'lastreviewdate' => new external_value(PARAM_INT, 'Last review timestamp'),
            ])
        );
    }

    public static function start_review_session_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'The Adaptive Review instance ID'),
            'mode' => new external_value(PARAM_ALPHA, 'Review mode: due, new, or early'),
        ]);
    }

    public static function start_review_session($instanceid, $mode) {
        global $USER;

        $params = self::validate_parameters(self::start_review_session_parameters(), [
            'instanceid' => $instanceid,
            'mode' => $mode,
        ]);

        $cm = get_coursemodule_from_instance('adaptivereview', $params['instanceid'], 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/adaptivereview:view', $context);

        $session = review_manager::start_session($params['instanceid'], $USER->id, $params['mode']);

        return ['sessionkey' => $session->sessionkey];
    }

    public static function start_review_session_returns() {
        return new external_single_structure([
            'sessionkey' => new external_value(PARAM_ALPHANUM, 'Opaque review session key'),
        ]);
    }

    public static function complete_review_session_parameters() {
        return new external_function_parameters([
            'sessionkey' => new external_value(PARAM_ALPHANUM, 'Opaque review session key'),
        ]);
    }

    public static function complete_review_session($sessionkey) {
        global $USER;

        $params = self::validate_parameters(self::complete_review_session_parameters(), [
            'sessionkey' => $sessionkey,
        ]);
        review_manager::complete_session($params['sessionkey'], $USER->id);

        return ['success' => true];
    }

    public static function complete_review_session_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success indicator'),
        ]);
    }

    public static function submit_answer_parameters() {
        return new external_function_parameters([
            'cardid' => new external_value(PARAM_INT, 'The card ID'),
            'response' => new external_value(PARAM_ALPHA, 'Learner response: know or notyet'),
            'sessionkey' => new external_value(PARAM_ALPHANUM, 'Opaque review session key'),
        ]);
    }

    public static function submit_answer($cardid, $response, $sessionkey) {
        global $DB, $USER;

        $params = self::validate_parameters(self::submit_answer_parameters(), [
            'cardid' => $cardid,
            'response' => $response,
            'sessionkey' => $sessionkey,
        ]);

        $session = review_manager::get_active_session($params['sessionkey'], $USER->id);
        $adaptivereview = $DB->get_record('adaptivereview', ['id' => $session->adaptivereviewid], '*', MUST_EXIST);
        $card = $DB->get_record('adaptivereview_cards', ['id' => $params['cardid'], 'archived' => 0], '*', MUST_EXIST);
        if (!$DB->record_exists('adaptivereview_set_cards', [
                'setid' => $adaptivereview->setid,
                'cardid' => $card->id,
            ])) {
            throw new \moodle_exception('invalidrecord');
        }
        $course = $DB->get_record('course', ['id' => $adaptivereview->course], '*', MUST_EXIST);

        $cm_raw = get_coursemodule_from_instance('adaptivereview', $adaptivereview->id, $course->id, false, MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($cm_raw->id);
        $context = \context_module::instance($cm->id);

        self::validate_context($context);
        require_capability('mod/adaptivereview:view', $context);

        $attempt = review_manager::record_attempt($session, $card, $params['response']);

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
        }

        return [
            'success' => true,
            'attemptnumber' => $attempt->attemptnumber,
            'masteryeligible' => (bool) $attempt->masteryeligible,
            'masterybefore' => (float) $attempt->masterybefore,
            'masterygain' => (float) $attempt->masterygain,
            'masteryafter' => (float) $attempt->masteryafter,
            'nextreviewdate' => (int) $attempt->nextreviewdate,
        ];
    }

    public static function submit_answer_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success indicator'),
            'attemptnumber' => new external_value(PARAM_INT, 'Attempt number for this card in the session'),
            'masteryeligible' => new external_value(PARAM_BOOL, 'Whether the attempt may affect mastery'),
            'masterybefore' => new external_value(PARAM_FLOAT, 'Mastery before the attempt'),
            'masterygain' => new external_value(PARAM_FLOAT, 'Mastery gain applied'),
            'masteryafter' => new external_value(PARAM_FLOAT, 'Mastery after the attempt'),
            'nextreviewdate' => new external_value(PARAM_INT, 'Resulting next review timestamp'),
        ]);
    }

    public static function reset_progress_parameters() {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'The adaptivereview instance id'),
        ]);
    }

    public static function reset_progress($instanceid) {
        global $DB, $USER;

        $params = self::validate_parameters(self::reset_progress_parameters(), [
            'instanceid' => $instanceid,
        ]);

        $adaptivereview = $DB->get_record('adaptivereview', ['id' => $params['instanceid']], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $adaptivereview->course], '*', MUST_EXIST);
        
        $cm_raw = get_coursemodule_from_instance('adaptivereview', $params['instanceid'], $course->id, false, MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($cm_raw->id);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/adaptivereview:view', $context);

        $cardids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?',
            [$adaptivereview->setid]);

        $resetcount = 0;

        if (!empty($cardids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($cardids, SQL_PARAMS_QM);

            $select = "userid = ? AND cardid $insql";
            $deleteparams = array_merge([$USER->id], $inparams);

            $resetcount = $DB->count_records_select('adaptivereview_mastery', $select, $deleteparams);
            $DB->delete_records_select('adaptivereview_mastery', $select, $deleteparams);
        }

        $completion = new \completion_info($course);
        if ($completion->is_enabled($cm) && $cm->completion == COMPLETION_TRACKING_AUTOMATIC) {
            $completion->update_state($cm, COMPLETION_UNKNOWN, $USER->id);
        }

        return [
            'success' => true,
            'reset_count' => $resetcount,
        ];
    }

    public static function reset_progress_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Success indicator'),
            'reset_count' => new external_value(PARAM_INT, 'Number of cards reset'),
        ]);
    }
}
