<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_adaptivereview\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Creates authoritative review sessions and append-only attempt history.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_manager {

    /** @var string Due-card session mode. */
    public const MODE_DUE = 'due';

    /** @var string New-card session mode. */
    public const MODE_NEW = 'new';

    /** @var string Voluntary early-practice session mode. */
    public const MODE_EARLY = 'early';

    /** @var string Successful retrieval response. */
    public const RESPONSE_KNOW = 'know';

    /** @var string Unsuccessful retrieval response. */
    public const RESPONSE_NOTYET = 'notyet';

    /** @var string History-only compatibility behavior identifier. */
    public const ALGORITHM_VERSION = 'history-v1';

    /** @var int Open sessions expire after eight hours. */
    private const SESSION_LIFETIME = 8 * 60 * 60;

    /**
     * Return supported review modes.
     *
     * @return string[]
     */
    public static function valid_modes(): array {
        return [self::MODE_DUE, self::MODE_NEW, self::MODE_EARLY];
    }

    /**
     * Return supported learner responses.
     *
     * @return string[]
     */
    public static function valid_responses(): array {
        return [self::RESPONSE_KNOW, self::RESPONSE_NOTYET];
    }

    /**
     * Start a server-owned review session.
     *
     * @param int $adaptivereviewid Activity instance ID.
     * @param int $userid User ID.
     * @param string $mode Session mode.
     * @param int|null $now Testable server time.
     * @return stdClass
     */
    public static function start_session(int $adaptivereviewid, int $userid, string $mode, ?int $now = null): \stdClass {
        global $DB;

        if (!in_array($mode, self::valid_modes(), true)) {
            throw new \invalid_parameter_exception('Invalid review session mode.');
        }

        $now = $now ?? time();
        do {
            $sessionkey = bin2hex(random_bytes(32));
        } while ($DB->record_exists('adaptivereview_sessions', ['sessionkey' => $sessionkey]));

        $session = (object) [
            'adaptivereviewid' => $adaptivereviewid,
            'userid' => $userid,
            'sessionkey' => $sessionkey,
            'mode' => $mode,
            'timecreated' => $now,
            'timecompleted' => 0,
        ];
        $session->id = $DB->insert_record('adaptivereview_sessions', $session);

        return $session;
    }

    /**
     * Complete a review session owned by the current user.
     *
     * @param string $sessionkey Opaque session key.
     * @param int $userid User ID.
     * @param int|null $now Testable server time.
     * @return bool
     */
    public static function complete_session(string $sessionkey, int $userid, ?int $now = null): bool {
        global $DB;

        $session = $DB->get_record('adaptivereview_sessions', [
            'sessionkey' => $sessionkey,
            'userid' => $userid,
        ], '*', MUST_EXIST);

        if (empty($session->timecompleted)) {
            $session->timecompleted = $now ?? time();
            $DB->update_record('adaptivereview_sessions', $session);
        }

        return true;
    }

    /**
     * Get an active session owned by the current user.
     *
     * @param string $sessionkey Opaque session key.
     * @param int $userid User ID.
     * @param int|null $now Testable server time.
     * @return stdClass
     */
    public static function get_active_session(string $sessionkey, int $userid, ?int $now = null): \stdClass {
        global $DB;

        $session = $DB->get_record('adaptivereview_sessions', [
            'sessionkey' => $sessionkey,
            'userid' => $userid,
        ], '*', MUST_EXIST);
        $now = $now ?? time();

        if (!empty($session->timecompleted) || ($session->timecreated + self::SESSION_LIFETIME) < $now) {
            throw new \moodle_exception('invalidparameter');
        }

        return $session;
    }

    /**
     * Record one retrieval attempt and conservatively update summary scheduling.
     *
     * The approved mastery formula has not been selected. This history-only
     * implementation never changes mastery. Eligible first attempts receive a
     * one-day follow-up schedule so cards remain available for later engine work.
     *
     * @param stdClass $session Active review session.
     * @param stdClass $item Activity item.
     * @param string $response Learner response.
     * @param int|null $now Testable server time.
     * @return stdClass Recorded attempt.
     */
    public static function record_attempt(
        \stdClass $session,
        \stdClass $item,
        string $response,
        ?int $now = null
    ): \stdClass {
        global $DB;

        if (!in_array($response, self::valid_responses(), true)) {
            throw new \invalid_parameter_exception('Invalid review response.');
        }
        if ((int) $item->adaptivereviewid !== (int) $session->adaptivereviewid) {
            throw new \invalid_parameter_exception('The card does not belong to this review session.');
        }

        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();

        $attemptnumber = $DB->count_records('adaptivereview_attempts', [
            'sessionid' => $session->id,
            'itemid' => $item->id,
        ]) + 1;
        $isremediation = $attemptnumber > 1;

        $mastery = $DB->get_record('adaptivereview_mastery', [
            'userid' => $session->userid,
            'itemid' => $item->id,
        ]);
        $isnew = !$mastery;

        $masterybefore = $mastery ? (float) $mastery->masteryscore : 0.0;
        $intervalbefore = $mastery ? (int) $mastery->requiredintervaldays : 0;
        $previousreviewdate = $mastery ? (int) $mastery->lastreviewdate : 0;
        $previousduedate = $mastery ? (int) $mastery->nextreviewdate : 0;
        $wasdue = !$isnew && $previousduedate > 0 && $previousduedate <= (int) $session->timecreated;

        $validfirstattempt = !$isremediation && (
            ($session->mode === self::MODE_NEW && $isnew) ||
            ($session->mode === self::MODE_DUE && $wasdue)
        );
        $masteryeligible = $validfirstattempt && $response === self::RESPONSE_KNOW;

        if ($validfirstattempt) {
            if (!$mastery) {
                $mastery = self::new_mastery_record((int) $session->userid, (int) $item->id);
            }

            $mastery->last_reviewed = $now;
            $mastery->requiredintervaldays = 1;
            $mastery->nextreviewdate = $now + DAYSECS;

            if ($response === self::RESPONSE_KNOW) {
                $mastery->count_correct++;
                $mastery->lastreviewdate = $now;
            } else {
                $mastery->count_wrong++;
            }

            if (empty($mastery->id)) {
                $mastery->id = $DB->insert_record('adaptivereview_mastery', $mastery);
            } else {
                $DB->update_record('adaptivereview_mastery', $mastery);
            }
        }

        $attempt = (object) [
            'sessionid' => $session->id,
            'itemid' => $item->id,
            'attemptnumber' => $attemptnumber,
            'response' => $response,
            'wasdue' => (int) $wasdue,
            'isremediation' => (int) $isremediation,
            'masteryeligible' => (int) $masteryeligible,
            'masterybefore' => $masterybefore,
            'masterygain' => 0.0,
            'masteryafter' => $masterybefore,
            'intervalbefore' => $intervalbefore,
            'intervalafter' => $mastery ? (int) $mastery->requiredintervaldays : $intervalbefore,
            'previousreviewdate' => $previousreviewdate,
            'previousduedate' => $previousduedate,
            'nextreviewdate' => $mastery ? (int) $mastery->nextreviewdate : $previousduedate,
            'algorithmversion' => self::ALGORITHM_VERSION,
            'timecreated' => $now,
        ];
        $attempt->id = $DB->insert_record('adaptivereview_attempts', $attempt);

        $transaction->allow_commit();
        return $attempt;
    }

    /**
     * Create a default mutable mastery summary row.
     *
     * @param int $userid User ID.
     * @param int $itemid Item ID.
     * @return stdClass
     */
    private static function new_mastery_record(int $userid, int $itemid): \stdClass {
        return (object) [
            'id' => 0,
            'userid' => $userid,
            'itemid' => $itemid,
            'box_number' => 0,
            'status' => 0,
            'count_correct' => 0,
            'count_wrong' => 0,
            'last_reviewed' => 0,
            'masteryscore' => 0.0,
            'requiredintervaldays' => 0,
            'nextreviewdate' => 0,
            'lastreviewdate' => 0,
        ];
    }
}
