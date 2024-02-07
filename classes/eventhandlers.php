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

        $assign_user_flag = $DB->get_record('assign_user_flags', ['userid' => $relateduserid, 'assignment' => $assign->get_instance()->id]);

        if (empty($assign_user_flag)) {
            $assign_user_flag = new stdClass();
            $assign_user_flag->userid = $relateduserid;
            $assign_user_flag->assignment = $assign->get_instance()->id;
            $assign_user_flag->workflowstate = '';
            $assign_user_flag->id = $DB->insert_record('assign_user_flags', $assign_user_flag);
        }

        $submission = $assign->get_user_submission($relateduserid, false);
        $grade = $assign->get_user_grade($relateduserid, false);

        if ($submission->status == ASSIGN_SUBMISSION_STATUS_SUBMITTED && empty($assign_user_flag->workflowstate)) {
            $assign_user_flag->allocatedmarker = self::get_next_marker($relateduserid, $assign);
            $assign_user_flag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
            $DB->update_record('assign_user_flags', $assign_user_flag);

            return true;
        }


        if (empty($grade) || $grade->grade == -1) {
            return true;
        }

        if (empty($assign_user_flag->workflowstate) || $assign_user_flag->workflowstate == ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW) {
            if ($assign_user_flag->workflowstate == ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW) {
                self::disconnect_latest_grade($relateduserid, $event->userid, $assign);
            }

            $nextmarker = self::get_next_marker($relateduserid, $assign);
            $finalmarkersubmitting = empty($nextmarker);

            if ($finalmarkersubmitting) {
                $grade = $assign->get_user_grade($relateduserid, true);
                $assign_user_flag->allocatedmarker = 0;
                $assign_user_flag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_INREVIEW;
                $DB->update_record('assign_user_flags', $assign_user_flag);

                $grade->grade = $DB->get_field_sql('select AVG(ag.grade) from {assign_grades} ag
                                        inner join {assignsubmission_ass_grade} assg on assg.assigngradeid = ag.id
                                        where ag.assignment = :assignid and assg.userid = :userid',
                    ['assignid' => $assign->get_instance()->id, 'userid' => $relateduserid]);
                $assign->update_grade($grade);
            } else {
                $assign_user_flag->allocatedmarker = $nextmarker;
                $assign_user_flag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED;
                $DB->update_record('assign_user_flags', $assign_user_flag);
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

    private static function get_next_marker($learneruserid, \assign $assign) {
        $teacherroles = array_keys(get_archetype_roles('editingteacher') + get_archetype_roles('teacher'));

        $grades = self::get_assign_grades($assign->get_instance()->id, $learneruserid);

        $graders = [];
        foreach ($teacherroles as $roleid) {
            $graders = array_merge($graders, array_keys(get_role_users($roleid, $assign->get_course_context())));
        }

        $graded = [];
        foreach ($grades as $grade) {
            $graded[] = $grade->grader;
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