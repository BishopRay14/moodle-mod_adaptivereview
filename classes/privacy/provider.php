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
 * Privacy provider for Adaptive Review.
 *
 * @package   mod_adaptivereview
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_adaptivereview\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\metadata\provider as metadata_provider;
use core_privacy\local\request\plugin\provider as plugin_provider;
use core_privacy\local\request\core_userlist_provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Implements metadata, export, and deletion for Adaptive Review user data.
 */
class provider implements core_userlist_provider, metadata_provider, plugin_provider {
    /**
     * Describe personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'adaptivereview_cards',
            [
                'createdby' => 'privacy:metadata:adaptivereview_cards:createdby',
                'modifiedby' => 'privacy:metadata:adaptivereview_cards:modifiedby',
            ],
            'privacy:metadata:adaptivereview_cards'
        );
        $collection->add_database_table(
            'adaptivereview_sets',
            [
                'createdby' => 'privacy:metadata:adaptivereview_sets:createdby',
                'modifiedby' => 'privacy:metadata:adaptivereview_sets:modifiedby',
            ],
            'privacy:metadata:adaptivereview_sets'
        );
        $collection->add_database_table(
            'adaptivereview_mastery',
            [
                'userid' => 'privacy:metadata:adaptivereview_mastery:userid',
                'cardid' => 'privacy:metadata:adaptivereview_mastery:cardid',
                'box_number' => 'privacy:metadata:adaptivereview_mastery:box_number',
                'count_correct' => 'privacy:metadata:adaptivereview_mastery:count_correct',
                'count_wrong' => 'privacy:metadata:adaptivereview_mastery:count_wrong',
                'last_reviewed' => 'privacy:metadata:adaptivereview_mastery:last_reviewed',
                'masteryscore' => 'privacy:metadata:adaptivereview_mastery:masteryscore',
                'requiredintervaldays' => 'privacy:metadata:adaptivereview_mastery:requiredintervaldays',
                'nextreviewdate' => 'privacy:metadata:adaptivereview_mastery:nextreviewdate',
                'lastreviewdate' => 'privacy:metadata:adaptivereview_mastery:lastreviewdate',
            ],
            'privacy:metadata:adaptivereview_mastery'
        );
        $collection->add_database_table(
            'adaptivereview_sessions',
            [
                'adaptivereviewid' => 'privacy:metadata:adaptivereview_sessions:adaptivereviewid',
                'userid' => 'privacy:metadata:adaptivereview_sessions:userid',
                'sessionkey' => 'privacy:metadata:adaptivereview_sessions:sessionkey',
                'mode' => 'privacy:metadata:adaptivereview_sessions:mode',
                'timecreated' => 'privacy:metadata:adaptivereview_sessions:timecreated',
                'timecompleted' => 'privacy:metadata:adaptivereview_sessions:timecompleted',
            ],
            'privacy:metadata:adaptivereview_sessions'
        );
        $collection->add_database_table(
            'adaptivereview_attempts',
            [
                'sessionid' => 'privacy:metadata:adaptivereview_attempts:sessionid',
                'cardid' => 'privacy:metadata:adaptivereview_attempts:cardid',
                'attemptnumber' => 'privacy:metadata:adaptivereview_attempts:attemptnumber',
                'response' => 'privacy:metadata:adaptivereview_attempts:response',
                'wasdue' => 'privacy:metadata:adaptivereview_attempts:wasdue',
                'isremediation' => 'privacy:metadata:adaptivereview_attempts:isremediation',
                'masteryeligible' => 'privacy:metadata:adaptivereview_attempts:masteryeligible',
                'masterybefore' => 'privacy:metadata:adaptivereview_attempts:masterybefore',
                'masterygain' => 'privacy:metadata:adaptivereview_attempts:masterygain',
                'masteryafter' => 'privacy:metadata:adaptivereview_attempts:masteryafter',
                'intervalbefore' => 'privacy:metadata:adaptivereview_attempts:intervalbefore',
                'intervalafter' => 'privacy:metadata:adaptivereview_attempts:intervalafter',
                'previousreviewdate' => 'privacy:metadata:adaptivereview_attempts:previousreviewdate',
                'previousduedate' => 'privacy:metadata:adaptivereview_attempts:previousduedate',
                'nextreviewdate' => 'privacy:metadata:adaptivereview_attempts:nextreviewdate',
                'algorithmversion' => 'privacy:metadata:adaptivereview_attempts:algorithmversion',
                'timecreated' => 'privacy:metadata:adaptivereview_attempts:timecreated',
            ],
            'privacy:metadata:adaptivereview_attempts'
        );
        return $collection;
    }

    /**
     * Return contexts containing data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist Contexts containing user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        if (self::global_user_data_exists($userid)) {
            $contextlist->add_system_context();
        }

        $sql = "SELECT c.id
                  FROM {context} c
                  JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = :contextlevel
                  JOIN {modules} m ON m.name = :modname AND m.id = cm.module
                  JOIN {adaptivereview_sessions} s ON s.adaptivereviewid = cm.instance
                 WHERE s.userid = :userid";
        $params = [
            'modname' => 'adaptivereview',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Add users with data in the supplied context.
     *
     * @param userlist $userlist Context-bound user list.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $userlist->add_from_sql('userid', 'SELECT userid FROM {adaptivereview_mastery}', []);
            $userlist->add_from_sql(
                'createdby',
                'SELECT createdby FROM {adaptivereview_cards} WHERE createdby > 0',
                []
            );
            $userlist->add_from_sql(
                'modifiedby',
                'SELECT modifiedby FROM {adaptivereview_cards} WHERE modifiedby > 0',
                []
            );
            $userlist->add_from_sql(
                'createdby',
                'SELECT createdby FROM {adaptivereview_sets} WHERE createdby > 0',
                []
            );
            $userlist->add_from_sql(
                'modifiedby',
                'SELECT modifiedby FROM {adaptivereview_sets} WHERE modifiedby > 0',
                []
            );
            return;
        }
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $params = [
            'modname' => 'adaptivereview',
            'cmid' => $context->instanceid,
        ];

        $sql = "SELECT s.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.name = :modname AND m.id = cm.module
                  JOIN {adaptivereview_sessions} s ON s.adaptivereviewid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export approved user data.
     *
     * @param approved_contextlist $contextlist Approved contexts for the user.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::export_global_user_data($context, $userid);
                continue;
            }
            if ($context->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id('adaptivereview', $context->instanceid);
                if (!$cm) {
                    continue;
                }

                $sessions = $DB->get_records('adaptivereview_sessions', [
                    'adaptivereviewid' => $cm->instance,
                    'userid' => $userid,
                ], 'timecreated, id');
                if ($sessions) {
                    $sessiondata = [];
                    foreach ($sessions as $session) {
                        $sessiondata[] = (object) [
                            'sessionkey' => $session->sessionkey,
                            'mode' => $session->mode,
                            'timecreated' => date('Y-m-d H:i:s', $session->timecreated),
                            'timecompleted' => $session->timecompleted
                                ? date('Y-m-d H:i:s', $session->timecompleted)
                                : null,
                        ];
                    }
                    \core_privacy\local\request\writer::with_context($context)->export_data(
                        [get_string('pluginname', 'mod_adaptivereview'), get_string('reviewsessions', 'mod_adaptivereview')],
                        (object) ['sessions' => $sessiondata]
                    );
                }

                $sql = "SELECT a.*, s.mode, rc.question, rc.answer
                          FROM {adaptivereview_attempts} a
                          JOIN {adaptivereview_sessions} s ON s.id = a.sessionid
                          JOIN {adaptivereview_cards} rc ON rc.id = a.cardid
                         WHERE s.adaptivereviewid = ? AND s.userid = ?
                      ORDER BY a.timecreated, a.id";
                $attemptrecords = $DB->get_records_sql($sql, [$cm->instance, $userid]);

                if (!empty($attemptrecords)) {
                    $history = [];
                    foreach ($attemptrecords as $attempt) {
                        $history[] = (object) [
                            'question' => format_text($attempt->question),
                            'answer' => format_text($attempt->answer),
                            'mode' => $attempt->mode,
                            'attemptnumber' => $attempt->attemptnumber,
                            'response' => $attempt->response,
                            'wasdue' => $attempt->wasdue,
                            'isremediation' => $attempt->isremediation,
                            'masteryeligible' => $attempt->masteryeligible,
                            'masterybefore' => $attempt->masterybefore,
                            'masterygain' => $attempt->masterygain,
                            'masteryafter' => $attempt->masteryafter,
                            'intervalbefore' => $attempt->intervalbefore,
                            'intervalafter' => $attempt->intervalafter,
                            'previousreviewdate' => $attempt->previousreviewdate,
                            'previousduedate' => $attempt->previousduedate,
                            'nextreviewdate' => $attempt->nextreviewdate,
                            'algorithmversion' => $attempt->algorithmversion,
                            'timecreated' => date('Y-m-d H:i:s', $attempt->timecreated),
                        ];
                    }
                    \core_privacy\local\request\writer::with_context($context)->export_data(
                        [get_string('pluginname', 'mod_adaptivereview'), get_string('reviewhistory', 'mod_adaptivereview')],
                        (object) ['attempts' => $history]
                    );
                }
            }
        }
    }

    /**
     * Delete data for every user in a context.
     *
     * @param \context $context Context to erase.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('adaptivereview_mastery');
            $DB->set_field_select('adaptivereview_cards', 'createdby', 0, 'createdby <> 0');
            $DB->set_field_select('adaptivereview_cards', 'modifiedby', 0, 'modifiedby <> 0');
            $DB->set_field_select('adaptivereview_sets', 'createdby', 0, 'createdby <> 0');
            $DB->set_field_select('adaptivereview_sets', 'modifiedby', 0, 'modifiedby <> 0');
            return;
        }
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        if ($cm = get_coursemodule_from_id('adaptivereview', $context->instanceid)) {
            self::delete_activity_history((int) $cm->instance);
        }
    }

    /**
     * Delete data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts for the user.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        if (empty($contextlist->count())) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                self::delete_global_user_data($userid);
                continue;
            }
            if ($context->contextlevel == CONTEXT_MODULE) {
                if ($cm = get_coursemodule_from_id('adaptivereview', $context->instanceid)) {
                    self::delete_activity_history((int) $cm->instance, [$userid]);
                }
            }
        }
    }

    /**
     * Delete data for approved users in a context.
     *
     * @param approved_userlist $userlist Approved users and context.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            foreach ($userids as $userid) {
                self::delete_global_user_data((int) $userid);
            }
            return;
        }
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }
        if ($cm = get_coursemodule_from_id('adaptivereview', $context->instanceid)) {
            self::delete_activity_history((int) $cm->instance, $userids);
        }
    }

    /**
     * Whether a user owns any global repository or mastery data.
     *
     * @param int $userid User ID.
     * @return bool
     */
    private static function global_user_data_exists(int $userid): bool {
        global $DB;

        return $DB->record_exists('adaptivereview_mastery', ['userid' => $userid])
            || $DB->record_exists_select('adaptivereview_cards', 'createdby = ? OR modifiedby = ?', [$userid, $userid])
            || $DB->record_exists_select('adaptivereview_sets', 'createdby = ? OR modifiedby = ?', [$userid, $userid]);
    }

    /**
     * Export lifelong mastery and repository authorship in system context.
     *
     * @param \context $context System context.
     * @param int $userid User ID.
     */
    private static function export_global_user_data(\context $context, int $userid): void {
        global $DB;

        $records = $DB->get_records_sql(
            "SELECT m.*, c.question, c.answer
               FROM {adaptivereview_mastery} m
               JOIN {adaptivereview_cards} c ON c.id = m.cardid
              WHERE m.userid = :userid
           ORDER BY c.id",
            ['userid' => $userid]
        );
        if ($records) {
            $mastery = [];
            foreach ($records as $record) {
                $mastery[] = (object) [
                    'question' => format_text($record->question),
                    'answer' => format_text($record->answer),
                    'masteryscore' => $record->masteryscore,
                    'requiredintervaldays' => $record->requiredintervaldays,
                    'nextreviewdate' => $record->nextreviewdate,
                    'lastreviewdate' => $record->lastreviewdate,
                ];
            }
            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'mod_adaptivereview'), get_string('lifelongmastery', 'mod_adaptivereview')],
                (object) ['cards' => $mastery]
            );
        }

        $cards = $DB->get_records_select(
            'adaptivereview_cards',
            'createdby = ? OR modifiedby = ?',
            [$userid, $userid],
            'id'
        );
        $sets = $DB->get_records_select(
            'adaptivereview_sets',
            'createdby = ? OR modifiedby = ?',
            [$userid, $userid],
            'id'
        );
        if ($cards || $sets) {
            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'mod_adaptivereview'), get_string('repositoryauthorship', 'mod_adaptivereview')],
                (object) [
                    'cards' => array_values($cards),
                    'sets' => array_values($sets),
                ]
            );
        }
    }

    /**
     * Delete global personal data for one user while preserving authored content.
     *
     * @param int $userid User ID.
     */
    private static function delete_global_user_data(int $userid): void {
        global $DB;

        $DB->delete_records('adaptivereview_mastery', ['userid' => $userid]);
        $DB->set_field('adaptivereview_cards', 'createdby', 0, ['createdby' => $userid]);
        $DB->set_field('adaptivereview_cards', 'modifiedby', 0, ['modifiedby' => $userid]);
        $DB->set_field('adaptivereview_sets', 'createdby', 0, ['createdby' => $userid]);
        $DB->set_field('adaptivereview_sets', 'modifiedby', 0, ['modifiedby' => $userid]);
    }

    /**
     * Delete activity-specific sessions and attempts.
     *
     * @param int $activityid Activity instance ID.
     * @param array|null $userids Optional user IDs.
     */
    private static function delete_activity_history(int $activityid, ?array $userids = null): void {
        global $DB;

        $select = 'adaptivereviewid = ?';
        $params = [$activityid];
        if ($userids !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids);
            $select .= " AND userid $insql";
            $params = array_merge($params, $inparams);
        }
        $sessionids = $DB->get_fieldset_select('adaptivereview_sessions', 'id', $select, $params);
        if ($sessionids) {
            $DB->delete_records_list('adaptivereview_attempts', 'sessionid', $sessionids);
            $DB->delete_records_list('adaptivereview_sessions', 'id', $sessionids);
        }
    }
}
