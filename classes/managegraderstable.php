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

use table_sql;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/tablelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

class managegraderstable extends table_sql {
    private managegraderscontroller $controller;
    private $now;

    public function __construct(managegraderscontroller $controller, $sortcolumn) {
        parent::__construct('managegraderstable_table');

        $this->now = time();
        $this->controller = $controller;

        $cols = ['learner', 'grader', 'actions'];
        $headers = [
            get_string('learner', 'assignsubmission_avgblindmarking'),
            get_string('grader', 'assignsubmission_avgblindmarking'),
            '',
        ];

        $this->define_columns($cols);
        $this->define_headers($headers);
        $this->collapsible(false);
        $this->sortable(true);
        $this->pageable(true);
        $this->sort_default_column = $sortcolumn;
        $this->is_downloadable(false);

        $learnerusernamesql = \core_user\fields::for_name()->get_sql('learner', true, 'learner')->selects;
        $graderusernamesql = \core_user\fields::for_name()->get_sql('grader', true, 'grader')->selects;

        $this->set_sql("graderalloc.id, concat(learner.firstname, learner.lastname) as learner, learner.id as learnerid, concat(grader.firstname, grader.lastname) as grader $learnerusernamesql $graderusernamesql",
                "{assignsubmission_graderalloc} graderalloc
                INNER JOIN {user} learner on learner.id = graderalloc.learneruserid
                INNER JOIN {user} grader on grader.id = graderalloc.graderuserid",
                'assignid = :assignid',
                ['assignid' => $controller->get_assign()->get_instance()->id]
        );
    }

    public function col_actions($row) {
        global $OUTPUT;

        $out = '';

        $icon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
        $out .= $OUTPUT->action_link($this->controller->getinternallink('editgraderalloc', ['learnerid' => $row->learnerid]), $icon);

        return $out;
    }

    public function col_learner($row) {
        $user = (object) [];
        username_load_fields_from_object($user, $row, 'learner');

        return fullname($user);
    }

    public function col_grader($row) {
        $user = (object) [];
        username_load_fields_from_object($user, $row, 'grader');

        return fullname($user);
    }
}
