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
defined('MOODLE_INTERNAL') || die();

function xmldb_adaptivereview_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026010103) {
        $table = new xmldb_table('adaptivereview');
        $field = new xmldb_field('completion_min_mastered', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'completion_min_cards');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026010103, 'adaptivereview');
    }

    if ($oldversion < 2026022705) {
        $table = new xmldb_table('adaptivereview');
        $field = new xmldb_field('cardorder', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'introformat');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026022705, 'adaptivereview');
    }

    if ($oldversion < 2026030100) {
        $table = new xmldb_table('adaptivereview');
        $field = new xmldb_field('completion_all_mastered', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'completion_min_mastered');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026030100, 'adaptivereview');
    }

    if ($oldversion < 2026061805) {
        $table = new xmldb_table('adaptivereview_progress');

        $field = new xmldb_field('masteryscore', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0', 'last_reviewed');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('requiredintervaldays', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'masteryscore');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('nextreviewdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'requiredintervaldays');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('lastreviewdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'nextreviewdate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026061805, 'adaptivereview');
    }

    if ($oldversion < 2026070701) {
        $table = new xmldb_table('adaptivereview_cards');

        if ($dbman->table_exists($table)){
            $dbman->rename_table($table, 'adaptivereview_items');
        }

        $table = new xmldb_table('adaptivereview_progress');

        if ($dbman->table_exists($table)){
            $dbman->rename_table($table, 'adaptivereview_mastery');
        }

        $table = new xmldb_table('adaptivereview');

        $field = new xmldb_field('cardorder', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0', 'introformat');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'itemorder');
        }

        $field = new xmldb_field('completion_min_cards', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'cardorder');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'completion_min_items');
        }

        $table = new xmldb_table('adaptivereview_mastery');

        $field = new xmldb_field('cardid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'itemid');
        }

        // Moodle identifies keys and indexes by their fields, not their XMLDB names.
        // Renaming the table and field above preserves both database indexes, so no
        // experimental and database-dependent key/index rename is needed here.

        upgrade_mod_savepoint(true, 2026070701, 'adaptivereview');
    }

    if ($oldversion < 2026081800) {
        $table = new xmldb_table('adaptivereview_sessions');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('adaptivereviewid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sessionkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
        $table->add_field('mode', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('adaptivereviewid', XMLDB_KEY_FOREIGN, ['adaptivereviewid'], 'adaptivereview', ['id']);

        $table->add_index('sessionkey', XMLDB_INDEX_UNIQUE, ['sessionkey']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('adaptivereviewid_userid', XMLDB_INDEX_NOTUNIQUE, ['adaptivereviewid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('adaptivereview_attempts');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attemptnumber', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('response', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL);
        $table->add_field('wasdue', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('isremediation', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('masteryeligible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('masterybefore', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('masterygain', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('masteryafter', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('intervalbefore', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('intervalafter', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('previousreviewdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('previousduedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextreviewdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('algorithmversion', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, ['sessionid'], 'adaptivereview_sessions', ['id']);
        $table->add_key('itemid', XMLDB_KEY_FOREIGN, ['itemid'], 'adaptivereview_items', ['id']);

        $table->add_index('sessionid_itemid_attempt', XMLDB_INDEX_UNIQUE, ['sessionid', 'itemid', 'attemptnumber']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Prototype scores were produced by rules that conflict with the approved
        // mastery model. Start the evidence-backed engine from a clean summary.
        $DB->delete_records('adaptivereview_mastery');

        upgrade_mod_savepoint(true, 2026081800, 'adaptivereview');
    }

    return true;
}
