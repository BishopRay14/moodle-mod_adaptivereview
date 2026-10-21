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

namespace mod_adaptivereview;

use mod_adaptivereview\local\review_manager;

/**
 * Deterministic mastery-v1 scheduler tests.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class review_manager_test extends \advanced_testcase {
    /**
     * Required intervals follow the approved mastery bands.
     */
    public function test_required_interval_boundaries(): void {
        $cases = [
            [0.0, 1],
            [0.9, 1],
            [1.0, 3],
            [1.9, 3],
            [2.0, 7],
            [2.4, 7],
            [2.5, 15],
            [2.9, 15],
            [3.0, 21],
            [3.4, 21],
            [3.5, 30],
            [3.9, 30],
            [4.0, 60],
            [4.4, 60],
            [4.5, 90],
            [4.9, 90],
            [5.0, 150],
            [5.4, 150],
            [5.5, 270],
            [5.9, 270],
        ];

        foreach ($cases as [$mastery, $expecteddays]) {
            $this->assertSame($expecteddays, review_manager::required_interval_days($mastery));
        }
    }

    /**
     * Two successful new-card reviews move the card to a three-day interval.
     */
    public function test_second_success_moves_card_to_three_day_interval(): void {
        [$student, $activity, $card] = $this->create_review_context();
        $base = 750000;

        $newsession = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_NEW,
            $base
        );
        $firstattempt = review_manager::record_attempt(
            $newsession,
            $card,
            review_manager::RESPONSE_KNOW,
            $base
        );
        $this->assertEquals(0.5, (float) $firstattempt->masteryafter);
        $this->assertEquals(1, $firstattempt->intervalafter);

        $secondreviewtime = $base + DAYSECS;
        $duesession = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_DUE,
            $secondreviewtime
        );
        $secondattempt = review_manager::record_attempt(
            $duesession,
            $card,
            review_manager::RESPONSE_KNOW,
            $secondreviewtime
        );

        $this->assertEquals(1.0, (float) $secondattempt->masteryafter);
        $this->assertEquals(3, $secondattempt->intervalafter);
        $this->assertEquals($secondreviewtime + (3 * DAYSECS), $secondattempt->nextreviewdate);
    }

    /**
     * Perfect on-time retrieval reaches 3.0 in the approved 29-day timeline.
     */
    public function test_perfect_retrieval_reaches_three_point_zero_after_twenty_nine_days(): void {
        $mastery = 0.0;
        $elapseddaystotal = 0;

        // The first successful retrieval introduces the card immediately.
        $mastery += review_manager::FULL_REVIEW_GAIN;
        while ($mastery < 3.0) {
            $intervaldays = review_manager::required_interval_days($mastery);
            $elapseddaystotal += $intervaldays;
            $mastery += review_manager::elapsed_mastery_gain($intervaldays * DAYSECS, $intervaldays);
        }

        $this->assertEquals(3.0, $mastery);
        $this->assertSame(29, $elapseddaystotal);
    }

    /**
     * Early gains are proportional and cannot exceed the scheduled gain.
     */
    public function test_elapsed_gain_is_proportional_and_capped(): void {
        $this->assertEquals(0.333, review_manager::elapsed_mastery_gain(14 * DAYSECS, 21));
        $this->assertEquals(0.5, review_manager::elapsed_mastery_gain(21 * DAYSECS, 21));
        $this->assertEquals(0.5, review_manager::elapsed_mastery_gain(30 * DAYSECS, 21));
        $this->assertEquals(0.0, review_manager::elapsed_mastery_gain(0, 21));
    }

    /**
     * A successful early review earns credit for elapsed retention time.
     */
    public function test_early_review_earns_partial_gain(): void {
        [$student, $activity, $card] = $this->create_review_context();
        $base = 1000000;
        $this->create_mastery($student->id, $card->id, 3.0, $base, 21);

        $now = $base + (14 * DAYSECS);
        $session = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_EARLY,
            $now
        );
        $attempt = review_manager::record_attempt($session, $card, review_manager::RESPONSE_KNOW, $now);

        $this->assertTrue((bool) $attempt->masteryeligible);
        $this->assertEquals(0.333, (float) $attempt->masterygain);
        $this->assertEquals(3.333, (float) $attempt->masteryafter);
        $this->assertEquals(21, $attempt->intervalafter);
        $this->assertEquals($now + (21 * DAYSECS), $attempt->nextreviewdate);
    }

    /**
     * A correct due or late review earns the full gain and uses the new band.
     */
    public function test_due_review_earns_full_gain_and_advances_interval(): void {
        [$student, $activity, $card] = $this->create_review_context();
        $base = 2000000;
        $this->create_mastery($student->id, $card->id, 2.8, $base, 5);

        $now = $base + (7 * DAYSECS);
        $session = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_DUE,
            $now
        );
        $attempt = review_manager::record_attempt($session, $card, review_manager::RESPONSE_KNOW, $now);

        $this->assertEquals(0.5, (float) $attempt->masterygain);
        $this->assertEquals(3.3, (float) $attempt->masteryafter);
        $this->assertEquals(21, $attempt->intervalafter);
        $this->assertEquals($now + (21 * DAYSECS), $attempt->nextreviewdate);
    }

    /**
     * Recovery success earns only the retention demonstrated by that interval.
     */
    public function test_recovery_review_earns_partial_gain_without_prior_loss(): void {
        global $DB;

        [$student, $activity, $card] = $this->create_review_context();
        $base = 3000000;
        $this->create_mastery($student->id, $card->id, 3.2, $base, 21);

        $failuretime = $base + (21 * DAYSECS);
        $failuresession = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_DUE,
            $failuretime
        );
        $failure = review_manager::record_attempt(
            $failuresession,
            $card,
            review_manager::RESPONSE_NOTYET,
            $failuretime
        );

        $this->assertEquals(3.2, (float) $failure->masteryafter);
        $this->assertEquals(0.0, (float) $failure->masterygain);
        $this->assertEquals(7, $failure->intervalafter);
        $this->assertEquals($failuretime + (7 * DAYSECS), $failure->nextreviewdate);

        $recoverytime = $failuretime + (7 * DAYSECS);
        $recoverysession = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_DUE,
            $recoverytime
        );
        $recovery = review_manager::record_attempt(
            $recoverysession,
            $card,
            review_manager::RESPONSE_KNOW,
            $recoverytime
        );

        $this->assertEquals(0.167, (float) $recovery->masterygain);
        $this->assertEquals(3.367, (float) $recovery->masteryafter);
        $this->assertEquals(21, $recovery->intervalafter);

        $mastery = $DB->get_record('adaptivereview_mastery', [
            'userid' => $student->id,
            'cardid' => $card->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(3.367, (float) $mastery->masteryscore);
        $this->assertEquals(1, $mastery->count_wrong);
        $this->assertEquals(1, $mastery->count_correct);
    }

    /**
     * Immediate remediation after a failure is history-only and earns no mastery.
     */
    public function test_same_session_recovery_cannot_increase_mastery(): void {
        global $DB;

        [$student, $activity, $card] = $this->create_review_context();
        $base = 3500000;
        $session = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_NEW,
            $base
        );

        $failure = review_manager::record_attempt(
            $session,
            $card,
            review_manager::RESPONSE_NOTYET,
            $base
        );
        $recoveryduedate = $failure->nextreviewdate;

        $immediatesuccess = review_manager::record_attempt(
            $session,
            $card,
            review_manager::RESPONSE_KNOW,
            $base + 60
        );

        $this->assertEquals(2, $immediatesuccess->attemptnumber);
        $this->assertTrue((bool) $immediatesuccess->isremediation);
        $this->assertFalse((bool) $immediatesuccess->masteryeligible);
        $this->assertEquals(0.0, (float) $immediatesuccess->masterygain);
        $this->assertEquals(0.0, (float) $immediatesuccess->masteryafter);
        $this->assertEquals($recoveryduedate, $immediatesuccess->nextreviewdate);

        $mastery = $DB->get_record('adaptivereview_mastery', [
            'userid' => $student->id,
            'cardid' => $card->id,
        ], '*', MUST_EXIST);
        $this->assertEquals(0.0, (float) $mastery->masteryscore);
        $this->assertEquals(0, $mastery->count_correct);
        $this->assertEquals(1, $mastery->count_wrong);
        $this->assertEquals($recoveryduedate, $mastery->nextreviewdate);
    }

    /**
     * Repeated failures progressively shorten recovery without reducing mastery.
     */
    public function test_repeated_failures_shorten_recovery_interval(): void {
        [$student, $activity, $card] = $this->create_review_context();
        $base = 4000000;
        $this->create_mastery($student->id, $card->id, 4.2, $base, 60);

        $firstfailuretime = $base + (60 * DAYSECS);
        $firstsession = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_DUE,
            $firstfailuretime
        );
        $firstfailure = review_manager::record_attempt(
            $firstsession,
            $card,
            review_manager::RESPONSE_NOTYET,
            $firstfailuretime
        );
        $this->assertEquals(20, $firstfailure->intervalafter);
        $this->assertEquals(4.2, (float) $firstfailure->masteryafter);

        $secondfailuretime = $firstfailuretime + (20 * DAYSECS);
        $secondsession = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_DUE,
            $secondfailuretime
        );
        $secondfailure = review_manager::record_attempt(
            $secondsession,
            $card,
            review_manager::RESPONSE_NOTYET,
            $secondfailuretime
        );

        $this->assertEquals(7, $secondfailure->intervalafter);
        $this->assertEquals(4.2, (float) $secondfailure->masteryafter);
        $this->assertEquals($secondfailuretime + (7 * DAYSECS), $secondfailure->nextreviewdate);
    }

    /**
     * Mastery cannot exceed 5.9 even after a full successful review.
     */
    public function test_mastery_is_capped_at_five_point_nine(): void {
        [$student, $activity, $card] = $this->create_review_context();
        $base = 5000000;
        $this->create_mastery($student->id, $card->id, 5.8, $base, 270);

        $now = $base + (270 * DAYSECS);
        $session = review_manager::start_session(
            $activity->id,
            $student->id,
            review_manager::MODE_DUE,
            $now
        );
        $attempt = review_manager::record_attempt($session, $card, review_manager::RESPONSE_KNOW, $now);

        $this->assertEquals(0.1, (float) $attempt->masterygain);
        $this->assertEquals(5.9, (float) $attempt->masteryafter);
        $this->assertEquals(270, $attempt->intervalafter);
        $this->assertEquals($now + (270 * DAYSECS), $attempt->nextreviewdate);
    }

    /**
     * Create an activity, learner, and repository card for scheduler tests.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass}
     */
    private function create_review_context(): array {
        global $DB;

        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $activity = $this->getDataGenerator()->create_module('adaptivereview', ['course' => $course->id]);
        $cardids = $DB->get_fieldset_select(
            'adaptivereview_set_cards',
            'cardid',
            'setid = ?',
            [$activity->setid]
        );
        $cardid = reset($cardids);
        $this->assertNotFalse($cardid);
        $card = $DB->get_record('adaptivereview_cards', ['id' => $cardid], '*', MUST_EXIST);

        return [$student, $activity, $card];
    }

    /**
     * Create an existing mastery summary with a regular scheduled review.
     *
     * @param int $userid User ID.
     * @param int $cardid Card ID.
     * @param float $score Current mastery score.
     * @param int $lastattempttime Last successful attempt timestamp.
     * @param int $intervaldays Required interval in days.
     */
    private function create_mastery(
        int $userid,
        int $cardid,
        float $score,
        int $lastattempttime,
        int $intervaldays
    ): void {
        global $DB;

        $DB->insert_record('adaptivereview_mastery', (object) [
            'userid' => $userid,
            'cardid' => $cardid,
            'box_number' => min(5, (int) floor($score)),
            'status' => 0,
            'count_correct' => 0,
            'count_wrong' => 0,
            'last_reviewed' => $lastattempttime,
            'masteryscore' => $score,
            'requiredintervaldays' => $intervaldays,
            'nextreviewdate' => $lastattempttime + ($intervaldays * DAYSECS),
            'lastreviewdate' => $lastattempttime,
        ]);
    }
}
