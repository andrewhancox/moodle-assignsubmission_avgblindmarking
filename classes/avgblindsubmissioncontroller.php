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

class avgblindsubmissioncontroller extends basecontroller {
    public function summary() {
        global $OUTPUT;
        return $OUTPUT->single_button($this->getinternallink('manageavgblindsubmissions'),
                get_string('manageavgblindsubmissions', 'assignsubmission_avgblindmarking'), 'get');
    }

    public function viewdelete() {
        $avgblindsubmissionid = required_param('avgblindsubmissionid', PARAM_INT);

        $mform = new avgblindsubmissiondeleteform($this->getinternallink('deleteavgblindsubmission'));

        if ($mform->is_cancelled()) {
            redirect($this->getinternallink('manageavgblindsubmissions'));
        } else if ($data = $mform->get_data()) {
            $avgblindsubmission = avgblindsubmission::get_record(['id' => $data->avgblindsubmissionid]);
            $avgblindsubmission->delete_avgblindsubmission_submission($this->assignment);
            redirect($this->getinternallink('manageavgblindsubmissions'));
        } else {
            $mform->set_data(['avgblindsubmissionid' => $avgblindsubmissionid]);
        }

        $o = $this->getheader(get_string('deleteavgblindsubmission', 'assignsubmission_avgblindmarking'));
        $o .= $this->renderer->render(new \assign_form('editsubmissionform', $mform));
        $o .= $this->getfooter();

        return $o;
    }

    public function viewmanageavgblindsubmissions() {
        global $OUTPUT;

        $sort = optional_param('tsort', 'lastname, firstname', PARAM_ALPHA);
        $table = new avgblindsubmissiontable($this->assignment, $sort);
        $table->define_baseurl($this->getinternallink('manageavgblindsubmissions'));

        $o = $this->getheader(get_string('manageavgblindsubmissions', 'assignsubmission_avgblindmarking'));
        ob_start();
        $table->out(25, false);
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= $OUTPUT->single_button($this->getinternallink('addavgblindsubmission'),
                get_string('addavgblindsubmission', 'assignsubmission_avgblindmarking'));

        $o .= $this->getfooter();

        return $o;
    }

    public function view() {
        global $PAGE, $DB;

        $avgblindsubmissionid = optional_param('avgblindsubmissionid', false, PARAM_INT);
        if ($avgblindsubmissionid) {
            $avgblindsubmission = avgblindsubmission::get_record(['id' => $avgblindsubmissionid]);
            $submission = $DB->get_record('assign_submission', ['id' => $avgblindsubmission->get('submissionid'), 'latest' => 1]);

            $data = new \stdClass();
            $data->userid = $submission->userid;
        } else {
            $submission = null;

            $data = new \stdClass();
            $data->userid = avgblindsubmission::getnextuserid($this->assignment);
        }
        $url = $this->getinternallink('addavgblindsubmission');
        $url->param('avgblindsubmissionid', $avgblindsubmissionid);
        $PAGE->set_url($url);

        $mform = new avgblindsubmissionform($url, [$this->assignment, $data, $submission]);

        if ($mform->is_cancelled()) {
            redirect($this->getinternallink('manageavgblindsubmissions'));
        } else if ($data = $mform->get_data()) {
            avgblindsubmission::save_avgblindsubmission_submission($data, $this->assignment, $submission, $notices);
            // Do something with notices.
            redirect($this->getinternallink('manageavgblindsubmissions'));
        }

        $o = $this->getheader(get_string('editavgblindsubmission', 'assignsubmission_avgblindmarking'));
        $o .= $this->renderer->render(new \assign_form('editsubmissionform', $mform));
        $o .= $this->getfooter();

        return $o;
    }
}
