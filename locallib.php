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

defined('MOODLE_INTERNAL') || die();

use assignsubmission_avgblindmarking\avgblindsubmissioncontroller;
use assignsubmission_avgblindmarking\manageblindgradescontroller;
use assignsubmission_avgblindmarking\managegraderscontroller;

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

    public function view_header() {
        global $OUTPUT;

        $o = '';

        $o .= $OUTPUT->container_start('manageblindgrades');
        $o .= $OUTPUT->box_start('boxaligncenter manageblindgradesbuttons');

        if (has_capability('assignsubmission/avgblindmarking:managegraders', $this->assignment->get_context())) {
            $manageblindgradescontroller = new manageblindgradescontroller($this->assignment);
            $o .= $manageblindgradescontroller->summary();

            $managegraderscontroller = new managegraderscontroller($this->assignment);
            $o .= $managegraderscontroller->summary();
        }

        $o .= $OUTPUT->box_end();
        $o .= $OUTPUT->container_end();

        return $o;
    }

    public function view_page($action) {
        require_capability('assignsubmission/avgblindmarking:managegraders', $this->assignment->get_context());

        $controllers = [
            new manageblindgradescontroller($this->assignment),
            new managegraderscontroller($this->assignment),
        ];

        foreach ($controllers as $controller) {
            if (method_exists($controller, $action)) {
                return $controller->$action();
            }
        }
    }

    /**
     * @param MoodleQuickForm $mform The form to add the elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform): void {
        if ($this->assignment->has_instance()) {
            $maxvarianceforautograde = $this->get_config('maxvarianceforautograde');
        } else {
            $maxvarianceforautograde = 20;
        }

        $name = get_string('maxvarianceforautograde', 'assignsubmission_avgblindmarking');
        $mform->addElement('text', 'assignsubmission_avgblindmarking_maxvarianceforautograde', $name);
        $mform->setType('assignsubmission_avgblindmarking_maxvarianceforautograde', PARAM_INT);
        $mform->hideIf('assignsubmission_avgblindmarking_maxvarianceforautograde', 'assignsubmission_avgblindmarking_enabled', 'notchecked');
        $mform->addHelpButton('assignsubmission_avgblindmarking_maxvarianceforautograde', 'maxvarianceforautograde', 'assignsubmission_avgblindmarking');
        $mform->setDefault('assignsubmission_avgblindmarking_maxvarianceforautograde', $maxvarianceforautograde);
    }

    /**
     * @param stdClass $formdata - the data submitted from the form
     * @return bool - on error the subtype should call set_error and return false.
     */
    public function save_settings(stdClass $formdata): bool {
        $this->set_config('maxvarianceforautograde', $formdata->assignsubmission_avgblindmarking_maxvarianceforautograde);

        return true;
    }
}
