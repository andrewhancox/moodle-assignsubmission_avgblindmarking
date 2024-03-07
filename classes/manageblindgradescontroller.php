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

class manageblindgradescontroller extends basecontroller {
    public function summary() {
        global $OUTPUT;

        return $OUTPUT->single_button($this->getinternallink('manageblindgrades'),
                get_string('manageblindgrades', 'assignsubmission_avgblindmarking'), 'get');
    }

    public function manageblindgrades() {

        $sort = optional_param('tsort', 'lastname, firstname', PARAM_ALPHA);
        $table = new blindgradestable($this, $sort);
        $table->define_baseurl($this->getinternallink('managecomparisoncomments'));

        $o = $this->getheader(get_string('viewblindgrades', 'assignsubmission_avgblindmarking'));

        ob_start();
        $table->out(25, false);
        $o .= ob_get_contents();
        ob_end_clean();

        $o .= $this->getfooter();

        return $o;
    }

    public function viewblindgrade() {
        global $DB, $PAGE;

        $oput = $this->getheader(get_string('viewblindgrades', 'assignsubmission_avgblindmarking'));

        $assigngradeid = required_param('assigngradeid', PARAM_INT);
        $grade = $DB->get_record('assign_grades', ['id' => $assigngradeid]);
        $learnerid = $DB->get_field('assignsubmission_ass_grade', 'userid', ['assigngradeid' => $assigngradeid]);
        $assign = extendedassign::get_from_instanceid($grade->assignment);
        $gradinginstance = $assign->get_grading_instance_pub($learnerid, $grade, true);
        $submission = $assign->get_user_submission($learnerid, false);

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

        $oput .= \html_writer::start_div('assignsubmission_avgblindmarking grade');
        $oput .= \html_writer::tag('h4', get_string('grade', 'grades'));

        if ($gradinginstance) {
            $oput .= $gradinginstance->get_controller()->get_renderer($PAGE)->display_instance($gradinginstance, 0, false);
        } else {
            $oput .= (int)unformat_float($grade->grade);
        }

        $oput .= \html_writer::end_div();

        foreach ($assign->get_feedback_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $oput .= \html_writer::start_div('assignsubmission_avgblindmarking feedback ' . get_class($plugin));
                $oput .= \html_writer::tag('h4', $plugin->get_name());
                $oput .= $plugin->view($grade);
                $oput .= \html_writer::end_div();

            }
        }

        $oput .= $this->getfooter();

        return $oput;
    }
}
