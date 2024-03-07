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
 * @copyright 2024 Andrew Hancox at Open Source Learning <andrewdchancox@googlemail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignsubmission_avgblindmarking;

use stdClass;

require_once("$CFG->dirroot/mod/assign/locallib.php");

class eventhandlers {
    public static function assessable_submitted(\mod_assign\event\assessable_submitted $event) {
        global $DB;

        $relateduserid = $DB->get_field('assign_submission', 'userid', ['id' => $event->objectid]);

        self::updateworkflowstateandmarker($event, $relateduserid);
    }
    public static function submission_status_updated(\mod_assign\event\submission_status_updated $event) {

        if ($event->other['newstatus'] !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return;
        }

        self::updateworkflowstateandmarker($event);
    }

    public static function workflow_state_updated(\mod_assign\event\workflow_state_updated $event) {
        self::updateworkflowstateandmarker($event);
    }

    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        self::updateworkflowstateandmarker($event);
    }

    private static function updateworkflowstateandmarker(\mod_assign\event\base $event, $relateduserid = null) {
        global $DB;

        $relateduserid = $relateduserid ?? $event->relateduserid;

        $assign = $event->get_assign();

        $assignuserflag = $DB->get_record('assign_user_flags', ['userid' => $relateduserid, 'assignment' => $assign->get_instance()->id]);

        if (empty($assignuserflag)) {
            $assignuserflag = new stdClass();
            $assignuserflag->userid = $relateduserid;
            $assignuserflag->assignment = $assign->get_instance()->id;
            $assignuserflag->workflowstate = '';
            $assignuserflag->id = $DB->insert_record('assign_user_flags', $assignuserflag);
        }

        $submission = $assign->get_user_submission($relateduserid, false);
        $grade = $assign->get_user_grade($relateduserid, false);

        if ($submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED && empty($assignuserflag->workflowstate)) {
            $assignuserflag->allocatedmarker = self::get_next_marker($relateduserid, $assign);
            $assignuserflag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
            $DB->update_record('assign_user_flags', $assignuserflag);

            return true;
        }

        if (empty($grade) || $grade->grade == -1) {
            return true;
        }

        if (empty($assignuserflag->workflowstate) || $assignuserflag->workflowstate == ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW) {
            if ($assignuserflag->workflowstate == ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW) {
                self::disconnect_latest_grade($relateduserid, $event->userid, $assign);
            }

            $nextmarker = self::get_next_marker($relateduserid, $assign);
            $finalmarkersubmitting = empty($nextmarker);

            if ($finalmarkersubmitting) {
                $grade = $assign->get_user_grade($relateduserid, true);
                $assignuserflag->allocatedmarker = 0;
                $assignuserflag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_INREVIEW;
                $DB->update_record('assign_user_flags', $assignuserflag);

                $grade->grade = $DB->get_field_sql('select AVG(ag.grade) from {assign_grades} ag
                                        inner join {assignsubmission_ass_grade} assg on assg.assigngradeid = ag.id
                                        where ag.assignment = :assignid and assg.userid = :userid',
                    ['assignid' => $assign->get_instance()->id, 'userid' => $relateduserid]);
                $assign->update_grade($grade);
            } else {
                $assignuserflag->allocatedmarker = $nextmarker;
                $assignuserflag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
                $DB->update_record('assign_user_flags', $assignuserflag);
            }
        }

        return true;
    }

    private static function disconnect_latest_grade($learneruserid, $graderuserid, \assign $assign) {
        global $DB;

        $assigngrade = $DB->get_record('assign_grades', ['userid' => $learneruserid, 'assignment' => $assign->get_instance()->id, 'grader' => $graderuserid]);

        if (empty($assigngrade)) {
            return;
        }

        $assigngrade->userid = -1;
        $assigngrade->attemptnumber = $DB->get_field('assign_grades', 'max(attemptnumber)', []) + 1;
        $DB->update_record('assign_grades', $assigngrade);

        $DB->insert_record('assignsubmission_ass_grade', ['assigngradeid' => $assigngrade->id, 'userid' => $learneruserid]);
    }

    public static function get_next_marker($learneruserid, \assign $assign) {
        $graded = [];
        foreach (self::get_assign_grades($assign->get_instance()->id, $learneruserid) as $grade) {
            $graded[] = $grade->grader;
        }

        $graders = [];
        foreach (graderalloc::get_records(['learneruserid' => $learneruserid]) as $grader) {
            $graders[] = $grader->get('graderuserid');
        }

        $tograde = array_diff($graders, $graded);

        if (!empty($tograde)) {
            return reset($tograde);
        }

        return null;
    }

    private static function get_assign_grades($assignid, $learneruserid) {
        global $DB;

        return $DB->get_records_sql('select ag.* from {assign_grades} ag
                                        inner join {assignsubmission_ass_grade} assg on assg.assigngradeid = ag.id
                                        where ag.assignment = :assignid and assg.userid = :userid',
            ['assignid' => $assignid, 'userid' => $learneruserid]);
    }
}
