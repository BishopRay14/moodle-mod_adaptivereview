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

    /** @var string Mastery scheduler behavior identifier. */
    public const ALGORITHM_VERSION = 'mastery-v1';

    /** @var float Maximum lifelong mastery score. */
    public const MAX_MASTERY = 5.9;

    /** @var float Full gain for a successful scheduled review. */
    public const FULL_REVIEW_GAIN = 0.5;

    /** @var int Stored mastery precision. */
    private const MASTERY_PRECISION = 3;

    /** @var int Each failed review shortens the active interval by this factor. */
    private const RECOVERY_DIVISOR = 3;

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
     * Return the required interval for a mastery score.
     *
     * @param float $mastery Mastery score.
     * @return int Required interval in days.
     */
    public static function required_interval_days(float $mastery): int {
        if ($mastery < 1.0) {
            return 1;
        }
        if ($mastery < 2.0) {
            return 3;
        }
        if ($mastery < 3.0) {
            return 5;
        }
        if ($mastery < 4.0) {
            return 21;
        }
        if ($mastery < 5.0) {
            return 90;
        }

        return 270;
    }

    /**
     * Calculate a successful review gain from elapsed retention time.
     *
     * @param int $elapsedseconds Seconds since the prior attempt.
     * @param int $requiredintervaldays Mastery-based interval in days.
     * @return float Mastery gain capped at the full scheduled gain.
     */
    public static function elapsed_mastery_gain(int $elapsedseconds, int $requiredintervaldays): float {
        $requiredseconds = max(1, $requiredintervaldays) * DAYSECS;
        $retentionratio = max(0.0, $elapsedseconds / $requiredseconds);

        return round(
            min(self::FULL_REVIEW_GAIN, self::FULL_REVIEW_GAIN * $retentionratio),
            self::MASTERY_PRECISION
        );
    }

    /**
     * Return the next recovery interval after an unsuccessful review.
     *
     * @param int $requiredintervaldays Mastery-based interval in days.
     * @param int $activeintervaldays Current regular or recovery interval in days.
     * @return int Shorter recovery interval in days.
     */
    public static function recovery_interval_days(int $requiredintervaldays, int $activeintervaldays): int {
        $basis = min(max(1, $requiredintervaldays), max(1, $activeintervaldays));
        return max(1, (int) ceil($basis / self::RECOVERY_DIVISOR));
    }

    /**
     * Record one retrieval attempt and update mastery-v1 scheduling.
     *
     * First attempts in a matching New, Due, or early-practice session may
     * update summary state. Same-session remediation remains history-only.
     *
     * @param stdClass $session Active review session.
     * @param stdClass $item Global repository card.
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
        $setid = $DB->get_field('adaptivereview', 'setid', ['id' => $session->adaptivereviewid], MUST_EXIST);
        if (!$DB->record_exists('adaptivereview_set_cards', [
                'setid' => $setid,
                'cardid' => $item->id,
            ])) {
            throw new \invalid_parameter_exception('The card does not belong to this review session.');
        }

        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();

        $attemptnumber = $DB->count_records('adaptivereview_attempts', [
            'sessionid' => $session->id,
            'cardid' => $item->id,
        ]) + 1;
        $isremediation = $attemptnumber > 1;

        $mastery = $DB->get_record('adaptivereview_mastery', [
            'userid' => $session->userid,
            'cardid' => $item->id,
        ]);
        $isnew = !$mastery;

        $masterybefore = $mastery
            ? min(self::MAX_MASTERY, max(0.0, (float) $mastery->masteryscore))
            : 0.0;
        $intervalbefore = self::required_interval_days($masterybefore);
        $previousattemptdate = $mastery ? (int) $mastery->last_reviewed : 0;
        $previousreviewdate = $mastery ? (int) $mastery->lastreviewdate : 0;
        $previousduedate = $mastery ? (int) $mastery->nextreviewdate : 0;
        $wasdue = !$isnew && $previousduedate > 0 && $previousduedate <= (int) $session->timecreated;

        $validfirstattempt = !$isremediation && (
            ($session->mode === self::MODE_NEW && $isnew) ||
            ($session->mode === self::MODE_DUE && $wasdue) ||
            ($session->mode === self::MODE_EARLY && !$isnew && !$wasdue)
        );
        $masteryeligible = $validfirstattempt && $response === self::RESPONSE_KNOW;
        $masterygain = 0.0;
        $masteryafter = $masterybefore;
        $intervalafter = $mastery
            ? self::active_interval_days($previousattemptdate, $previousduedate, $intervalbefore)
            : 0;

        if ($validfirstattempt) {
            if (!$mastery) {
                $mastery = self::new_mastery_record((int) $session->userid, (int) $item->id);
            }

            $mastery->last_reviewed = $now;

            if ($response === self::RESPONSE_KNOW) {
                if ($isnew) {
                    $masterygain = self::FULL_REVIEW_GAIN;
                } else {
                    $gainanchor = $previousattemptdate ?: $previousreviewdate;
                    $elapsedseconds = $gainanchor > 0 ? max(0, $now - $gainanchor) : 0;
                    $masterygain = self::elapsed_mastery_gain($elapsedseconds, $intervalbefore);
                }

                $masteryafter = round(
                    min(self::MAX_MASTERY, $masterybefore + $masterygain),
                    self::MASTERY_PRECISION
                );
                $masterygain = round($masteryafter - $masterybefore, self::MASTERY_PRECISION);
                $intervalafter = self::required_interval_days($masteryafter);

                $mastery->masteryscore = $masteryafter;
                $mastery->box_number = min(5, (int) floor($masteryafter));
                $mastery->requiredintervaldays = $intervalafter;
                $mastery->nextreviewdate = $now + ($intervalafter * DAYSECS);
                $mastery->count_correct++;
                $mastery->lastreviewdate = $now;
            } else {
                $activeinterval = self::active_interval_days(
                    $previousattemptdate,
                    $previousduedate,
                    $intervalbefore
                );
                $intervalafter = self::recovery_interval_days($intervalbefore, $activeinterval);

                $mastery->masteryscore = $masterybefore;
                $mastery->requiredintervaldays = $intervalbefore;
                $mastery->nextreviewdate = $now + ($intervalafter * DAYSECS);
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
            'cardid' => $item->id,
            'attemptnumber' => $attemptnumber,
            'response' => $response,
            'wasdue' => (int) $wasdue,
            'isremediation' => (int) $isremediation,
            'masteryeligible' => (int) $masteryeligible,
            'masterybefore' => $masterybefore,
            'masterygain' => $masterygain,
            'masteryafter' => $masteryafter,
            'intervalbefore' => $intervalbefore,
            'intervalafter' => $intervalafter,
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
     * Resolve the active regular or recovery interval from summary dates.
     *
     * @param int $lastattemptdate Last summary-changing attempt timestamp.
     * @param int $nextreviewdate Current due timestamp.
     * @param int $fallbackdays Mastery-based interval when dates are unavailable.
     * @return int Active interval in days.
     */
    private static function active_interval_days(
        int $lastattemptdate,
        int $nextreviewdate,
        int $fallbackdays
    ): int {
        if ($lastattemptdate > 0 && $nextreviewdate > $lastattemptdate) {
            return max(1, (int) ceil(($nextreviewdate - $lastattemptdate) / DAYSECS));
        }

        return max(1, $fallbackdays);
    }

    /**
     * Create a default mutable mastery summary row.
     *
     * @param int $userid User ID.
     * @param int $cardid Card ID.
     * @return stdClass
     */
    private static function new_mastery_record(int $userid, int $cardid): \stdClass {
        return (object) [
            'id' => 0,
            'userid' => $userid,
            'cardid' => $cardid,
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
