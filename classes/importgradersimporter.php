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

use core_text;
use core_user;

require_once($CFG->dirroot . '/user/lib.php');

class importgradersimporter {
    public $errors;
    public $log;
    public $gradercount;

    private \assign $assign;

    public function __construct(\assign $assignment) {
        $this->assign = $assignment;
    }

    public function loaddata($csvstring) {
        $line = 0;

        $this->errors = [];
        $this->gradercount = 0;

        $assigninstance = $this->assign->get_instance();

        if (empty($csvstring)) {
            $this->errors[] = get_string('error:nodatasupplied', 'assignsubmission_avgblindmarking');
            return;
        }

        $csvstring = explode("\n",
            str_replace(["\r\n", "\n\r", "\r"], "\n", $csvstring)
        );

        $markerlist = [];
        foreach (get_enrolled_users($this->assign->get_context(), 'mod/assign:grade') as $marker) {
            $markerlist[$marker->email] = $marker;
        }

        foreach ($csvstring as $csvline) { // if the line is over 8k, it won't work...$line = 0
            $csvdata = str_getcsv($csvline);

            $line += 1;
            if ($line == 1) { // Check the headers.
                $firstheader = array_shift($csvdata);
                if (core_text::strtolower($firstheader) != 'learneremail') {
                    $this->errors[] = 'Unexpected table headers.';
                    return;
                }

                foreach ($csvdata as $csvdatum) {
                    if (!empty($csvdatum) && strpos(core_text::strtolower($csvdatum), 'graderemail_') !== 0) {
                        $this->errors[] = 'Unexpected table headers.';
                        return;
                    }
                }
            } else {
                $csvdata = str_getcsv($csvline);

                $learneremail = array_shift($csvdata);
                $learner = core_user::get_user_by_email($learneremail);

                if (empty($learner)) {
                    $this->errors[] = "Unknown user: $learneremail";
                    continue;
                }

                $graders = [];
                foreach ($csvdata as $graderemail) {
                    if (empty($graderemail)) {
                        continue;
                    }

                    $grader = $markerlist[$graderemail] ?? null;

                    if (empty($grader)) {
                        $graderuserobject = core_user::get_user_by_email($graderemail);

                        if (empty($graderuserobject)) {
                            $this->errors[] = "Unknown user: $graderemail";
                        } else {
                            $this->errors[] = "User does not have mod/assign:grade capability: $graderemail";
                        }

                        continue;
                    }

                    $graders[] = $grader;
                }

                $existingrecords = graderalloc::get_records([
                    'assignid' => $assigninstance->id,
                    'learneruserid' => $learner->id,
                ]);

                if (!empty($existingrecords)) {
                    $this->log[] = "Deleting all existing allocations for:" . $learner->email;
                    foreach ($existingrecords as $existingrecord) {
                        $existingrecord->delete();
                    }
                }

                foreach ($graders as $grader) {
                    $graderalloc = new graderalloc();
                    $graderalloc->set('assignid', $assigninstance->id);
                    $graderalloc->set('learneruserid', $learner->id);
                    $graderalloc->set('graderuserid', $grader->id);
                    $graderalloc->save();

                    $this->gradercount += 1;

                    $this->log[] = "Grader allocation created:" . $learner->email . ' - ' . $grader->email;
                }
            }
        }
    }
}
