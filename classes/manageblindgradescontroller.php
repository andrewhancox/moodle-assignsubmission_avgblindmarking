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

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

defined('MOODLE_INTERNAL') || die();

class manageblindgradescontroller extends manageblindgradescontrollerbase {
    public function summary() {
        global $OUTPUT;

        return $OUTPUT->single_button($this->getinternallink('manageblindgrades'),
                get_string('manageblindgrades', 'assignsubmission_avgblindmarking'), 'get');
    }

    public function manageblindgrades() {
        return parent::renderblindgradestable();
    }

    public function viewblindgrade() {
        return parent::renderblindgrade();
    }
}
