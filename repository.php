<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Global card and set repository management.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use mod_adaptivereview\local\repository_manager;

$cmid = optional_param('cmid', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$setid = optional_param('setid', 0, PARAM_INT);
$cardid = optional_param('cardid', 0, PARAM_INT);
$setsearch = optional_param('setsearch', '', PARAM_TEXT);
$cardsearch = optional_param('cardsearch', '', PARAM_TEXT);

$cm = null;
$activity = null;
if ($cmid > 0) {
    $cm = get_coursemodule_from_id('adaptivereview', $cmid, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    require_login($course, false, $cm);
    $PAGE->set_cm($cm, $course);
    $activity = $DB->get_record('adaptivereview', ['id' => $cm->instance], 'id, name, setid', MUST_EXIST);
    if ($setid === 0) {
        $setid = (int) $activity->setid;
    }
} else {
    require_login();
    $PAGE->set_context(context_system::instance());
}
require_capability('mod/adaptivereview:manageglobalrepository', context_system::instance());

$pageparams = [];
if ($cmid > 0) {
    $pageparams['cmid'] = $cmid;
}
if ($setid > 0) {
    $pageparams['setid'] = $setid;
}
$PAGE->set_url('/mod/adaptivereview/repository.php', $pageparams);
$PAGE->set_title(get_string('repositorytitle', 'mod_adaptivereview'));
$PAGE->set_heading(get_string('repositorytitle', 'mod_adaptivereview'));

$returnurl = static function(int $selectedsetid = 0, array $extra = []) use ($cmid): moodle_url {
    $params = $extra;
    if ($cmid > 0) {
        $params['cmid'] = $cmid;
    }
    if ($selectedsetid > 0) {
        $params['setid'] = $selectedsetid;
    }
    return new moodle_url('/mod/adaptivereview/repository.php', $params);
};

if (data_submitted() && confirm_sesskey()) {
    if ($action === 'createset') {
        $set = repository_manager::create_set(
            required_param('name', PARAM_TEXT),
            optional_param('description', '', PARAM_CLEANHTML),
            required_param('defaultmasterygoal', PARAM_FLOAT),
            (int) $USER->id
        );
        redirect($returnurl((int) $set->id), get_string('setcreated', 'mod_adaptivereview'));
    }
    if ($action === 'updateset' && $setid > 0) {
        repository_manager::update_set(
            $setid,
            required_param('name', PARAM_TEXT),
            optional_param('description', '', PARAM_CLEANHTML),
            required_param('defaultmasterygoal', PARAM_FLOAT),
            (int) $USER->id
        );
        redirect($returnurl($setid), get_string('setupdated', 'mod_adaptivereview'));
    }
    if ($action === 'duplicateset' && $setid > 0) {
        $source = $DB->get_record('adaptivereview_sets', ['id' => $setid], 'id, name', MUST_EXIST);
        $copy = repository_manager::duplicate_set(
            $setid,
            get_string('setcopyname', 'mod_adaptivereview', $source->name),
            (int) $USER->id
        );
        redirect($returnurl((int) $copy->id), get_string('setduplicated', 'mod_adaptivereview'));
    }
    if ($action === 'archiveset' && $setid > 0) {
        repository_manager::archive_set($setid, (int) $USER->id);
        redirect($returnurl(), get_string('setarchived', 'mod_adaptivereview'));
    }
    if ($action === 'restoreset' && $setid > 0) {
        repository_manager::restore_set($setid, (int) $USER->id);
        redirect($returnurl($setid), get_string('setrestored', 'mod_adaptivereview'));
    }
    if ($action === 'createcard' && $setid > 0) {
        repository_manager::create_card(
            $setid,
            required_param('question', PARAM_CLEANHTML),
            required_param('answer', PARAM_CLEANHTML),
            optional_param('hint', '', PARAM_CLEANHTML),
            optional_param('category', '', PARAM_TEXT),
            (int) $USER->id
        );
        redirect($returnurl($setid), get_string('cardadded', 'mod_adaptivereview'));
    }
    if ($action === 'updatecard' && $cardid > 0) {
        repository_manager::update_card(
            $cardid,
            required_param('question', PARAM_CLEANHTML),
            required_param('answer', PARAM_CLEANHTML),
            optional_param('hint', '', PARAM_CLEANHTML),
            optional_param('category', '', PARAM_TEXT),
            (int) $USER->id
        );
        redirect($returnurl($setid), get_string('cardupdated', 'mod_adaptivereview'));
    }
    if ($action === 'addcard' && $setid > 0 && $cardid > 0) {
        repository_manager::add_card_to_set($setid, $cardid);
        redirect($returnurl($setid, ['cardsearch' => $cardsearch]), get_string('cardaddedtoset', 'mod_adaptivereview'));
    }
    if ($action === 'removecard' && $setid > 0 && $cardid > 0) {
        repository_manager::remove_card_from_set($setid, $cardid);
        redirect($returnurl($setid), get_string('cardremovedfromset', 'mod_adaptivereview'));
    }
    if ($action === 'movecard' && $setid > 0 && $cardid > 0) {
        $direction = required_param('direction', PARAM_ALPHA);
        repository_manager::move_card($setid, $cardid, $direction === 'up' ? -1 : 1);
        redirect($returnurl($setid));
    }
    if ($action === 'archivecard' && $cardid > 0) {
        repository_manager::archive_card($cardid, (int) $USER->id);
        redirect($returnurl($setid), get_string('cardarchived', 'mod_adaptivereview'));
    }
    if ($action === 'restorecard' && $cardid > 0) {
        repository_manager::restore_card($cardid, (int) $USER->id);
        redirect($returnurl($setid), get_string('cardrestored', 'mod_adaptivereview'));
    }
}

$setwhere = '1 = 1';
$setparams = [];
if ($setsearch !== '') {
    $setwhere = $DB->sql_like('name', ':setsearch', false);
    $setparams['setsearch'] = '%' . $DB->sql_like_escape($setsearch) . '%';
}
$sets = $DB->get_records_select('adaptivereview_sets', $setwhere, $setparams, 'archived ASC, name ASC');
if ($setid === 0 && $sets) {
    foreach ($sets as $candidate) {
        if (empty($candidate->archived)) {
            $setid = (int) $candidate->id;
            break;
        }
    }
}
$selectedset = $setid > 0 ? $DB->get_record('adaptivereview_sets', ['id' => $setid]) : false;

$setrows = [];
foreach ($sets as $set) {
    $setrows[] = [
        'id' => $set->id,
        'name' => $set->name,
        'selected' => (int) $set->id === $setid,
        'archived' => !empty($set->archived),
        'cardcount' => $DB->count_records('adaptivereview_set_cards', ['setid' => $set->id]),
        'activitycount' => $DB->count_records('adaptivereview', ['setid' => $set->id]),
        'url' => $returnurl((int) $set->id)->out(false),
    ];
}

$resolvedemocontent = static function(string $text): string {
    if (preg_match('/^##(demo_[a-z0-9]+)##$/', $text, $matches)) {
        return get_string($matches[1], 'mod_adaptivereview');
    }
    return $text;
};

$cardrows = [];
$libraryrows = [];
$editcard = false;
if ($selectedset) {
    $cards = $DB->get_records_sql(
        "SELECT c.*, sc.sortorder
           FROM {adaptivereview_set_cards} sc
           JOIN {adaptivereview_cards} c ON c.id = sc.cardid
          WHERE sc.setid = :setid
       ORDER BY sc.sortorder, c.id",
        ['setid' => $setid]
    );
    $cardvalues = array_values($cards);
    $lastindex = count($cardvalues) - 1;
    foreach ($cardvalues as $index => $card) {
        $cardrows[] = [
            'id' => $card->id,
            'question' => format_text($resolvedemocontent($card->question)),
            'answer' => format_text($resolvedemocontent($card->answer)),
            'category' => s($card->category ?? ''),
            'archived' => !empty($card->archived),
            'setcount' => $DB->count_records('adaptivereview_set_cards', ['cardid' => $card->id]),
            'canmoveup' => $index > 0,
            'canmovedown' => $index < $lastindex,
            'editurl' => $returnurl($setid, ['action' => 'editcard', 'cardid' => $card->id])->out(false),
        ];
    }
    if ($action === 'editcard' && $cardid > 0) {
        $editcard = $DB->get_record('adaptivereview_cards', ['id' => $cardid], '*', MUST_EXIST);
        $editcard->setcount = $DB->count_records('adaptivereview_set_cards', ['cardid' => $cardid]);
    }
    if ($cardsearch !== '') {
        $likequestion = $DB->sql_like('c.question', ':question', false);
        $likeanswer = $DB->sql_like('c.answer', ':answer', false);
        $likecategory = $DB->sql_like('c.category', ':category', false);
        $librarycards = $DB->get_records_sql(
            "SELECT c.*
               FROM {adaptivereview_cards} c
              WHERE c.archived = 0
                AND ($likequestion OR $likeanswer OR $likecategory)
                AND NOT EXISTS (
                    SELECT 1 FROM {adaptivereview_set_cards} sc
                     WHERE sc.setid = :setid AND sc.cardid = c.id
                )
           ORDER BY c.timemodified DESC, c.id DESC",
            [
                'question' => '%' . $DB->sql_like_escape($cardsearch) . '%',
                'answer' => '%' . $DB->sql_like_escape($cardsearch) . '%',
                'category' => '%' . $DB->sql_like_escape($cardsearch) . '%',
                'setid' => $setid,
            ],
            0,
            50
        );
        foreach ($librarycards as $card) {
            $libraryrows[] = [
                'id' => $card->id,
                'question' => format_text($resolvedemocontent($card->question)),
                'answer' => format_text($resolvedemocontent($card->answer)),
                'setcount' => $DB->count_records('adaptivereview_set_cards', ['cardid' => $card->id]),
            ];
        }
    }
}

$templatedata = [
    'baseurl' => $returnurl()->out(false),
    'contextcmid' => $cmid > 0 ? $cmid : false,
    'sesskey' => sesskey(),
    'setsearch' => $setsearch,
    'cardsearch' => $cardsearch,
    'sets' => $setrows,
    'hassets' => !empty($setrows),
    'selectedset' => $selectedset ? [
        'id' => $selectedset->id,
        'name' => $selectedset->name,
        'description' => $selectedset->description ?? '',
        'defaultmasterygoal' => format_float($selectedset->defaultmasterygoal, 1),
        'archived' => !empty($selectedset->archived),
        'inuse' => $DB->record_exists('adaptivereview', ['setid' => $selectedset->id]),
    ] : false,
    'cards' => $cardrows,
    'hascards' => !empty($cardrows),
    'librarycards' => $libraryrows,
    'haslibrarycards' => !empty($libraryrows),
    'editcard' => $editcard ? [
        'id' => $editcard->id,
        'question' => $editcard->question,
        'answer' => $editcard->answer,
        'hint' => $editcard->hint ?? '',
        'category' => $editcard->category ?? '',
        'setcount' => $editcard->setcount,
    ] : false,
    'activityreturn' => $cm && $activity ? [
        'url' => (new moodle_url('/mod/adaptivereview/view.php', ['id' => $cm->id]))->out(false),
        'label' => get_string('backtoactivitynamed', 'mod_adaptivereview', $activity->name),
    ] : false,
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('repositorytitle', 'mod_adaptivereview'));
echo $OUTPUT->notification(get_string('repositorysharingnotice', 'mod_adaptivereview'),
    \core\output\notification::NOTIFY_INFO);
echo $OUTPUT->render_from_template('mod_adaptivereview/repository', $templatedata);
echo $OUTPUT->footer();
