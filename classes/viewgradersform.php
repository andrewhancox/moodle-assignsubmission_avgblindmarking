<?php

namespace assignsubmission_avgblindmarking;

use moodleform;

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->libdir . '/pear/HTML/QuickForm/input.php');

class viewgradersform extends moodleform {
    protected function definition() {
        global $DB;

        $mform = $this->_form;

        $assign = \assignsubmission_avgblindmarking\extendedassign::get_from_cmid(1278);

        $grade = $DB->get_record('assign_grades', ['id' => 40]);
        $gradinginstance = $assign->get_grading_instance_pub(22, $grade, true);

        $mform->addElement('header', 'gradeheader', get_string('gradenoun'));
        if ($gradinginstance) {
            $gradingelement = $mform->addElement('grading',
                'advancedgrading',
                get_string('gradenoun') . ':',
                array('gradinginstance' => $gradinginstance));

            $gradingelement->freeze();
        } else {
            // Use simple direct grading.
            if ($assign->get_instance()->grade > 0) {
                $name = get_string('gradeoutof', 'assign', $assign->get_instance()->grade);
                $strgradelocked = get_string('gradelocked', 'assign');
                $mform->addElement('static', 'gradedisabled', $name, $strgradelocked);
                $mform->addHelpButton('gradedisabled', 'gradeoutofhelp', 'assign');

            } else {
                $grademenu = array(-1 => get_string("nograde")) + make_grades_menu($assign->get_instance()->grade);
                if (count($grademenu) > 1) {
                    $gradingelement = $mform->addElement('select', 'grade', get_string('gradenoun') . ':', $grademenu);

                    // The grade is already formatted with format_float so it needs to be converted back to an integer.
                    if (!empty($data->grade)) {
                        $data->grade = (int)unformat_float($data->grade);
                    }
                    $mform->setType('grade', PARAM_INT);
                    $gradingelement->freeze();
                }
            }
        }


        $assign->add_plugin_grade_elements_pub($grade, $mform, new \stdClass(), 22);
    }
}