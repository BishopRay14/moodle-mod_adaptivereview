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
 * Text import support for Adaptive Review cards.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_adaptivereview;

defined('MOODLE_INTERNAL') || die();

/**
 * Parses the supported plain-text card import format.
 */
class import_handler {
    /**
     * Parses the custom text-block format into an array of cards.
     *
     * @param string $text The raw text input containing card definitions.
     * @return array Array of associative arrays with 'question', 'answer', 'hint' keys.
     */
    public static function parse_text($text) {
        $cards = [];
        $text = str_replace("\r\n", "\n", $text);
        $text = str_replace("\r", "\n", $text);

        $blocks = explode('===CARD===', $text);

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            $lines = explode("\n", $block);
            $currentkey = '';
            $currentval = [];

            $q = '';
            $a = '';
            $h = '';

            foreach ($lines as $line) {
                if (preg_match('/^Q:\s*(.*)/i', $line, $matches)) {
                    if ($currentkey === 'A') {
                        $a = implode("\n", $currentval);
                    }
                    if ($currentkey === 'H') {
                        $h = implode("\n", $currentval);
                    }
                    $currentkey = 'Q';
                    $currentval = [$matches[1]];
                } else if (preg_match('/^A:\s*(.*)/i', $line, $matches)) {
                    if ($currentkey === 'Q') {
                        $q = implode("\n", $currentval);
                    }
                    if ($currentkey === 'H') {
                        $h = implode("\n", $currentval);
                    }
                    $currentkey = 'A';
                    $currentval = [$matches[1]];
                } else if (preg_match('/^H:\s*(.*)/i', $line, $matches)) {
                    if ($currentkey === 'Q') {
                        $q = implode("\n", $currentval);
                    }
                    if ($currentkey === 'A') {
                        $a = implode("\n", $currentval);
                    }
                    $currentkey = 'H';
                    $currentval = [$matches[1]];
                } else {
                    if ($currentkey) {
                        $currentval[] = $line;
                    }
                }
            }

            if ($currentkey === 'Q') {
                $q = implode("\n", $currentval);
            }
            if ($currentkey === 'A') {
                $a = implode("\n", $currentval);
            }
            if ($currentkey === 'H') {
                $h = implode("\n", $currentval);
            }

            $q = trim($q);
            $a = trim($a);
            $h = trim($h);

            if (!empty($q) && !empty($a)) {
                $cards[] = [
                    'question' => $q,
                    'answer' => $a,
                    'hint' => $h,
                ];
            }
        }

        return $cards;
    }
}
