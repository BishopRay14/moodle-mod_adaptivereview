<?php
// This file is part of Moodle - https://moodle.org/
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

/**
 * Manual completion-logic diagnostic for Adaptive Review.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

// Mocking Moodle DB counts for the "Min Mastered" condition specifically to pen-test the SQL logic

// Scenario 1: User has to master 2 cards.
$minmasteredsetting = 2;

// User's current DB state (Mastery table simulation)
$masterytable = [
    ['id' => 1, 'cardid' => 101, 'userid' => 2, 'box_number' => 1],
    ['id' => 2, 'cardid' => 102, 'userid' => 2, 'box_number' => 5], // 1 Mastered
    ['id' => 3, 'cardid' => 103, 'userid' => 2, 'box_number' => 4],
];

// Replicate lib.php logic
$totalmastered = 0;
foreach ($masterytable as $m) {
    if ($m['box_number'] == 5) {
        $totalmastered++;
    }
}

echo "Pen Test 1 - Target: 2 Mastered\n";
echo "Current Mastered: $totalmastered\n";
if ($totalmastered < $minmasteredsetting) {
    echo "Result: NOT COMPLETED (Correct)\n";
} else {
    echo "Result: COMPLETED (Incorrect!)\n";
}

// Scenario 2: What happens if a user masters the SAME item twice?
// Moodle DB setup: a user only has ONE mastery row per item.
// So a count of Box 5 records *must* equal distinct mastered cards.
$sqlmasteredlogictest = "SELECT COUNT(*) FROM {adaptivereview_mastery} WHERE userid = :userid AND box_number = 5";
echo "Pen Test 2 - SQL Logic: $sqlmasteredlogictest\n";
echo "SQL test relies on DB constraint: UNIQUE(userid, cardid). Does install.xml enforce this?\n";
