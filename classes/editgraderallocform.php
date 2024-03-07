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

use moodleform;

require_once($CFG->libdir . '/formslib.php');

class editgraderallocform extends moodleform {
    public function definition() {
        $mform = $this->_form;
        $params = $this->_customdata;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        if (empty($params['learnerid'])) {
            $learners = $params['learners'];
            $learners[0] = '';
            $mform->addElement('autocomplete',
                'learnerid',
                get_string('learner', 'assignsubmission_avgblindmarking'),
                $learners
            );
            $mform->setDefault('learnerid', 0);
            $mform->addRule('learnerid', null, 'required', null, 'client');
        } else {
            $mform->addElement('hidden', 'learnerid');
            $mform->setDefault('learnerid', $params['learnerid']);
        }
        $mform->setType('learnerid', PARAM_INT);

        $mform->addElement(
            'select',
            'markers',
            get_string('allocatedgraders', 'assignsubmission_avgblindmarking'),
            $params['markers'],
            ['multiple' => 'multiple']
        );

        $this->add_action_buttons();
    }
}
