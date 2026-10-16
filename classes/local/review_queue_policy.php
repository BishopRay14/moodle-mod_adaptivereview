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
 * Enforces review-queue priority while preserving browse access.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class review_queue_policy {

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
     * Require that due reviews are complete before introducing a new card.
     *
     * @param int $adaptivereviewid Activity instance ID.
     * @param int $userid User ID.
     * @param int|null $now Testable server time.
     */
    public static function require_new_cards_available(
        int $adaptivereviewid,
        int $userid,
        ?int $now = null
    ): void {
        if (self::due_count($adaptivereviewid, $userid, $now) > 0) {
            throw new \moodle_exception('newcardsblockedbydue', 'mod_adaptivereview');
        }
    }
}
