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

defined('MOODLE_INTERNAL') || die();

use assign;
use context_module;
use core\persistent;

class avgblindsubmission extends persistent {
    const TABLE = 'assignsubmission_avgbm_sub';

    protected static function define_properties() {
        return [
            'submissionid' => [
                'type' => PARAM_INT,
            ],
            'originalsubmissionid' => [
                'type' => PARAM_INT,
            ],
            'graderallocid' => [
                'type' => PARAM_INT,
            ],
        ];
    }

    public static function spawnfromusersubmission(context_module $context, $submissionid) {
        global $DB;

        $submission = $DB->get_record('assign_submission', ['id' => $submissionid, 'latest' => 1]);
        $assignment = new assign($context);

        self::save_avgblindsubmission_submission($assignment, $submission);
    }

    /**
     * @param assign $assignment
     * @return int|mixed
     * @throws \dml_exception
     */
    public static function getnextuserid(assign $assignment) {
        global $DB;

        $nextuserid = $DB->get_field_sql('select min(userid) from {assign_submission} where assignment = :assignment',
                ['assignment' => $assignment->get_instance()->id]);
        if ($nextuserid > 0) {
            $nextuserid = -1;
        } else {
            $nextuserid -= 1;
        }
        return $nextuserid;
    }

    public static function save_avgblindsubmission_submission(assign $assignment, $source_submission, $graderalloc) {
        global $DB;

        $notices = [];

        $trans = $DB->start_delegated_transaction();
        $nextuserid = self::getnextuserid($assignment);

        $target_submission = $assignment->get_user_submission($nextuserid, true);
        $target_submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $target_submission->timemodified = time();
        $DB->update_record('assign_submission', $target_submission);

        foreach ($assignment->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                if (!$plugin->copy_submission($source_submission, $target_submission)) {
                    $notices[] = $plugin->get_error();
                } else {
                    $plugin->submit_for_grading($target_submission);
                }
            }
        }

        if ($assignment->submission_empty($target_submission)) {
            $notices[] = get_string('submissionempty', 'mod_assign');
        }

        $trans->allow_commit();

        $avgblindsubmission = new avgblindsubmission();
        $avgblindsubmission->set_many(['submissionid' => $target_submission->id, 'originalsubmissionid' => $source_submission->id, 'graderallocid' => $graderalloc->get('id')]);
        $avgblindsubmission->save();

        return $notices;
    }
}
