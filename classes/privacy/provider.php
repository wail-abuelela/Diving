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
 * Privacy Subsystem implementation for enrol_paybank.
 *
 * @package    enrol_paybank
 * @category   privacy
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_paybank\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for enrol_paybank.
 *
 * @copyright  2018 Shamim Rezaie <shamim@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_external_location_link(
            'paybank.com',
            [
                'os0'        => 'privacy:metadata:enrol_paybank:paybank_com:os0',
                'custom'     => 'privacy:metadata:enrol_paybank:paybank_com:custom',
                'first_name' => 'privacy:metadata:enrol_paybank:paybank_com:first_name',
                'last_name'  => 'privacy:metadata:enrol_paybank:paybank_com:last_name',
                'address'    => 'privacy:metadata:enrol_paybank:paybank_com:address',
                'city'       => 'privacy:metadata:enrol_paybank:paybank_com:city',
                'email'      => 'privacy:metadata:enrol_paybank:paybank_com:email',
                'country'    => 'privacy:metadata:enrol_paybank:paybank_com:country',
            ],
            'privacy:metadata:enrol_paybank:paybank_com'
        );

        // The enrol_paybank has a DB table that contains user data.
        $collection->add_database_table(
                'enrol_paybank',
                [
                    'business'            => 'privacy:metadata:enrol_paybank:enrol_paybank:business',
                    'receiver_email'      => 'privacy:metadata:enrol_paybank:enrol_paybank:receiver_email',
                    'receiver_id'         => 'privacy:metadata:enrol_paybank:enrol_paybank:receiver_id',
                    'item_name'           => 'privacy:metadata:enrol_paybank:enrol_paybank:item_name',
                    'courseid'            => 'privacy:metadata:enrol_paybank:enrol_paybank:courseid',
                    'userid'              => 'privacy:metadata:enrol_paybank:enrol_paybank:userid',
                    'instanceid'          => 'privacy:metadata:enrol_paybank:enrol_paybank:instanceid',
                    'memo'                => 'privacy:metadata:enrol_paybank:enrol_paybank:memo',
                    'tax'                 => 'privacy:metadata:enrol_paybank:enrol_paybank:tax',
                    'option_selection1_x' => 'privacy:metadata:enrol_paybank:enrol_paybank:option_selection1_x',
                    'payment_status'      => 'privacy:metadata:enrol_paybank:enrol_paybank:payment_status',
                    'pending_reason'      => 'privacy:metadata:enrol_paybank:enrol_paybank:pending_reason',
                    'reason_code'         => 'privacy:metadata:enrol_paybank:enrol_paybank:reason_code',
                    'txn_id'              => 'privacy:metadata:enrol_paybank:enrol_paybank:txn_id',
                    'parent_txn_id'       => 'privacy:metadata:enrol_paybank:enrol_paybank:parent_txn_id',
                    'payment_type'        => 'privacy:metadata:enrol_paybank:enrol_paybank:payment_type',
                    'timeupdated'         => 'privacy:metadata:enrol_paybank:enrol_paybank:timeupdated'
                ],
                'privacy:metadata:enrol_paybank:enrol_paybank'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        $contextlist = new contextlist();

        // Values of ep.receiver_email and ep.business are already normalised to lowercase characters by Paybank,
        // therefore there is no need to use LOWER() on them in the following query.
        $sql = "SELECT ctx.id
                  FROM {enrol_paybank} ep
                  JOIN {enrol} e ON ep.instanceid = e.id
                  JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
             LEFT JOIN {user} u ON u.id = :emailuserid AND (
                    LOWER(u.email) = ep.receiver_email
                        OR
                    LOWER(u.email) = ep.business
                )
                 WHERE ep.userid = :userid
                       OR u.id IS NOT NULL";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $userid,
            'emailuserid'   => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        // Values of ep.receiver_email and ep.business are already normalised to lowercase characters by Paybank,
        // therefore there is no need to use LOWER() on them in the following query.
        $sql = "SELECT ep.*
                  FROM {enrol_paybank} ep
                  JOIN {enrol} e ON ep.instanceid = e.id
                  JOIN {context} ctx ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
             LEFT JOIN {user} u ON u.id = :emailuserid AND (
                    LOWER(u.email) = ep.receiver_email
                        OR
                    LOWER(u.email) = ep.business
                )
                 WHERE ctx.id {$contextsql}
                       AND (ep.userid = :userid
                        OR u.id IS NOT NULL)
              ORDER BY e.courseid";

        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $user->id,
            'emailuserid'   => $user->id,
        ];
        $params += $contextparams;

        // Reference to the course seen in the last iteration of the loop. By comparing this with the current record, and
        // because we know the results are ordered, we know when we've moved to the Paybank transactions for a new course
        // and therefore when we can export the complete data for the last course.
        $lastcourseid = null;

        $strtransactions = get_string('transactions', 'enrol_paybank');
        $transactions = [];
        $paybankrecords = $DB->get_recordset_sql($sql, $params);
        foreach ($paybankrecords as $paybankrecord) {
            if ($lastcourseid != $paybankrecord->courseid) {
                if (!empty($transactions)) {
                    $coursecontext = \context_course::instance($paybankrecord->courseid);
                    writer::with_context($coursecontext)->export_data(
                            [$strtransactions],
                            (object) ['transactions' => $transactions]
                    );
                }
                $transactions = [];
            }

            $transaction = (object) [
                'receiver_id'         => $paybankrecord->receiver_id,
                'item_name'           => $paybankrecord->item_name,
                'userid'              => $paybankrecord->userid,
                'memo'                => $paybankrecord->memo,
                'tax'                 => $paybankrecord->tax,
                'option_name1'        => $paybankrecord->option_name1,
                'option_selection1_x' => $paybankrecord->option_selection1_x,
                'option_name2'        => $paybankrecord->option_name2,
                'option_selection2_x' => $paybankrecord->option_selection2_x,
                'payment_status'      => $paybankrecord->payment_status,
                'pending_reason'      => $paybankrecord->pending_reason,
                'reason_code'         => $paybankrecord->reason_code,
                'txn_id'              => $paybankrecord->txn_id,
                'parent_txn_id'       => $paybankrecord->parent_txn_id,
                'payment_type'        => $paybankrecord->payment_type,
                'timeupdated'         => \core_privacy\local\request\transform::datetime($paybankrecord->timeupdated),
            ];
            if ($paybankrecord->userid == $user->id) {
                $transaction->userid = $paybankrecord->userid;
            }
            if ($paybankrecord->business == \core_text::strtolower($user->email)) {
                $transaction->business = $paybankrecord->business;
            }
            if ($paybankrecord->receiver_email == \core_text::strtolower($user->email)) {
                $transaction->receiver_email = $paybankrecord->receiver_email;
            }

            $transactions[] = $paybankrecord;

            $lastcourseid = $paybankrecord->courseid;
        }
        $paybankrecords->close();

        // The data for the last activity won't have been written yet, so make sure to write it now!
        if (!empty($transactions)) {
            $coursecontext = \context_course::instance($paybankrecord->courseid);
            writer::with_context($coursecontext)->export_data(
                    [$strtransactions],
                    (object) ['transactions' => $transactions]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('enrol_paybank', array('courseid' => $context->instanceid));
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        $contexts = $contextlist->get_contexts();
        $courseids = [];
        foreach ($contexts as $context) {
            if ($context instanceof \context_course) {
                $courseids[] = $context->instanceid;
            }
        }

        list($insql, $inparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);

        $select = "userid = :userid AND courseid $insql";
        $params = $inparams + ['userid' => $user->id];
        $DB->delete_records_select('enrol_paybank', $select, $params);

        // We do not want to delete the payment record when the user is just the receiver of payment.
        // In that case, we just delete the receiver's info from the transaction record.

        $select = "business = :business AND courseid $insql";
        $params = $inparams + ['business' => \core_text::strtolower($user->email)];
        $DB->set_field_select('enrol_paybank', 'business', '', $select, $params);

        $select = "receiver_email = :receiver_email AND courseid $insql";
        $params = $inparams + ['receiver_email' => \core_text::strtolower($user->email)];
        $DB->set_field_select('enrol_paybank', 'receiver_email', '', $select, $params);
    }
}
