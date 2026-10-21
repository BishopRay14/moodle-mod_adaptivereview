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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_adaptivereview\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the authoritative student summary for one selected repository set.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class student_summary {
    /**
     * Calculate queue counts and progress for an activity and learner.
     *
     * Set mastery is the average lifelong mastery of every active card in the
     * selected set. Progress toward goal averages each card's capped progress,
     * so mastery above the goal on one card cannot compensate for an unlearned
     * card elsewhere in the set.
     *
     * @param int $adaptivereviewid Activity instance ID.
     * @param int $userid User ID.
     * @param int|null $now Testable server time.
     * @return \stdClass
     */
    public static function get(int $adaptivereviewid, int $userid, ?int $now = null): \stdClass {
        global $DB;

        $activity = $DB->get_record(
            'adaptivereview',
            ['id' => $adaptivereviewid],
            'id, setid, masterygoal',
            MUST_EXIST
        );
        $now = $now ?? time();
        $goal = max(0.1, (float) $activity->masterygoal);

        $cards = $DB->get_records_sql(
            "SELECT c.id,
                    COALESCE(m.masteryscore, 0) AS masteryscore,
                    COALESCE(m.nextreviewdate, 0) AS nextreviewdate,
                    COALESCE(m.lastreviewdate, 0) AS lastreviewdate,
                    m.id AS masteryid
               FROM {adaptivereview_set_cards} sc
               JOIN {adaptivereview_cards} c ON c.id = sc.cardid
          LEFT JOIN {adaptivereview_mastery} m
                 ON m.cardid = c.id AND m.userid = :userid
              WHERE sc.setid = :setid
                AND c.archived = 0",
            [
                'userid' => $userid,
                'setid' => $activity->setid,
            ]
        );

        $summary = (object) [
            'duecount' => 0,
            'newcount' => 0,
            'recentcount' => 0,
            'totalcards' => count($cards),
            'setmastery' => 0.0,
            'masterygoal' => $goal,
            'progresspercent' => 0.0,
            'cardsatgoal' => 0,
            'newavailable' => true,
        ];

        $masterytotal = 0.0;
        $progressunits = 0.0;
        foreach ($cards as $card) {
            $score = min(review_manager::MAX_MASTERY, max(0.0, (float) $card->masteryscore));
            $masterytotal += $score;
            $progressunits += min(1.0, $score / $goal);

            if ($score >= $goal) {
                $summary->cardsatgoal++;
            }
            if (empty($card->masteryid)) {
                $summary->newcount++;
            } else if ((int) $card->nextreviewdate > 0 && (int) $card->nextreviewdate <= $now) {
                $summary->duecount++;
            } else if ((int) $card->lastreviewdate > 0 && (int) $card->nextreviewdate > $now) {
                $summary->recentcount++;
            }
        }

        if ($summary->totalcards > 0) {
            $summary->setmastery = round($masterytotal / $summary->totalcards, 3);
            $summary->progresspercent = round(($progressunits / $summary->totalcards) * 100, 1);
        }
        $summary->newavailable = $summary->duecount === 0;

        return $summary;
    }
}
