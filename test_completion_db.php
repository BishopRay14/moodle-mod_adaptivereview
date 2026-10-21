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
 * Manual activity-form completion diagnostic for Adaptive Review.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/mod_form.php');

$form = new mod_adaptivereview_mod_form(null, null, 'post', '', null, true, null);
$form->definition();

// Check the form elements that Moodle built dynamically under completion
$mform = $form->getform();
foreach ($mform->_elements as $el) {
    if (strpos($el->getName(), 'completion') !== false) {
         echo $el->getName() . " (" . $el->getType() . ")\n";
    }
}
