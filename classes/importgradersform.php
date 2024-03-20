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
use html_writer;
use moodleform;

class importgradersform extends moodleform {
    function definition() {
        $mform =& $this->_form;

        $mform->addElement('html',
            html_writer::tag('div',
                html_writer::link(
                    new \moodle_url('/mod/assign/submission/avgblindmarking/resources/example.csv'),
                    get_string('examplecsvfile', 'assignsubmission_avgblindmarking'),
                    ['target' => '_blank', 'download' => 'download']
                ),
                array('class' => 'addpage')
            )
        );

        $mform->addElement('filepicker', 'csvfile', get_string('csvfile', 'assignsubmission_avgblindmarking'), null, ['accepted_types' => ['.csv']]);
        $mform->addRule('csvfile', null, 'required', null, 'client');

        $encodings = core_text::get_encodings();
        $mform->addElement('select', 'encoding', get_string('encoding', 'grades'), $encodings);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}
