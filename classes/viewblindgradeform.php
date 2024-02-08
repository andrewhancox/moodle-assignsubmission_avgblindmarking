<?php

namespace assignsubmission_avgblindmarking;

use moodleform;

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/pear/HTML/QuickForm/input.php');

class viewblindgradeform extends moodleform {
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        $grade = $DB->get_record('assign_grades', ['id' => $this->_customdata['assigngradeid']]);
        $learnerid = $DB->get_field('assignsubmission_ass_grade', 'userid', ['assigngradeid' => $this->_customdata['assigngradeid']]);


        $assign = \assignsubmission_avgblindmarking\extendedassign::get_from_instanceid($grade->assignment);
        $gradinginstance = $assign->get_grading_instance_pub($learnerid, $grade, true);

        $submission = $assign->get_user_submission($learnerid, false);


        $oput = '';
        foreach ($assign->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible() && !$plugin->is_empty($submission)) {
                $oput .= \html_writer::start_div('assignsubmission_avgblindmarking submission ' . get_class($plugin));
                $oput .= \html_writer::tag('h4', $plugin->get_name());

                $pluginsubmission = new \assign_submission_plugin_submission($plugin,
                    $submission,
                    \assign_submission_plugin_submission::FULL,
                    $assign->get_course_module()->id,
                    '', []);
                $assignrenderer = $assign->get_renderer();

                if (!$plugin->is_empty($submission)) {
                    $oput .= $assignrenderer->render($pluginsubmission);
                }


                $oput .= \html_writer::end_div();

            }
        }

        $mform->addElement('html', $oput);




        $mform->addElement('html', \html_writer::start_div('assignsubmission_avgblindmarking grade'));
        $mform->addElement('html', \html_writer::tag('h4', get_string('grade')));

        // Taken from \assign::add_grade_form_elements
        if ($gradinginstance) {
            $gradingelement = $mform->addElement('grading',
                'advancedgrading',
                get_string('gradenoun') . ':',
                array('gradinginstance' => $gradinginstance));

            $gradingelement->freeze();
        } else {
            $grademenu = array(-1 => get_string("nograde")) + make_grades_menu($assign->get_instance()->grade);
            if (count($grademenu) > 1) {
                $gradingelement = $mform->addElement('select', 'grade', get_string('gradenoun') . ':', $grademenu);

                // The grade is already formatted with format_float so it needs to be converted back to an integer.
                if (!empty($grade->grade)) {
                    $grade->grade = (int)unformat_float($grade->grade);
                }
                $mform->setType('grade', PARAM_INT);
                $this->set_data($grade);
                $gradingelement->freeze();
            }
        }

        $mform->addElement('html', \html_writer::end_div());




        $oput = '';
        foreach ($assign->get_feedback_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $oput .= \html_writer::start_div('assignsubmission_avgblindmarking feedback ' . get_class($plugin));
                $oput .= \html_writer::tag('h4', $plugin->get_name());
                $oput .= $plugin->view($grade);
                $oput .= \html_writer::end_div();

            }
        }

        $mform->addElement('html', $oput);
    }
}