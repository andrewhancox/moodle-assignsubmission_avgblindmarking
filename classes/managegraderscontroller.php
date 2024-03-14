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

use core_php_time_limit;
use core_text;
use html_table;
use html_writer;

defined('MOODLE_INTERNAL') || die();

class managegraderscontroller extends basecontroller {
    public function summary() {
        global $OUTPUT;

        return $OUTPUT->single_button($this->getinternallink('managegraders'),
            get_string('managegraders', 'assignsubmission_avgblindmarking'), 'get');
    }

    public function managegraders() {
        global $OUTPUT;

        $sort = optional_param('tsort', 'lastname, firstname', PARAM_ALPHA);
        $table = new managegraderstable($this, $sort);

        $table->define_baseurl($this->getinternallink('managegraders'));
        $o = $this->getheader(get_string('managegraders', 'assignsubmission_avgblindmarking'));

        ob_start();
        $table->out(5000, true);
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= $OUTPUT->single_button($this->getinternallink('importgraders'),
            get_string('importgraders', 'assignsubmission_avgblindmarking'), 'get');

        $o .= $OUTPUT->single_button($this->getinternallink('editgraderalloc'),
            get_string('creategraderalloc', 'assignsubmission_avgblindmarking'), 'get');

        $o .= $this->getfooter();

        return $o;
    }

    public function editgraderalloc() {
        $learnerid = optional_param('learnerid', null, PARAM_INT);
        $learner = \core_user::get_user($learnerid);

        list($sort, $params) = users_order_by_sql('u');
        // Only enrolled users could be assigned as potential markers.
        $markers = get_enrolled_users($this->get_assign()->get_context(), 'mod/assign:grade', 0, 'u.*', $sort);
        $markerlist = [];
        foreach ($markers as $marker) {
            $markerlist[$marker->id] = fullname($marker);
        }
        $learners = get_enrolled_users($this->get_assign()->get_context(), 'mod/assign:submit', 0, 'u.*', $sort);
        $learnerlist = [];
        foreach ($learners as $learneroption) {
            $learnerlist[$learneroption->id] = fullname($learneroption);
        }

        $currentgraderalloc = [];
        foreach (graderalloc::get_records(['learneruserid' => $learnerid]) as $graderalloc) {
            $currentgraderalloc[] = $graderalloc->get('graderuserid');
        }

        $mform = new editgraderallocform($this->getinternallink('editgraderalloc'), [
            'markers' => $markerlist,
            'learners' => $learnerlist,
            'learnerid' => $learnerid,
        ]);

        if ($mform->is_cancelled()) {
            redirect($this->getinternallink('managegraders'));
        }

        if ($data = $mform->get_data()) {
            $assigninstance = $this->assignment->get_instance();

            $existingrecords = graderalloc::get_records([
                'assignid' => $assigninstance->id,
                'learneruserid' => $learnerid,
            ]);

            if (!empty($existingrecords)) {
                foreach ($existingrecords as $existingrecord) {
                    $existingrecord->delete();
                }
            }

            foreach ($data->markers as $grader) {
                $graderalloc = new graderalloc();
                $graderalloc->set('assignid', $assigninstance->id);
                $graderalloc->set('learneruserid', $learnerid);
                $graderalloc->set('graderuserid', $grader);
                $graderalloc->save();
            }

            redirect($this->getinternallink('managegraders'));
        }

        $mform->set_data([
            'markers' => $currentgraderalloc,
            'id' => $this->assignment->get_course_module()->id,
        ]);

        if (empty($learner)) {
            $o = $this->getheader(get_string('editgraderalloc', 'assignsubmission_avgblindmarking'));
        } else {
            $o = $this->getheader(get_string('editgraderallocusername', 'assignsubmission_avgblindmarking', fullname($learner)));
        }

        $o .= $mform->render();
        $o .= $this->getfooter();

        return $o;
    }

    public function importgraders() {
        global $SESSION;

        $form = new importgradersform($this->getinternallink('importgraders'));

        if ($form->is_cancelled()) {
            redirect($this->getinternallink('managegraders'));
        }

        if ($data = $form->get_data()) {
            // Handle data
            $rawinput = null;
            // Large files are likely to take their time and memory. Let PHP know
            // that we'll take longer, and that the process should be recycled soon
            // to free up memory.
            core_php_time_limit::raise();
            raise_memory_limit(MEMORY_HUGE);
            if (function_exists('apache_child_terminate')) {
                @apache_child_terminate();
            }

            $text = $form->get_file_content('csvfile');

            // Trim utf-8 bom.
            $text = core_text::trim_utf8_bom($text);
            // Do the encoding conversion.
            $rawinput = core_text::convert($text, $data->encoding);

            $importer = new importgradersimporter($this->assignment);
            $importer->loaddata($rawinput);

            // Record results in session for results dialog to access
            if (empty($SESSION->assignsubmission_avgblindmarking_results)) {
                $SESSION->assignsubmission_avgblindmarking_results = [];
            }
            $SESSION->assignsubmission_avgblindmarking_results = [$importer->gradercount, $importer->errors, $importer->log];

            redirect($this->getinternallink('importgradersresults'));
        }

        $form->set_data(['id' => $this->assignment->get_course_module()->id]);

        $o = $this->getheader(get_string('importgraders', 'assignsubmission_avgblindmarking'));
        $o .= $form->render();
        $o .= $this->getfooter();

        return $o;
    }

    public function importgradersresults() {
        global $OUTPUT, $SESSION;

        if (!isset($SESSION->assignsubmission_avgblindmarking_results)) {
            redirect($this->getinternallink('managegraders'));
        }

        $results = $SESSION->assignsubmission_avgblindmarking_results;

        $added = $results[0];
        $errors = $results[1];
        $log = $results[2];

        // Display results
        $resultmessage = '';
        if ($errors) {
            if ($added) {
                $resultmessage .= get_string('successfullyaddededitedxgraderallocations', 'assignsubmission_avgblindmarking', $added) . '<br>';
            }
            $resultmessage .= get_string('xerrorsencounteredduringimport', 'assignsubmission_avgblindmarking', count($errors));
        } else {
            $resultmessage .= get_string('successfullyaddededitedxgraderallocations', 'assignsubmission_avgblindmarking', count($added));
        }

        $oput = $this->getheader(get_string('importgraders', 'assignsubmission_avgblindmarking'));

        $oput .= $resultmessage;

        if (!empty($errors)) {
            $oput .= $OUTPUT->box_start();

            $oput .= html_writer::tag('h3', get_string('errors', 'assignsubmission_avgblindmarking'));

            $table = new html_table();
            $table->head = [''];

            $table->data = [];
            foreach ($errors as $error) {
                $table->data[] = [$error];
            }

            $oput .= html_writer::table($table);
            $oput .= $OUTPUT->box_end();
        }

        if (!empty($log)) {
            $oput .= $OUTPUT->box_start();

            $oput .= html_writer::tag('h3', get_string('log', 'assignsubmission_avgblindmarking'));

            $table = new html_table();
            $table->head = [''];

            $table->data = [];
            foreach ($log as $entry) {
                $table->data[] = [$entry];
            }

            $oput .= html_writer::table($table);
            $oput .= $OUTPUT->box_end();
        }

        $oput .= $OUTPUT->single_button($this->getinternallink('managegraders'),
            get_string('managegraders', 'assignsubmission_avgblindmarking'), 'get');

        $oput .= $this->getfooter();

        return $oput;
    }
}
