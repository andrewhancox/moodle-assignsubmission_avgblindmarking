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

defined('MOODLE_INTERNAL') || die();

use assignsubmission_avgblindmarking\avgblindsubmissioncontroller;

require_once($CFG->dirroot . '/comment/lib.php');
require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

class assign_submission_avgblindmarking extends assign_submission_plugin {
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_avgblindmarking');
    }

    public function delete_instance() {
        global $DB;

        $DB->delete_records('assignsubmission_graderalloc',
            ['assignid' => $this->assignment->get_instance()->id]);

        $DB->delete_records_subquery('assignsubmission_ass_grade', 'assigngradeid', 'id',
            'SELECT id from {assign_grades} WHERE assignment = :assignid', ['assignid' => $this->assignment->get_instance()->id]);
    }
}
