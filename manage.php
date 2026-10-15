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
 * Manage cards page for mod_adaptivereview.
 *
 * Refactored to use Moodle Output API (render_from_template),
 * Mustache templates, and AMD JavaScript modules.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/import.php');

$id     = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$cardid = optional_param('cardid', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($id, 'adaptivereview');
$adaptivereview = $DB->get_record('adaptivereview', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($adaptivereview->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

/**
 * Automatically cleans up the 5 tutorial demo cards when the teacher adds their first custom card.
 *
 * @param int $setid The repository set ID.
 */
function mod_adaptivereview_auto_delete_demos($setid) {
    global $DB;
    // Only auto-delete if NO custom (non-demo) cards exist yet.
    $custom_count = $DB->count_records_sql(
        "SELECT COUNT(*)
           FROM {adaptivereview_set_cards} sc
           JOIN {adaptivereview_cards} c ON c.id = sc.cardid
          WHERE sc.setid = ? AND (c.category IS NULL OR c.category != 'demo')",
        [$setid]
    );
    if ($custom_count > 0) {
        return; // Custom cards already exist, don't touch anything.
    }
    // Delete all remaining demo cards and their progress.
    $demo_ids = $DB->get_fieldset_sql(
        "SELECT c.id
           FROM {adaptivereview_set_cards} sc
           JOIN {adaptivereview_cards} c ON c.id = sc.cardid
          WHERE sc.setid = ? AND c.category = 'demo'",
        [$setid]
    );
    if (!empty($demo_ids)) {
        [$insql, $params] = $DB->get_in_or_equal($demo_ids);
        $DB->delete_records_select('adaptivereview_set_cards', "setid = ? AND cardid $insql",
            array_merge([$setid], $params));
        foreach ($demo_ids as $democardid) {
            if (!$DB->record_exists('adaptivereview_set_cards', ['cardid' => $democardid])) {
                $DB->set_field('adaptivereview_cards', 'archived', 1, ['id' => $democardid]);
            }
        }
    }
}

// =========================================================
// Action processing (data changes only, no HTML output yet)
// =========================================================

if ($action === 'delete' && $cardid && confirm_sesskey()) {
    $cardparams = ['cardid' => $cardid, 'setid' => $adaptivereview->setid];
    if ($DB->record_exists('adaptivereview_set_cards', $cardparams)) {
        $DB->delete_records('adaptivereview_set_cards', $cardparams);
        if (!$DB->record_exists('adaptivereview_set_cards', ['cardid' => $cardid])) {
            $DB->set_field('adaptivereview_cards', 'archived', 1, ['id' => $cardid]);
        }
    }
    redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]),
        get_string('carddeleted', 'mod_adaptivereview'));
}

if ($action === 'bulkdelete' && data_submitted() && confirm_sesskey()) {
    $cardids      = optional_param_array('cardids', [], PARAM_INT);
    $deleted_count = 0;

    if (!empty($cardids)) {
        list($in, $params) = $DB->get_in_or_equal($cardids);
        $params[]    = $adaptivereview->setid;
        $valid_cards = $DB->get_fieldset_sql(
            "SELECT cardid FROM {adaptivereview_set_cards} WHERE cardid $in AND setid = ?", $params);

        if (!empty($valid_cards)) {
            $deleted_count = count($valid_cards);
            [$in_valid, $params_valid] = $DB->get_in_or_equal($valid_cards);
            $DB->delete_records_select('adaptivereview_set_cards', "setid = ? AND cardid $in_valid",
                array_merge([$adaptivereview->setid], $params_valid));
            foreach ($valid_cards as $validcardid) {
                if (!$DB->record_exists('adaptivereview_set_cards', ['cardid' => $validcardid])) {
                    $DB->set_field('adaptivereview_cards', 'archived', 1, ['id' => $validcardid]);
                }
            }
        }
    }

    if ($deleted_count > 0) {
        redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]),
            get_string('cardsdeleted', 'mod_adaptivereview', $deleted_count));
    } else {
        redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]));
    }
}

if ($action === 'export' && confirm_sesskey()) {
    $cards = $DB->get_records_sql(
        "SELECT c.*
           FROM {adaptivereview_set_cards} sc
           JOIN {adaptivereview_cards} c ON c.id = sc.cardid
          WHERE sc.setid = ? AND c.archived = 0
       ORDER BY sc.sortorder, c.id",
        [$adaptivereview->setid]
    );
    $export_content = "";
    foreach ($cards as $c) {
        $export_content .= "===CARD===\n";
        $export_content .= "Q: " . $c->question . "\n";
        $export_content .= "A: " . $c->answer . "\n";
        if (!empty($c->hint)) {
            $export_content .= "H: " . $c->hint . "\n";
        }
        $export_content .= "\n";
    }
    $filename = clean_filename($adaptivereview->name) . '_export.txt';
    send_file($export_content, $filename, 0, 0, true, true, 'text/plain');
    die();
}

if ($action === 'add' && data_submitted() && confirm_sesskey()) {
    $current_count = $DB->count_records('adaptivereview_set_cards', ['setid' => $adaptivereview->setid]);
    if ($current_count >= 200) {
        redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]),
            get_string('error_limit_reached', 'mod_adaptivereview'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $q = required_param('question', PARAM_CLEANHTML);
    $a = required_param('answer',   PARAM_CLEANHTML);
    $h = optional_param('hint', '', PARAM_CLEANHTML);

    if (!empty($q) && !empty($a)) {
        mod_adaptivereview_auto_delete_demos($adaptivereview->setid);
        $newcard = new stdClass();
        $newcard->question  = $q;
        $newcard->answer    = $a;
        $newcard->hint      = $h;
        $newcard->archived = 0;
        $newcard->createdby = $USER->id;
        $newcard->modifiedby = $USER->id;
        $newcard->timecreated = time();
        $newcard->timemodified = $newcard->timecreated;
        $newcard->id = $DB->insert_record('adaptivereview_cards', $newcard);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $adaptivereview->setid,
            'cardid' => $newcard->id,
            'sortorder' => $current_count,
            'timecreated' => time(),
        ]);
        redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]),
            get_string('cardadded', 'mod_adaptivereview'));
    }
}

if ($action === 'update' && data_submitted() && confirm_sesskey() && $cardid) {
    // Security check: ensure the card belongs to this adaptivereview instance.
    if (!$DB->record_exists('adaptivereview_set_cards',
            ['cardid' => $cardid, 'setid' => $adaptivereview->setid])) {
        throw new \moodle_exception('invalidrecord');
    }

    $q = required_param('question', PARAM_CLEANHTML);
    $a = required_param('answer',   PARAM_CLEANHTML);
    $h = optional_param('hint', '', PARAM_CLEANHTML);

    if (!empty($q) && !empty($a)) {
        $updatecard           = new stdClass();
        $updatecard->id       = $cardid;
        $updatecard->question = $q;
        $updatecard->answer   = $a;
        $updatecard->hint     = $h;
        $updatecard->modifiedby = $USER->id;
        $updatecard->timemodified = time();
        $DB->update_record('adaptivereview_cards', $updatecard);
        redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]),
            get_string('cardupdated', 'mod_adaptivereview'));
    }
}

if ($action === 'import' && data_submitted() && confirm_sesskey()) {
    $importtext   = required_param('importdata', PARAM_RAW);
    $parsed_cards = \mod_adaptivereview\import_handler::parse_text($importtext);

    $current_count = $DB->count_records('adaptivereview_set_cards', ['setid' => $adaptivereview->setid]);
    $new_total     = $current_count + count($parsed_cards);

    if ($new_total > 200) {
        redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]),
            get_string('error_limit_exceeded_import', 'mod_adaptivereview',
                max(0, 200 - $current_count)),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    if (!empty($parsed_cards)) {
        mod_adaptivereview_auto_delete_demos($adaptivereview->setid);
    }

    $count = 0;
    foreach ($parsed_cards as $card) {
        $newcard = new stdClass();
        $newcard->question  = $card['question'];
        $newcard->answer    = $card['answer'];
        $newcard->hint      = $card['hint'];
        $newcard->archived = 0;
        $newcard->createdby = $USER->id;
        $newcard->modifiedby = $USER->id;
        $newcard->timecreated = time();
        $newcard->timemodified = $newcard->timecreated;
        $newcard->id = $DB->insert_record('adaptivereview_cards', $newcard);
        $DB->insert_record('adaptivereview_set_cards', (object) [
            'setid' => $adaptivereview->setid,
            'cardid' => $newcard->id,
            'sortorder' => $current_count + $count,
            'timecreated' => time(),
        ]);
        $count++;
    }
    redirect(new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]),
        get_string('cardsimported', 'mod_adaptivereview', $count));
}

// =========================================================
// Build template data
// =========================================================

// Card being edited (if applicable).
$editcard = null;
if ($action === 'edit' && $cardid) {
    if ($DB->record_exists('adaptivereview_set_cards',
            ['cardid' => $cardid, 'setid' => $adaptivereview->setid])) {
        $editcard = $DB->get_record('adaptivereview_cards', ['id' => $cardid, 'archived' => 0]);
    }
}

// AI prompt templates (passed to AMD module for client-side switching).
$prompts = [
    'standard' => get_string('prompt_template_standard', 'mod_adaptivereview'),
    'tf'       => get_string('prompt_template_tf',       'mod_adaptivereview'),
    'vocab'    => get_string('prompt_template_vocab',    'mod_adaptivereview'),
    'cloze'    => get_string('prompt_template_cloze',    'mod_adaptivereview'),
    'jeopardy' => get_string('prompt_template_jeopardy', 'mod_adaptivereview'),
    'transfer' => get_string('prompt_template_transfer', 'mod_adaptivereview'),
];

// Pagination.
$page       = optional_param('page', 0, PARAM_INT);
$perpage    = 50;
$totalcards = $DB->count_records('adaptivereview_set_cards', ['setid' => $adaptivereview->setid]);
$cards = $DB->get_records_sql(
    "SELECT c.*
       FROM {adaptivereview_set_cards} sc
       JOIN {adaptivereview_cards} c ON c.id = sc.cardid
      WHERE sc.setid = ? AND c.archived = 0
   ORDER BY sc.sortorder, c.id",
    [$adaptivereview->setid],
    $page * $perpage,
    $perpage
);

// Build the cards array for the Mustache template.
$cardrows = [];
$rownum   = ($page * $perpage) + 1;
foreach ($cards as $c) {
    $editurl = (new moodle_url('/mod/adaptivereview/manage.php',
        ['id' => $cm->id, 'action' => 'edit', 'cardid' => $c->id]))->out(false);
    $delurl  = (new moodle_url('/mod/adaptivereview/manage.php',
        ['id' => $cm->id, 'action' => 'delete', 'cardid' => $c->id,
         'sesskey' => sesskey()]))->out(false);

    $cardrows[] = [
        'id'            => $c->id,
        'rownum'        => $rownum++,
        'question'      => format_text($c->question),
        'answer'        => format_text($c->answer),
        'hint'          => format_text($c->hint),
        'editurl'       => $editurl,
        'delurl'        => $delurl,
        'editiconhtml'  => $OUTPUT->pix_icon('t/edit',   get_string('edit')),
        'deleteiconhtml'=> $OUTPUT->pix_icon('t/delete', get_string('delete')),
    ];
}

// Pre-render paging bars (injected via triple-mustache {{{ }}}).
$manageurl      = new moodle_url('/mod/adaptivereview/manage.php', ['id' => $cm->id]);
$pagingbartop   = '';
$pagingbarbottom = '';
if (!empty($cards)) {
    $pagingbartop    = $OUTPUT->paging_bar($totalcards, $page, $perpage, $manageurl);
    $pagingbarbottom = $pagingbartop;
}

$templatedata = [
    // Navigation.
    'backurl'      => (new moodle_url('/mod/adaptivereview/view.php', ['id' => $cm->id]))->out(false),
    'managebaseurl'=> (new moodle_url('/mod/adaptivereview/manage.php'))->out(false),
    'cmid'         => $cm->id,
    'sesskey'      => sesskey(),

    // Localised strings.
    'strdidacticnotice'  => get_string('didactic_limit_notice', 'mod_adaptivereview'),
    'strbacktoactivity'  => get_string('backtoactivity',        'mod_adaptivereview'),
    'straddsinglecard'   => get_string('addsinglecard',         'mod_adaptivereview'),
    'streditsinglecard'  => get_string('editsinglecard',        'mod_adaptivereview'),
    'strquestion'        => get_string('question',              'mod_adaptivereview'),
    'stranswer'          => get_string('answer',                'mod_adaptivereview'),
    'strhint'            => get_string('hint',                  'mod_adaptivereview'),
    'stroptional'        => get_string('optional',              'moodle'),
    'straddcard'         => get_string('addcard',               'mod_adaptivereview'),
    'strupdatecard'      => get_string('updatecard',            'mod_adaptivereview'),
    'strcancel'          => get_string('cancel',                'mod_adaptivereview'),
    'strbulkimport'      => get_string('bulkimport',            'mod_adaptivereview'),
    'strbulkimportdesc'  => get_string('bulkimportdesc',        'mod_adaptivereview'),
    'strprompttypesel'   => get_string('prompt_type_selection', 'mod_adaptivereview'),
    'strpromptinstruct'  => get_string('prompt_instruction',    'mod_adaptivereview'),
    'strimportcards'     => get_string('importcards',           'mod_adaptivereview'),
    'strimportph'        => get_string('import_placeholder',    'mod_adaptivereview'),
    'strexistingcards'   => get_string('existingcards',         'mod_adaptivereview'),
    'strnocards'         => get_string('nocards',               'mod_adaptivereview'),
    'strquestioncol'     => get_string('question',              'mod_adaptivereview'),
    'stranswercol'       => get_string('answer',                'mod_adaptivereview'),
    'strhintcol'         => get_string('hint',                  'mod_adaptivereview'),
    'stractionscol'      => get_string('actions',               'moodle'),
    'strdeleteselected'  => get_string('deleteselected',        'mod_adaptivereview'),
    'strexportcards'     => get_string('exportcards',           'mod_adaptivereview'),
    'strselectall'       => get_string('selectall',             'moodle'),

    // Card form fields.
    'isedit'      => (bool)$editcard,
    'editcardid'  => $editcard ? $editcard->id : 0,
    'qval'        => $editcard ? $editcard->question : '',
    'aval'        => $editcard ? $editcard->answer   : '',
    'hval'        => $editcard ? $editcard->hint     : '',

    // Prompt type selector options.
    'prompttypes' => [
        ['key' => 'standard', 'label' => get_string('prompt_type_standard', 'mod_adaptivereview'), 'selected' => true],
        ['key' => 'tf',       'label' => get_string('prompt_type_tf',       'mod_adaptivereview')],
        ['key' => 'vocab',    'label' => get_string('prompt_type_vocab',    'mod_adaptivereview')],
        ['key' => 'cloze',    'label' => get_string('prompt_type_cloze',    'mod_adaptivereview')],
        ['key' => 'jeopardy', 'label' => get_string('prompt_type_jeopardy', 'mod_adaptivereview')],
        ['key' => 'transfer', 'label' => get_string('prompt_type_transfer', 'mod_adaptivereview')],
    ],
    'initialprompt'  => get_string('prompt_template_standard', 'mod_adaptivereview'),

    // Cards table.
    'hascards'       => !empty($cards),
    'cards'          => $cardrows,
    'pagingbartop'   => $pagingbartop,
    'pagingbarbottom'=> $pagingbarbottom,
    'exporturl'      => (new moodle_url('/mod/adaptivereview/manage.php',
        ['id' => $cm->id, 'action' => 'export', 'sesskey' => sesskey()]))->out(false),
];

// Pass prompt templates and confirmation strings to the AMD module via the template
// (avoids Moodle's 1024-character limit on js_call_amd arguments).
$amdparams = [
    'prompts'            => $prompts,
    'confirmDelete'      => get_string('confirmdeletecard',  'mod_adaptivereview'),
    'confirmBulkDelete'  => get_string('confirmbulkdelete',  'mod_adaptivereview'),
];
$templatedata['jsconfig'] = json_encode($amdparams);

// =========================================================
// Output
// =========================================================

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecards', 'mod_adaptivereview'));
echo $OUTPUT->notification(get_string('didactic_limit_notice', 'mod_adaptivereview'),
    \core\output\notification::NOTIFY_INFO);
echo $OUTPUT->render_from_template('mod_adaptivereview/manage', $templatedata);
echo $OUTPUT->footer();
