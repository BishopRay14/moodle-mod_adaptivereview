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
 * Test data generator for mod_adaptivereview.
 *
 * @package   mod_adaptivereview
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Creates Adaptive Review activity instances for automated tests.
 */
class mod_adaptivereview_generator extends testing_module_generator {

    /**
     * Create an Adaptive Review activity.
     *
     * @param array|stdClass|null $record Activity data.
     * @param array|null $options Course-module options.
     * @return stdClass
     */
    public function create_instance($record = null, ?array $options = null) {
        $record = (object) (array) $record;

        $record->itemorder ??= 0;
        $record->masterygoal ??= 4.0;
        $record->completion_min_items ??= 0;
        $record->completion_min_mastered ??= 0;
        $record->completion_all_mastered ??= 0;

        return parent::create_instance($record, (array) $options);
    }
}
