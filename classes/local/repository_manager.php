<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace mod_adaptivereview\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Authoritative operations for shared repository cards and sets.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class repository_manager {

    /**
     * Create an empty reusable set.
     *
     * @param string $name Set name.
     * @param string $description Set description.
     * @param float $defaultgoal Suggested activity goal.
     * @param int $userid Author ID.
     * @param int|null $now Testable timestamp.
     * @return stdClass
     */
    public static function create_set(
        string $name,
        string $description,
        float $defaultgoal,
        int $userid,
        ?int $now = null
    ): \stdClass {
        global $DB;

        self::validate_goal($defaultgoal);
        $now = $now ?? time();
        $set = (object) [
            'name' => trim($name),
            'description' => $description,
            'descriptionformat' => FORMAT_HTML,
            'defaultmasterygoal' => $defaultgoal,
            'archived' => 0,
            'createdby' => $userid,
            'modifiedby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        if ($set->name === '') {
            throw new \invalid_parameter_exception('Set name is required.');
        }
        $set->id = $DB->insert_record('adaptivereview_sets', $set);

        return $set;
    }

    /**
     * Update reusable set metadata.
     *
     * @param int $setid Set ID.
     * @param string $name Set name.
     * @param string $description Set description.
     * @param float $defaultgoal Suggested activity goal.
     * @param int $userid Modifier ID.
     * @param int|null $now Testable timestamp.
     */
    public static function update_set(
        int $setid,
        string $name,
        string $description,
        float $defaultgoal,
        int $userid,
        ?int $now = null
    ): void {
        global $DB;

        self::validate_goal($defaultgoal);
        $name = trim($name);
        if ($name === '') {
            throw new \invalid_parameter_exception('Set name is required.');
        }
        $DB->get_record('adaptivereview_sets', ['id' => $setid, 'archived' => 0], 'id', MUST_EXIST);
        $DB->update_record('adaptivereview_sets', (object) [
            'id' => $setid,
            'name' => $name,
            'description' => $description,
            'defaultmasterygoal' => $defaultgoal,
            'modifiedby' => $userid,
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Duplicate a set and its cards to create an independent variation.
     *
     * @param int $setid Source set ID.
     * @param string $name New set name.
     * @param int $userid Author ID.
     * @param int|null $now Testable timestamp.
     * @return stdClass
     */
    public static function duplicate_set(int $setid, string $name, int $userid, ?int $now = null): \stdClass {
        global $DB;

        $source = $DB->get_record('adaptivereview_sets', ['id' => $setid, 'archived' => 0], '*', MUST_EXIST);
        $now = $now ?? time();
        $transaction = $DB->start_delegated_transaction();
        $copy = self::create_set(
            $name,
            (string) $source->description,
            (float) $source->defaultmasterygoal,
            $userid,
            $now
        );
        $cards = $DB->get_records_sql(
            "SELECT c.*, sc.sortorder
               FROM {adaptivereview_set_cards} sc
               JOIN {adaptivereview_cards} c ON c.id = sc.cardid
              WHERE sc.setid = :setid AND c.archived = 0
           ORDER BY sc.sortorder, c.id",
            ['setid' => $setid]
        );
        foreach ($cards as $card) {
            self::create_card(
                (int) $copy->id,
                (string) $card->question,
                (string) $card->answer,
                (string) ($card->hint ?? ''),
                (string) ($card->category ?? ''),
                $userid,
                $now
            );
        }
        $transaction->allow_commit();

        return $copy;
    }

    /**
     * Archive an unused set.
     *
     * @param int $setid Set ID.
     * @param int $userid Modifier ID.
     * @param int|null $now Testable timestamp.
     */
    public static function archive_set(int $setid, int $userid, ?int $now = null): void {
        global $DB;

        if ($DB->record_exists('adaptivereview', ['setid' => $setid])) {
            throw new \moodle_exception('cannotarchivesetinuse', 'mod_adaptivereview');
        }
        $DB->update_record('adaptivereview_sets', (object) [
            'id' => $setid,
            'archived' => 1,
            'modifiedby' => $userid,
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Restore an archived set.
     *
     * @param int $setid Set ID.
     * @param int $userid Modifier ID.
     * @param int|null $now Testable timestamp.
     */
    public static function restore_set(int $setid, int $userid, ?int $now = null): void {
        global $DB;

        $DB->update_record('adaptivereview_sets', (object) [
            'id' => $setid,
            'archived' => 0,
            'modifiedby' => $userid,
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Create a global card and add it to a set.
     *
     * @param int $setid Set ID.
     * @param string $question Question text.
     * @param string $answer Answer text.
     * @param string $hint Hint text.
     * @param string $category Optional category.
     * @param int $userid Author ID.
     * @param int|null $now Testable timestamp.
     * @return stdClass
     */
    public static function create_card(
        int $setid,
        string $question,
        string $answer,
        string $hint,
        string $category,
        int $userid,
        ?int $now = null
    ): \stdClass {
        global $DB;

        self::require_active_set($setid);
        $question = trim($question);
        $answer = trim($answer);
        if ($question === '' || $answer === '') {
            throw new \invalid_parameter_exception('Question and answer are required.');
        }
        $now = $now ?? time();
        $card = (object) [
            'question' => $question,
            'answer' => $answer,
            'hint' => $hint,
            'category' => $category === '' ? null : $category,
            'archived' => 0,
            'createdby' => $userid,
            'modifiedby' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $transaction = $DB->start_delegated_transaction();
        $card->id = $DB->insert_record('adaptivereview_cards', $card);
        self::add_card_to_set($setid, (int) $card->id, $now);
        $transaction->allow_commit();

        return $card;
    }

    /**
     * Update a shared global card.
     *
     * @param int $cardid Card ID.
     * @param string $question Question text.
     * @param string $answer Answer text.
     * @param string $hint Hint text.
     * @param string $category Optional category.
     * @param int $userid Modifier ID.
     * @param int|null $now Testable timestamp.
     */
    public static function update_card(
        int $cardid,
        string $question,
        string $answer,
        string $hint,
        string $category,
        int $userid,
        ?int $now = null
    ): void {
        global $DB;

        $question = trim($question);
        $answer = trim($answer);
        if ($question === '' || $answer === '') {
            throw new \invalid_parameter_exception('Question and answer are required.');
        }
        $DB->get_record('adaptivereview_cards', ['id' => $cardid, 'archived' => 0], 'id', MUST_EXIST);
        $DB->update_record('adaptivereview_cards', (object) [
            'id' => $cardid,
            'question' => $question,
            'answer' => $answer,
            'hint' => $hint,
            'category' => $category === '' ? null : $category,
            'modifiedby' => $userid,
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Add an existing global card to a set.
     *
     * @param int $setid Set ID.
     * @param int $cardid Card ID.
     * @param int|null $now Testable timestamp.
     */
    public static function add_card_to_set(int $setid, int $cardid, ?int $now = null): void {
        global $DB;

        self::require_active_set($setid);
        $DB->get_record('adaptivereview_cards', ['id' => $cardid, 'archived' => 0], 'id', MUST_EXIST);
        if ($DB->record_exists('adaptivereview_set_cards', ['setid' => $setid, 'cardid' => $cardid])) {
            return;
        }
        $sortorder = (int) $DB->get_field_sql(
            'SELECT COALESCE(MAX(sortorder), -1) + 1 FROM {adaptivereview_set_cards} WHERE setid = ?',
            [$setid]
        );
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $setid,
            'cardid' => $cardid,
            'sortorder' => $sortorder,
            'timecreated' => $now ?? time(),
        ]);
    }

    /**
     * Remove a card from one set without deleting or archiving the card.
     *
     * @param int $setid Set ID.
     * @param int $cardid Card ID.
     */
    public static function remove_card_from_set(int $setid, int $cardid): void {
        global $DB;

        $DB->delete_records('adaptivereview_set_cards', ['setid' => $setid, 'cardid' => $cardid]);
        self::normalise_order($setid);
    }

    /**
     * Move a card one position within a set.
     *
     * @param int $setid Set ID.
     * @param int $cardid Card ID.
     * @param int $direction Negative for up, positive for down.
     */
    public static function move_card(int $setid, int $cardid, int $direction): void {
        global $DB;

        $memberships = array_values($DB->get_records('adaptivereview_set_cards', ['setid' => $setid],
            'sortorder ASC, id ASC'));
        $current = null;
        foreach ($memberships as $index => $membership) {
            if ((int) $membership->cardid === $cardid) {
                $current = $index;
                break;
            }
        }
        if ($current === null) {
            throw new \moodle_exception('invalidrecord');
        }
        $target = $current + ($direction < 0 ? -1 : 1);
        if (!isset($memberships[$target])) {
            return;
        }
        $transaction = $DB->start_delegated_transaction();
        $first = $memberships[$current];
        $second = $memberships[$target];
        $firstorder = $first->sortorder;
        $first->sortorder = $second->sortorder;
        $second->sortorder = $firstorder;
        $DB->update_record('adaptivereview_set_cards', $first);
        $DB->update_record('adaptivereview_set_cards', $second);
        $transaction->allow_commit();
    }

    /**
     * Archive a global card without deleting history or set membership.
     *
     * @param int $cardid Card ID.
     * @param int $userid Modifier ID.
     * @param int|null $now Testable timestamp.
     */
    public static function archive_card(int $cardid, int $userid, ?int $now = null): void {
        global $DB;

        $DB->update_record('adaptivereview_cards', (object) [
            'id' => $cardid,
            'archived' => 1,
            'modifiedby' => $userid,
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Restore an archived global card.
     *
     * @param int $cardid Card ID.
     * @param int $userid Modifier ID.
     * @param int|null $now Testable timestamp.
     */
    public static function restore_card(int $cardid, int $userid, ?int $now = null): void {
        global $DB;

        $DB->update_record('adaptivereview_cards', (object) [
            'id' => $cardid,
            'archived' => 0,
            'modifiedby' => $userid,
            'timemodified' => $now ?? time(),
        ]);
    }

    /**
     * Validate the set goal range.
     *
     * @param float $goal Goal value.
     */
    private static function validate_goal(float $goal): void {
        if ($goal < 0.1 || $goal > review_manager::MAX_MASTERY) {
            throw new \invalid_parameter_exception('Mastery goal must be between 0.1 and 5.9.');
        }
    }

    /**
     * Require an active set.
     *
     * @param int $setid Set ID.
     */
    private static function require_active_set(int $setid): void {
        global $DB;
        $DB->get_record('adaptivereview_sets', ['id' => $setid, 'archived' => 0], 'id', MUST_EXIST);
    }

    /**
     * Re-number set membership after a removal.
     *
     * @param int $setid Set ID.
     */
    private static function normalise_order(int $setid): void {
        global $DB;

        $memberships = $DB->get_records('adaptivereview_set_cards', ['setid' => $setid], 'sortorder ASC, id ASC');
        $sortorder = 0;
        foreach ($memberships as $membership) {
            if ((int) $membership->sortorder !== $sortorder) {
                $membership->sortorder = $sortorder;
                $DB->update_record('adaptivereview_set_cards', $membership);
            }
            $sortorder++;
        }
    }
}
