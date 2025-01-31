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
        if (!\assign_submission_avgblindmarking::is_enabled_for_assign($assign)) {
            return;
        }

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
            $nextmarker = self::get_next_marker($relateduserid, $assign);
            if (empty($assignuserflag->allocatedmarker) && !empty($nextmarker)) {
                $assignuserflag->allocatedmarker = $nextmarker;
            }
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
                // Do this now as we want a new grade to get created.
                // Otherwise this gets handled by a shutdown function.
                eventhandlers::finalisedisconnect();

                $grade = $assign->get_user_grade($relateduserid, true);
                $assignuserflag->allocatedmarker = 0;
                $assignuserflag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_INREVIEW;
                $DB->update_record('assign_user_flags', $assignuserflag);

                $gradeinfo = $DB->get_record_sql('SELECT AVG(ag.grade) AS avg,  MAX(ag.grade) - MIN(ag.grade) AS variance
                                        FROM {assign_grades} ag
                                        INNER JOIN {assignsubmission_ass_grade} assg on assg.assigngradeid = ag.id
                                        INNER JOIN {assign_submission} s on s.assignment = ag.assignment AND s.userid = assg.userid AND assg.attemptnumber = s.attemptnumber and s.latest = 1
                                        WHERE ag.assignment = :assignid AND assg.userid = :userid AND s.attemptnumber = :attemptnumber',
                    ['assignid' => $assign->get_instance()->id, 'userid' => $relateduserid, 'attemptnumber' => $submission->attemptnumber]);

                $publishable = true;

                $maxvariance =  $assign->get_submission_plugin_by_type('avgblindmarking')->get_config('maxvarianceforautograde');
                if (!empty($maxvariance)) {
                    $absolutevariance = ($assign->get_instance()->grade / 100) * $maxvariance;
                    if ($gradeinfo->variance > $absolutevariance) {
                        $publishable = false;
                    }
                }
                $grade->grade = $gradeinfo->avg;
                $assign->update_grade($grade);

                if ($publishable) {
                    $assignuserflag->workflowstate = ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE;
                    $DB->update_record('assign_user_flags', $assignuserflag);

                    // Pull together all feedback files and comments to attach to the new grade.
                    $blindgrades = $DB->get_records_sql('SELECT ag.*
                                        FROM {assign_grades} ag
                                        INNER JOIN {assignsubmission_ass_grade} assg on assg.assigngradeid = ag.id
                                        INNER JOIN {assign_submission} s on s.assignment = ag.assignment AND s.userid = assg.userid AND assg.attemptnumber = s.attemptnumber and s.latest = 1
                                        WHERE ag.assignment = :assignid AND assg.userid = :userid AND s.attemptnumber = :attemptnumber',
                        ['assignid' => $assign->get_instance()->id, 'userid' => $relateduserid, 'attemptnumber' => $submission->attemptnumber]);

                    $fs = get_file_storage();
                    $assignfeedback_file_count = 0;
                    $assignfeedback_comments = '';
                    foreach ($blindgrades as $blindgrade) {
                        foreach ($fs->get_area_files($assign->get_context()->id, 'assignfeedback_file', 'feedback_files', $blindgrade->id, 'itemid', false) as $file) {
                            $fs->create_file_from_storedfile(['itemid' => $grade->id], $file);
                            $file->delete();
                            $assignfeedback_file_count++;
                        }

                        foreach ($DB->get_records('assignfeedback_comments', ['grade' => $blindgrade->id]) as $assignfeedback_comment) {
                            if (!empty($assignfeedback_comment->commenttext)) {
                                $assignfeedback_comments .= \html_writer::div($assignfeedback_comment->commenttext);
                            }
                        }

                        foreach ($fs->get_area_files($assign->get_context()->id, 'assignfeedback_comments', 'feedback', $blindgrade->id, 'itemid', false) as $file) {
                            $fs->create_file_from_storedfile(['itemid' => $grade->id], $file);
                            $file->delete();
                        }
                    }

                    if (!empty($assignfeedback_comments)) {
                        $DB->delete_records('assignfeedback_comments', ['grade' => $grade->id]);
                        $DB->insert_record('assignfeedback_comments', ['assignment' => $assign->get_instance()->id, 'grade' => $grade->id, 'commenttext' => $assignfeedback_comments, 'commentformat' => FORMAT_HTML]);
                    }

                    if ($assignfeedback_file_count > 0) {
                        $DB->delete_records('assignfeedback_file', ['grade' => $grade->id]);
                        $DB->insert_record('assignfeedback_file', ['assignment' => $assign->get_instance()->id, 'grade' => $grade->id, 'numfiles' => $assignfeedback_file_count]);
                    }
                }
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

        $assignid = $assign->get_instance()->id;

        $assigngrade = $DB->get_record_sql('SELECT ag.*
                                        FROM {assign_grades} ag
                                        INNER JOIN {assign_submission} s on s.assignment = ag.assignment AND s.userid = ag.userid AND s.attemptnumber = ag.attemptnumber and s.latest = 1
                                        WHERE ag.assignment = :assignid AND ag.userid = :userid AND ag.grader = :grader',
            ['assignid' => $assignid, 'userid' => $learneruserid, 'grader' => $graderuserid]);

        if (empty($assigngrade)) {
            return;
        }

        // Delete old records first to avoid over counting where a grader gets allocated to grade a second time.
        $oldassignsubmission_ass_grade = $DB->get_records_sql('SELECT assg.*
                                        FROM {assign_grades} ag
                                        INNER JOIN {assignsubmission_ass_grade} assg on assg.assigngradeid = ag.id
                                        INNER JOIN {assign_submission} s on s.assignment = ag.assignment AND s.userid = assg.userid AND assg.attemptnumber = s.attemptnumber and s.latest = 1
                                        WHERE ag.assignment = :assignid AND assg.userid = :userid AND ag.grader = :grader',
            ['assignid' => $assignid, 'userid' => $learneruserid, 'grader' => $graderuserid]);
        $DB->delete_records_list('assignsubmission_ass_grade', 'id', array_keys($oldassignsubmission_ass_grade));

        $DB->insert_record('assignsubmission_ass_grade', ['assigngradeid' => $assigngrade->id, 'userid' => $assigngrade->userid, 'attemptnumber' => $assigngrade->attemptnumber]);

        self::$finaldisconnectpending = $assigngrade;

        // VERY nervous about this... In several instances the disconnection gets undone after the event handler that
        // triggered it happens so we're doing it as a shutdown function.
        // We cant just create a new grade  as the calling function already has a reference to it that we can't overwrite.
        // It also, potentially, still needs to make some changes to it.
        // First properly grim bit of code so far...
        register_shutdown_function(static function () {
            eventhandlers::finalisedisconnect();
        });
    }

    private static $finaldisconnectpending = null;

    public static function finalisedisconnect() {
        if (empty(self::$finaldisconnectpending)) {
            return;
        }
        global $DB;
        $DB->set_field('assign_grades', 'userid', -1, ['id' => self::$finaldisconnectpending->id]);
        $DB->set_field('assign_grades', 'attemptnumber',
            $DB->get_field('assign_grades', 'min(attemptnumber)', ['assignment' => self::$finaldisconnectpending->assignment]) - 1, ['id' => self::$finaldisconnectpending->id]
        );
        self::$finaldisconnectpending = null;
    }

    public static function get_next_marker($learneruserid, \assign $assign) {
        $graded = [];
        foreach (self::get_assign_grades($assign->get_instance()->id, $learneruserid) as $grade) {
            $graded[] = $grade->grader;
        }

        $graders = [];
        foreach (graderalloc::get_records(['learneruserid' => $learneruserid, 'assignid' => $assign->get_instance()->id]) as $grader) {
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

        return $DB->get_records_sql('SELECT ag.* FROM {assign_grades} ag
                                        INNER JOIN {assignsubmission_ass_grade} assg ON assg.assigngradeid = ag.id
                                        INNER JOIN {assign_submission} s on s.assignment = ag.assignment AND s.userid = assg.userid AND assg.attemptnumber = s.attemptnumber and s.latest = 1
                                        WHERE ag.assignment = :assignid AND assg.userid = :userid',
            ['assignid' => $assignid, 'userid' => $learneruserid]);
    }
}
