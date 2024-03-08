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
use assignsubmission_avgblindmarking\managemyblindgradescontroller;

require_once($CFG->dirroot . '/comment/lib.php');
require_once($CFG->dirroot . '/mod/assign/submissionplugin.php');

class assign_submission_avgblindmarking extends assign_submission_plugin {
    public function get_name() {
        return get_string('pluginname', 'assignsubmission_avgblindmarking');
    }

    public function view_summary(stdClass $submissionorgrade, &$showviewlink) {
        global $DB;
        $assignuserflag = $DB->get_record('assign_user_flags', ['userid' => $submissionorgrade->userid, 'assignment' => $submissionorgrade->assignment]);

        if (empty($assignuserflag)) {
            return '';
        } else if ($assignuserflag->workflowstate == ASSIGN_MARKING_WORKFLOW_STATE_INREVIEW) {
            $controller = new manageblindgradescontroller($this->assignment);
            return html_writer::link(
                $controller->getinternallink('manageblindgrades', ['learnerid' => $submissionorgrade->userid]),
                get_string("outsidevariancerequiresreview", 'assignsubmission_avgblindmarking'),
                ['class' => 'btn btn-primary']
            );
        } else if (in_array($assignuserflag->workflowstate, [ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED, ASSIGN_MARKING_WORKFLOW_STATE_INMARKING, ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW])) {
            $submittedgrades = $DB->count_records_sql('select count(ag.id)
                                        from {assign_grades} ag
                                        INNER JOIN {assignsubmission_ass_grade} bg on bg.assigngradeid = ag.id
                                        INNER JOIN {assignsubmission_graderalloc} ga on ag.grader = ga.graderuserid and bg.userid = ga.learneruserid and ga.assignid = ag.assignment
                                        WHERE bg.userid = :userid AND ag.assignment = :assignmentid',
                ['userid' => $submissionorgrade->userid, 'assignmentid' => $submissionorgrade->assignment]);
            $requiredgrades = \assignsubmission_avgblindmarking\graderalloc::count_records(['learneruserid' => $submissionorgrade->userid]);

            return get_string('requiredvsubmitted', 'assignsubmission_avgblindmarking', (object)['required' => $requiredgrades, 'submitted' => $submittedgrades]);
        }

        return '';
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

        $controllers = [];

        if (has_capability('assignsubmission/avgblindmarking:managegraders', $this->assignment->get_context())) {
            $controllers[] = new manageblindgradescontroller($this->assignment);
            $controllers[] = new managegraderscontroller($this->assignment);
        }

        if (has_capability('mod/assign:grade', $this->assignment->get_context())) {
            $controllers[] = new managemyblindgradescontroller($this->assignment);
        }

        if (empty($controllers)) {
            return '';
        }

        $o = '';
        $o .= $OUTPUT->container_start('manageblindgrades');
        $o .= $OUTPUT->box_start('boxaligncenter manageblindgradesbuttons');

        foreach ($controllers as $controller) {
            $o .= $controller->summary();
        }

        $o .= $OUTPUT->box_end();
        $o .= $OUTPUT->container_end();

        return $o;
    }

    public function view_page($action) {
        $controllers = [];

        if (has_capability('assignsubmission/avgblindmarking:managegraders', $this->assignment->get_context())) {
            $controllers[] = new manageblindgradescontroller($this->assignment);
            $controllers[] = new managegraderscontroller($this->assignment);
        }

        if (has_capability('mod/assign:grade', $this->assignment->get_context())) {
            $controllers[] = new managemyblindgradescontroller($this->assignment);
        }

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
