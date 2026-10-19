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
 * External functions unit tests for mod_adaptivereview.
 *
 * @package   mod_adaptivereview
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_adaptivereview\external;

use core_external\external_api;
use mod_adaptivereview\external;

defined('MOODLE_INTERNAL') || die();

/**
 * External functions unit tests for mod_adaptivereview.
 */
final class external_test extends \core_external\tests\externallib_testcase {

    /**
     * Set up for every test
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Helper to create a basic adaptivereview activity with some cards.
     */
    protected function create_adaptivereview_activity() {
        global $DB;

        // Create course and user.
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        // Create adaptivereview instance.
        $adaptivereview = $this->getDataGenerator()->create_module('adaptivereview', ['course' => $course->id]);

        // Clear default demo cards inserted by the mod_form/lib.php to start fresh.
        $democardids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?',
            [$adaptivereview->setid]);
        $DB->delete_records('adaptivereview_set_cards', ['setid' => $adaptivereview->setid]);
        if ($democardids) {
            [$insql, $params] = $DB->get_in_or_equal($democardids);
            $DB->delete_records_select('adaptivereview_cards', "id $insql", $params);
        }

        // Insert a test card.
        $card = new \stdClass();
        $card->question = 'What is the capital of France?';
        $card->answer = 'Paris';
        $card->hint = 'City of light';
        $card->archived = 0;
        $card->createdby = 0;
        $card->modifiedby = 0;
        $card->timecreated = time();
        $card->timemodified = time();
        $card->id = $DB->insert_record('adaptivereview_cards', $card);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $adaptivereview->setid,
            'cardid' => $card->id,
            'sortorder' => 0,
            'timecreated' => time(),
        ]);

        return [$course, $student, $adaptivereview, $card];
    }

    /**
     * Add a card to an activity's repository set.
     *
     * @param stdClass $adaptivereview Activity record.
     * @param int $number Card number used in test content and ordering.
     * @return stdClass
     */
    protected function add_card(\stdClass $adaptivereview, int $number): \stdClass {
        global $DB;

        $card = (object) [
            'question' => "Question {$number}",
            'answer' => "Answer {$number}",
            'hint' => '',
            'archived' => 0,
            'createdby' => 0,
            'modifiedby' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $card->id = $DB->insert_record('adaptivereview_cards', $card);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $adaptivereview->setid,
            'cardid' => $card->id,
            'sortorder' => $number,
            'timecreated' => time(),
        ]);

        return $card;
    }

    /**
     * Create a lifelong mastery summary for a test card.
     *
     * @param int $userid User ID.
     * @param int $cardid Card ID.
     * @param float $score Mastery score.
     * @param int $nextreviewdate Next review timestamp.
     * @param int $lastreviewdate Last successful review timestamp.
     */
    protected function create_mastery(
        int $userid,
        int $cardid,
        float $score,
        int $nextreviewdate,
        int $lastreviewdate
    ): void {
        global $DB;

        $DB->insert_record('adaptivereview_mastery', (object) [
            'userid' => $userid,
            'cardid' => $cardid,
            'box_number' => min(5, (int) floor($score)),
            'status' => 0,
            'count_correct' => 1,
            'count_wrong' => 0,
            'last_reviewed' => $lastreviewdate,
            'masteryscore' => $score,
            'requiredintervaldays' => 21,
            'nextreviewdate' => $nextreviewdate,
            'lastreviewdate' => $lastreviewdate,
        ]);
    }

    /**
     * Test getting cards by box.
     */
    public function test_get_cards_by_box() {
        list($course, $student, $adaptivereview, $card) = $this->create_adaptivereview_activity();

        // Must be logged in as the student.
        $this->setUser($student);

        // Call the external function for category 1 (New cards).
        $result = external::get_cards_by_box($adaptivereview->id, 1);

        // We need to execute the return values cleaning process to simulate the web service server.
        $result = external_api::clean_returnvalue(external::get_cards_by_box_returns(), $result);

        $this->assertCount(1, $result);
        $this->assertEquals($card->question, $result[0]['question']);
        $this->assertEquals($card->answer, $result[0]['answer']);
    }

    /**
     * The New queue exposes every unstarted card when no reviews are due.
     */
    public function test_new_queue_has_no_daily_cap(): void {
        global $DB;

        [$course, $student, $adaptivereview] = $this->create_adaptivereview_activity();
        for ($number = 2; $number <= 7; $number++) {
            $this->add_card($adaptivereview, $number);
        }
        $this->setUser($student);

        $counts = external::get_box_counts($adaptivereview->id);
        $counts = external_api::clean_returnvalue(external::get_box_counts_returns(), $counts);
        $this->assertEquals(7, $counts[1]['count']);
        $this->assertEquals(7, $counts[3]['count']);

        $newcards = external::get_cards_by_box($adaptivereview->id, 1);
        $this->assertCount(7, $newcards);

        $session = external::start_review_session($adaptivereview->id, 'new');
        foreach ($newcards as $card) {
            external::submit_answer($card['id'], 'know', $session['sessionkey']);
        }

        $counts = external::get_box_counts($adaptivereview->id);
        $this->assertEquals(0, $counts[1]['count']);
        $this->assertCount(0, external::get_cards_by_box($adaptivereview->id, 1));
        $this->assertCount(7, external::get_cards_by_box($adaptivereview->id, 3));
        $this->assertEquals(7, $DB->count_records('adaptivereview_mastery', [
            'userid' => $student->id,
        ]));
    }

    /**
     * Due reviews pause New without hiding the complete browseable set.
     */
    public function test_due_cards_pause_new_queue(): void {
        global $DB;

        [$course, $student, $adaptivereview, $duecard] = $this->create_adaptivereview_activity();
        $newcard = $this->add_card($adaptivereview, 2);
        $this->setUser($student);

        $session = external::start_review_session($adaptivereview->id, 'new');
        external::submit_answer($duecard->id, 'know', $session['sessionkey']);
        $DB->set_field('adaptivereview_mastery', 'nextreviewdate', time() - 1, [
            'userid' => $student->id,
            'cardid' => $duecard->id,
        ]);

        $counts = external::get_box_counts($adaptivereview->id);
        $this->assertEquals(1, $counts[0]['count']);
        $this->assertEquals(1, $counts[1]['count']);
        $this->assertCount(0, external::get_cards_by_box($adaptivereview->id, 1));
        $this->assertCount(2, external::get_cards_by_box($adaptivereview->id, 3));

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('newcardsblockedbydue', 'mod_adaptivereview'));
        external::submit_answer($newcard->id, 'know', $session['sessionkey']);
    }

    /**
     * Student summary uses the selected global set and the activity-specific goal.
     */
    public function test_student_summary_is_authoritative_for_shared_set(): void {
        global $DB;

        [$course, $student, $adaptivereview, $firstcard] = $this->create_adaptivereview_activity();
        $secondcard = $this->add_card($adaptivereview, 2);
        $thirdcard = $this->add_card($adaptivereview, 3);
        $this->add_card($adaptivereview, 4);
        $now = time();

        $this->create_mastery($student->id, $firstcard->id, 0.5, $now + DAYSECS, $now - DAYSECS);
        $this->create_mastery($student->id, $secondcard->id, 2.0, $now - 1, $now - (7 * DAYSECS));
        $this->create_mastery($student->id, $thirdcard->id, 5.5, $now + DAYSECS, $now - DAYSECS);
        $DB->set_field('adaptivereview', 'masterygoal', 4.0, ['id' => $adaptivereview->id]);

        $secondactivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'Second goal',
            'setid' => $adaptivereview->setid,
            'masterygoal' => 2.0,
        ]);
        $this->setUser($student);

        $summary = external::get_student_summary($adaptivereview->id);
        $summary = external_api::clean_returnvalue(external::get_student_summary_returns(), $summary);
        $this->assertEquals(1, $summary['duecount']);
        $this->assertEquals(1, $summary['newcount']);
        $this->assertEquals(2, $summary['recentcount']);
        $this->assertEquals(4, $summary['totalcards']);
        $this->assertEquals(2.0, $summary['setmastery']);
        $this->assertEquals(4.0, $summary['masterygoal']);
        $this->assertEquals(40.6, $summary['progresspercent']);
        $this->assertEquals(1, $summary['cardsatgoal']);
        $this->assertFalse($summary['newavailable']);

        $secondsummary = external::get_student_summary($secondactivity->id);
        $secondsummary = external_api::clean_returnvalue(
            external::get_student_summary_returns(),
            $secondsummary
        );
        $this->assertEquals(2.0, $secondsummary['setmastery']);
        $this->assertEquals(2.0, $secondsummary['masterygoal']);
        $this->assertEquals(56.3, $secondsummary['progresspercent']);
        $this->assertEquals(2, $secondsummary['cardsatgoal']);

        // The dashboard shows the real New count even while the queue is locked.
        $counts = external::get_box_counts($adaptivereview->id);
        $this->assertEquals(1, $counts[1]['count']);
        $this->assertCount(0, external::get_cards_by_box($adaptivereview->id, 1));
    }

    /**
     * Browsing All Cards reads global state without creating retrieval evidence.
     */
    public function test_browsing_all_cards_does_not_change_mastery_or_history(): void {
        global $DB;

        [$course, $student, $adaptivereview, $card] = $this->create_adaptivereview_activity();
        $this->setUser($student);

        $cards = external::get_cards_by_box($adaptivereview->id, 3);

        $this->assertCount(1, $cards);
        $this->assertEquals($card->id, $cards[0]['id']);
        $this->assertEquals(0, $DB->count_records('adaptivereview_mastery'));
        $this->assertEquals(0, $DB->count_records('adaptivereview_sessions'));
        $this->assertEquals(0, $DB->count_records('adaptivereview_attempts'));
    }

    /**
     * Test first attempts and same-session remediation history.
     */
    public function test_submit_answer() {
        global $DB;
        list($course, $student, $adaptivereview, $card) = $this->create_adaptivereview_activity();

        $this->setUser($student);

        $session = external::start_review_session($adaptivereview->id, 'new');
        $session = external_api::clean_returnvalue(external::start_review_session_returns(), $session);

        $result = external::submit_answer($card->id, 'know', $session['sessionkey']);
        $result = external_api::clean_returnvalue(external::submit_answer_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['attemptnumber']);
        $this->assertTrue($result['masteryeligible']);
        $this->assertEquals(0.5, $result['masterygain']);

        $progress = $DB->get_record('adaptivereview_mastery', ['userid' => $student->id, 'cardid' => $card->id]);
        $this->assertEquals(0.5, $progress->masteryscore);
        $this->assertEquals(1, $progress->count_correct);
        $this->assertGreaterThan(time(), $progress->nextreviewdate);

        $nextreviewdate = $progress->nextreviewdate;
        $result = external::submit_answer($card->id, 'notyet', $session['sessionkey']);
        $result = external_api::clean_returnvalue(external::submit_answer_returns(), $result);

        $this->assertEquals(2, $result['attemptnumber']);
        $this->assertFalse($result['masteryeligible']);

        $progress = $DB->get_record('adaptivereview_mastery', ['userid' => $student->id, 'cardid' => $card->id]);
        $this->assertEquals(0.5, $progress->masteryscore);
        $this->assertEquals($nextreviewdate, $progress->nextreviewdate);
        $this->assertEquals(2, $DB->count_records('adaptivereview_attempts', ['cardid' => $card->id]));

        $repeat = $DB->get_record('adaptivereview_attempts', [
            'cardid' => $card->id,
            'attemptnumber' => 2,
        ]);
        $this->assertEquals(1, $repeat->isremediation);
    }

    /**
     * Test that Not Yet schedules recovery without reducing mastery.
     */
    public function test_not_yet_does_not_reduce_mastery() {
        global $DB;
        list($course, $student, $adaptivereview, $card) = $this->create_adaptivereview_activity();
        $this->setUser($student);

        $session = external::start_review_session($adaptivereview->id, 'new');
        $result = external::submit_answer($card->id, 'notyet', $session['sessionkey']);
        $result = external_api::clean_returnvalue(external::submit_answer_returns(), $result);

        $this->assertFalse($result['masteryeligible']);
        $this->assertEquals(0.0, $result['masterybefore']);
        $this->assertEquals(0.0, $result['masterygain']);
        $this->assertEquals(0.0, $result['masteryafter']);

        $progress = $DB->get_record('adaptivereview_mastery', [
            'userid' => $student->id,
            'cardid' => $card->id,
        ]);
        $this->assertEquals(0.0, $progress->masteryscore);
        $this->assertEquals(1, $progress->count_wrong);
        $this->assertEquals(0, $progress->lastreviewdate);
        $this->assertGreaterThan(time(), $progress->nextreviewdate);
    }

    /**
     * Test that early practice is recorded without changing summary state.
     */
    public function test_early_practice_records_history_only() {
        global $DB;
        list($course, $student, $adaptivereview, $card) = $this->create_adaptivereview_activity();
        $this->setUser($student);

        $session = external::start_review_session($adaptivereview->id, 'early');
        $result = external::submit_answer($card->id, 'know', $session['sessionkey']);
        $result = external_api::clean_returnvalue(external::submit_answer_returns(), $result);

        $this->assertFalse($result['masteryeligible']);
        $this->assertEquals(0, $result['nextreviewdate']);
        $this->assertFalse($DB->record_exists('adaptivereview_mastery', [
            'userid' => $student->id,
            'cardid' => $card->id,
        ]));
        $this->assertEquals(1, $DB->count_records('adaptivereview_attempts'));
    }

    /**
     * Mastery retains calculation precision while the student API displays one decimal.
     */
    public function test_card_mastery_is_displayed_to_one_decimal(): void {
        global $DB;

        [$course, $student, $adaptivereview, $card] = $this->create_adaptivereview_activity();
        $this->setUser($student);

        $session = external::start_review_session($adaptivereview->id, 'new');
        external::submit_answer($card->id, 'know', $session['sessionkey']);
        $DB->set_field('adaptivereview_mastery', 'masteryscore', 3.367, [
            'userid' => $student->id,
            'cardid' => $card->id,
        ]);

        $cards = external::get_cards_by_box($adaptivereview->id, 3);
        $cards = external_api::clean_returnvalue(external::get_cards_by_box_returns(), $cards);

        $this->assertCount(1, $cards);
        $this->assertEquals(3.4, $cards[0]['masteryscore']);
        $storedscore = $DB->get_field('adaptivereview_mastery', 'masteryscore', [
            'userid' => $student->id,
            'cardid' => $card->id,
        ]);
        $this->assertEquals(3.367, (float) $storedscore);
    }

    /**
     * Test resetting progress.
     */
    public function test_reset_progress() {
        global $DB;
        list($course, $student, $adaptivereview, $card) = $this->create_adaptivereview_activity();

        $this->setUser($student);

        // Submit an answer to create a progress record.
        $session = external::start_review_session($adaptivereview->id, 'new');
        external::submit_answer($card->id, 'know', $session['sessionkey']);
        
        // Ensure progress exists.
        $this->assertEquals(1, $DB->count_records('adaptivereview_mastery', ['userid' => $student->id]));

        // Call reset.
        $result = external::reset_progress($adaptivereview->id);
        $result = external_api::clean_returnvalue(external::reset_progress_returns(), $result);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['reset_count']);

        // Progress should be completely gone for this user/instance.
        $this->assertEquals(0, $DB->count_records('adaptivereview_mastery', ['userid' => $student->id]));
        $this->assertEquals(1, $DB->count_records('adaptivereview_attempts'));
        $this->assertEquals(1, $DB->count_records('adaptivereview_sessions'));
    }
    /**
     * Test that invalid box number throws exception.
     */
    public function test_get_cards_by_box_invalid_box() {
        list($course, $student, $adaptivereview, $card) = $this->create_adaptivereview_activity();
        $this->setUser($student);
        $this->expectException(\moodle_exception::class);
        external::get_cards_by_box($adaptivereview->id, 99);
    }

    /**
     * Test completion state: min_cards condition uses DISTINCT card count.
     */
    public function test_completion_min_cards_counts_unique_cards() {
        global $DB;
        list($course, $student, $adaptivereview, $card) = $this->create_adaptivereview_activity();
        $this->setUser($student);

        $session = external::start_review_session($adaptivereview->id, 'new');

        // Record the same card five times in one session.
        for ($i = 0; $i < 5; $i++) {
            external::submit_answer($card->id, 'know', $session['sessionkey']);
        }

        // Despite five attempts, only one mutable summary row exists.
        $progress_count = $DB->count_records('adaptivereview_mastery', ['userid' => $student->id]);
        $this->assertEquals(1, $progress_count);
        $this->assertEquals(5, $DB->count_records('adaptivereview_attempts'));
    }
}
