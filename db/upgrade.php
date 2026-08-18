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

    return true;
}
