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
 * @package    assignsubmission_avgblindmarking
 * @author Andrew Hancox <andrewdchancox@googlemail.com>
 * @author Open Source Learning <enquiries@opensourcelearning.co.uk>
 * @link https://opensourcelearning.co.uk
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2024, Andrew Hancox
 */

namespace assignsubmission_avgblindmarking;

class eventhandlers {
    public static function workflow_state_updated(\mod_assign\event\workflow_state_updated $event) {
        global $DB, $CFG;

        require_once("$CFG->dirroot/mod/assign/locallib.php");

        $assign = $event->get_assign();

        $assign_user_flag = $DB->get_record('assign_user_flags', ['userid' => $event->relateduserid, 'assignment' => $assign->get_instance()->id]);

        if ($assign_user_flag->workflowstate !== ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW) {
            return;
        }

        $nextmarker = self::get_next_marker($event->relateduserid, $assign->get_instance()->id);

        $finalmarkersubmitting = empty($nextmarker);
        if ($finalmarkersubmitting) {
            $assign_user_flag->allocatedmarker = 0;
            $assign_user_flag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_INREVIEW;
        } else {
            $assign_user_flag->allocatedmarker = $nextmarker;
            $assign_user_flag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
        }

        $tran = $DB->start_delegated_transaction();

        $DB->update_record('assign_user_flags', $assign_user_flag);

        // If we're not blind marking then we need to catch this after the \assign::gradebook_item_update function has run
        // by catching the submission_graded event.
        if ($assign->is_blind_marking()) {
            self::disconnect_latest_grade($event->relateduserid, $assign, $finalmarkersubmitting);
        }

        $tran->allow_commit();
    }

    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        global $DB;

        $assign = $event->get_assign();

        $assign_user_flag = $DB->get_record('assign_user_flags', ['userid' => $event->relateduserid, 'assignment' => $assign->get_instance()->id]);

        $finalmarkersubmitted = $assign_user_flag->workflowstate == ASSIGN_MARKING_WORKFLOW_STATE_INREVIEW;

        if (
            (!$assign->is_blind_marking()&&$assign_user_flag->workflowstate == ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED)
            ||
            $finalmarkersubmitted
        ) {
            self::disconnect_latest_grade($event->relateduserid, $assign, $finalmarkersubmitted);
        }
    }

    private static function disconnect_latest_grade($userid, \assign $assign, $createfinalgrade) {
        global $DB;

        $assigngrade = $DB->get_record('assign_grades', ['userid' => $userid, 'assignment' => $assign->get_instance()->id]);
        $assigngrade->userid = -1;
        $assigngrade->attemptnumber = $DB->get_field('assign_grades', 'max(attemptnumber)', []) + 1;
        $DB->update_record('assign_grades', $assigngrade);

        //$DB->insert_record('assignsubmission_ass_grade', ['assigngradeid' => $assigngrade->id, 'userid' => $userid]);

        if ($createfinalgrade) {
            $grade = $assign->get_user_grade($userid, true);
            $grade->grade = '25.00000';
            $assign->update_grade($grade);
        }
    }

    private static function get_next_marker($userid, $assignmentid) {
        return null;
        return 668;
    }
}