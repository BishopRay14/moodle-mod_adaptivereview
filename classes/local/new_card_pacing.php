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
 * Enforces gradual new-card introduction while preserving browse access.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class new_card_pacing {

    /** @var int Maximum cards introduced per activity and student day. */
    public const DAILY_LIMIT = 5;

    /**
     * Count cards currently due in an activity.
     *
     * @param int $adaptivereviewid Activity instance ID.
     * @param int $userid User ID.
     * @param int|null $now Testable server time.
     * @return int
     */
    public static function due_count(int $adaptivereviewid, int $userid, ?int $now = null): int {
        global $DB;

        $now = $now ?? time();

        return (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {adaptivereview_set_cards} sc
               JOIN {adaptivereview} ar ON ar.setid = sc.setid
               JOIN {adaptivereview_cards} c ON c.id = sc.cardid
               JOIN {adaptivereview_mastery} m ON m.cardid = c.id
              WHERE ar.id = :adaptivereviewid
                AND c.archived = 0
                AND m.userid = :userid
                AND m.nextreviewdate > 0
                AND m.nextreviewdate <= :now",
            [
                'adaptivereviewid' => $adaptivereviewid,
                'userid' => $userid,
                'now' => $now,
            ]
        );
    }

    /**
     * Return the number of new cards that can still be introduced today.
     *
     * The day boundary follows the student's Moodle timezone. Attempts are
     * retained as the source of truth, so resetting mastery cannot bypass the
     * daily allowance.
     *
     * @param int $adaptivereviewid Activity instance ID.
     * @param int $userid User ID.
     * @param int|null $now Testable server time.
     * @return int
     */
    public static function remaining_today(int $adaptivereviewid, int $userid, ?int $now = null): int {
        global $DB;

        $now = $now ?? time();
        $user = $DB->get_record('user', ['id' => $userid], 'id, timezone', MUST_EXIST);
        $timezone = \core_date::get_user_timezone($user);
        $daystart = usergetmidnight($now, $timezone);

        $introduced = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT a.cardid)
               FROM {adaptivereview_attempts} a
               JOIN {adaptivereview_sessions} s ON s.id = a.sessionid
              WHERE s.adaptivereviewid = :adaptivereviewid
                AND s.userid = :userid
                AND s.mode = :mode
                AND a.attemptnumber = 1
                AND a.timecreated >= :daystart
                AND a.timecreated <= :now",
            [
                'adaptivereviewid' => $adaptivereviewid,
                'userid' => $userid,
                'mode' => review_manager::MODE_NEW,
                'daystart' => $daystart,
                'now' => $now,
            ]
        );

        return max(0, self::DAILY_LIMIT - $introduced);
    }

    /**
     * Require that a student may introduce another new card.
     *
     * @param int $adaptivereviewid Activity instance ID.
     * @param int $userid User ID.
     * @param int|null $now Testable server time.
     */
    public static function require_available(int $adaptivereviewid, int $userid, ?int $now = null): void {
        if (self::due_count($adaptivereviewid, $userid, $now) > 0) {
            throw new \moodle_exception('newcardsblockedbydue', 'mod_adaptivereview');
        }

        if (self::remaining_today($adaptivereviewid, $userid, $now) === 0) {
            throw new \moodle_exception('newcardlimitreached', 'mod_adaptivereview');
        }
    }
}
