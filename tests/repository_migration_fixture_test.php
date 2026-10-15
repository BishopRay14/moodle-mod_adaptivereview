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

namespace mod_adaptivereview;

use mod_adaptivereview\local\review_manager;

/**
 * Proves that legacy activity-owned data survives the repository migration.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class repository_migration_fixture_test extends \advanced_testcase {

    /**
     * Migrate a complete legacy fixture without changing card or history IDs.
     */
    public function test_legacy_fixture_migrates_without_data_loss(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $firststudent = $this->getDataGenerator()->create_user();
        $secondstudent = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($firststudent->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($secondstudent->id, $course->id, 'student');

        $firstactivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'First legacy activity',
        ]);
        $secondactivity = $this->getDataGenerator()->create_module('adaptivereview', [
            'course' => $course->id,
            'name' => 'Second legacy activity',
        ]);

        $firstcard = $this->replace_demo_cards($firstactivity, 'First activity answer');
        $secondcard = $this->replace_demo_cards($secondactivity, 'Second activity answer');

        $firstsession = review_manager::start_session(
            (int) $firstactivity->id,
            (int) $firststudent->id,
            review_manager::MODE_NEW,
            1000000
        );
        review_manager::record_attempt($firstsession, $firstcard, review_manager::RESPONSE_KNOW, 1000010);

        $secondsession = review_manager::start_session(
            (int) $secondactivity->id,
            (int) $secondstudent->id,
            review_manager::MODE_NEW,
            2000000
        );
        review_manager::record_attempt($secondsession, $secondcard, review_manager::RESPONSE_NOTYET, 2000010);

        $before = [
            'activities' => $DB->count_records('adaptivereview'),
            'cards' => $DB->count_records('adaptivereview_cards'),
            'mastery' => $DB->count_records('adaptivereview_mastery'),
            'sessions' => $DB->count_records('adaptivereview_sessions'),
            'attempts' => $DB->count_records('adaptivereview_attempts'),
        ];
        $cardids = [$firstcard->id, $secondcard->id];
        $masteryids = array_keys($DB->get_records('adaptivereview_mastery'));
        $attemptids = array_keys($DB->get_records('adaptivereview_attempts'));

        $this->convert_repository_schema_to_legacy();
        set_config('version', 2026081800, 'mod_adaptivereview');
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/mod/adaptivereview/db/upgrade.php');
        xmldb_adaptivereview_upgrade(2026081800);

        $this->assertEquals($before['activities'], $DB->count_records('adaptivereview'));
        $this->assertEquals($before['cards'], $DB->count_records('adaptivereview_cards'));
        $this->assertEquals($before['mastery'], $DB->count_records('adaptivereview_mastery'));
        $this->assertEquals($before['sessions'], $DB->count_records('adaptivereview_sessions'));
        $this->assertEquals($before['attempts'], $DB->count_records('adaptivereview_attempts'));
        $this->assertEqualsCanonicalizing($cardids, array_keys($DB->get_records('adaptivereview_cards')));
        $this->assertEqualsCanonicalizing($masteryids, array_keys($DB->get_records('adaptivereview_mastery')));
        $this->assertEqualsCanonicalizing($attemptids, array_keys($DB->get_records('adaptivereview_attempts')));

        $activities = $DB->get_records('adaptivereview', null, 'id ASC');
        foreach ($activities as $activity) {
            $this->assertGreaterThan(0, $activity->setid);
            $this->assertEquals(1,
                $DB->count_records('adaptivereview_set_cards', ['setid' => $activity->setid]));
        }

        $cards = $DB->get_records('adaptivereview_cards', null, 'id ASC');
        $this->assertCount(2, $cards);
        $this->assertEquals(reset($cards)->question, end($cards)->question);
        $this->assertNotEquals(reset($cards)->answer, end($cards)->answer);
        $this->assertEquals(0, $DB->count_records('adaptivereview_cards', ['archived' => 1]));

        foreach ($cardids as $cardid) {
            $this->assertTrue($DB->record_exists('adaptivereview_mastery', ['cardid' => $cardid]));
            $this->assertTrue($DB->record_exists('adaptivereview_attempts', ['cardid' => $cardid]));
        }
    }

    /**
     * Replace an activity's demos with one deliberately duplicate-worded card.
     *
     * @param stdClass $activity Activity record.
     * @param string $answer Distinct answer text.
     * @return stdClass
     */
    private function replace_demo_cards(\stdClass $activity, string $answer): \stdClass {
        global $DB;

        $cardids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?', [$activity->setid]);
        $DB->delete_records('adaptivereview_set_cards', ['setid' => $activity->setid]);
        if ($cardids) {
            [$insql, $params] = $DB->get_in_or_equal($cardids);
            $DB->delete_records_select('adaptivereview_cards', "id $insql", $params);
        }

        $card = (object) [
            'question' => 'Shared wording must not imply shared identity.',
            'answer' => $answer,
            'hint' => '',
            'category' => 'Migration fixture',
            'archived' => 0,
            'createdby' => 0,
            'modifiedby' => 0,
            'timecreated' => 900000,
            'timemodified' => 900000,
        ];
        $card->id = $DB->insert_record('adaptivereview_cards', $card);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $activity->setid,
            'cardid' => $card->id,
            'sortorder' => 0,
            'timecreated' => 900000,
        ]);

        return $card;
    }

    /**
     * Recreate the 0.2.0 schema around current fixture data.
     */
    private function convert_repository_schema_to_legacy(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $activitytable = new \xmldb_table('adaptivereview');
        $cardtable = new \xmldb_table('adaptivereview_cards');
        $masterytable = new \xmldb_table('adaptivereview_mastery');
        $attempttable = new \xmldb_table('adaptivereview_attempts');

        $field = new \xmldb_field('adaptivereviewid', XMLDB_TYPE_INTEGER, '10', null,
            XMLDB_NOTNULL, null, '0', 'id');
        $dbman->add_field($cardtable, $field);
        $activities = $DB->get_records('adaptivereview', null, '', 'id, setid');
        foreach ($activities as $activity) {
            $cardids = $DB->get_fieldset_select('adaptivereview_set_cards', 'cardid', 'setid = ?',
                [$activity->setid]);
            if ($cardids) {
                [$insql, $params] = $DB->get_in_or_equal($cardids);
                $DB->set_field_select('adaptivereview_cards', 'adaptivereviewid', $activity->id,
                    "id $insql", $params);
            }
        }

        $dbman->drop_key($activitytable,
            new \xmldb_key('setid', XMLDB_KEY_FOREIGN, ['setid'], 'adaptivereview_sets', ['id']));
        $dbman->drop_field($activitytable,
            new \xmldb_field('masterygoal', XMLDB_TYPE_NUMBER, '3,1', null, XMLDB_NOTNULL, null, '4.0'));
        $dbman->drop_field($activitytable,
            new \xmldb_field('setid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'));

        $dbman->drop_key($masterytable,
            new \xmldb_key('cardid', XMLDB_KEY_FOREIGN, ['cardid'], 'adaptivereview_cards', ['id']));
        $dbman->drop_index($masterytable,
            new \xmldb_index('userid_cardid', XMLDB_INDEX_UNIQUE, ['userid', 'cardid']));
        $dbman->rename_field($masterytable,
            new \xmldb_field('cardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'), 'itemid');

        $dbman->drop_key($attempttable,
            new \xmldb_key('cardid', XMLDB_KEY_FOREIGN, ['cardid'], 'adaptivereview_cards', ['id']));
        $dbman->drop_index($attempttable,
            new \xmldb_index('sessionid_cardid_attempt', XMLDB_INDEX_UNIQUE,
                ['sessionid', 'cardid', 'attemptnumber']));
        $dbman->rename_field($attempttable,
            new \xmldb_field('cardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0'), 'itemid');

        $dbman->drop_table(new \xmldb_table('adaptivereview_set_cards'));
        $dbman->drop_table(new \xmldb_table('adaptivereview_sets'));

        $dbman->drop_index($cardtable, new \xmldb_index('archived', XMLDB_INDEX_NOTUNIQUE, ['archived']));
        $dbman->drop_index($cardtable, new \xmldb_index('category', XMLDB_INDEX_NOTUNIQUE, ['category']));
        foreach (['timemodified', 'timecreated', 'modifiedby', 'createdby', 'archived'] as $fieldname) {
            $dbman->drop_field($cardtable, new \xmldb_field($fieldname));
        }
        $dbman->rename_table($cardtable, 'adaptivereview_items');

        $itemtable = new \xmldb_table('adaptivereview_items');
        $dbman->add_key($itemtable,
            new \xmldb_key('adaptivereviewid', XMLDB_KEY_FOREIGN, ['adaptivereviewid'], 'adaptivereview', ['id']));
        $dbman->add_key($masterytable,
            new \xmldb_key('itemid', XMLDB_KEY_FOREIGN, ['itemid'], 'adaptivereview_items', ['id']));
        $dbman->add_index($masterytable,
            new \xmldb_index('userid_itemid', XMLDB_INDEX_UNIQUE, ['userid', 'itemid']));
        $dbman->add_key($attempttable,
            new \xmldb_key('itemid', XMLDB_KEY_FOREIGN, ['itemid'], 'adaptivereview_items', ['id']));
        $legacyattemptfields = [];
        $legacyattemptfields[] = 'sessionid';
        $legacyattemptfields[] = 'itemid';
        $legacyattemptfields[] = 'attemptnumber';
        $legacyattemptindex = new \xmldb_index(
            'sessionid_itemid_attempt',
            XMLDB_INDEX_UNIQUE,
            $legacyattemptfields
        );
        $dbman->add_index($attempttable, $legacyattemptindex);
    }
}
