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
 * @contact   leitbox.moodle@gmail.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * VERSIONING RULES (For Developers / AI Tooling):
 * This plugin uses Semantic Versioning (MAJOR.MINOR.PATCH):
 * 
 * - MAJOR (e.g., 1.0.0 -> 2.0.0): 
 *   Use for deep architectural changes, replacing frameworks, or formally 
 *   dropping compatibility with older Moodle versions.
 *
 * - MINOR (e.g., 1.4.0 -> 1.5.0): 
 *   Use for new features, new settings in the Moodle form (e.g., completion conditions), 
 *   or structural database updates (XMLDB).
 *
 * - PATCH (e.g., 1.4.0 -> 1.4.1): 
 *   Use strictly for bugfixes, language string corrections, minor CSS tweaks, 
 *   or anything that carries zero architectural risk.
 *
 * EVERY release must be officially block-documented in CHANGELOG.md!
 */

$plugin->version   = 2026101500; // The current module version (Date: YYYYMMDDXX).
$plugin->requires  = 2025041400; // Requires Moodle 5.0.
$plugin->component = 'mod_adaptivereview'; // Full name of the plugin (used for diagnostics).
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.4.0-prototype';
