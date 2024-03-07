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

defined('MOODLE_INTERNAL') || die();

class managemyblindgradescontroller extends manageblindgradescontrollerbase {
    public function summary() {
        global $OUTPUT;

        return $OUTPUT->single_button($this->getinternallink('managemyblindgrades'),
                get_string('managemyblindgrades', 'assignsubmission_avgblindmarking'), 'get');
    }

    public function managemyblindgrades() {
        global $USER;

        return parent::renderblindgradestable($USER->id);
    }

    public function viewblindgrade() {
        global $USER, $DB;

        $assigngradeid = required_param('assigngradeid', PARAM_INT);
        $grade = $DB->get_record('assign_grades', ['id' => $assigngradeid]);

        if ($grade->grader <> $USER->id) {
            require_capability('assignsubmission/avgblindmarking:managegraders', $this->assignment->get_context());
        }

        return parent::renderblindgrade();
    }
}
