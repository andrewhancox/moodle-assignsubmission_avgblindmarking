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

use html_writer;
use moodle_url;
use table_sql;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/tablelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

class blindgradestable extends table_sql {
    protected $filterparams;
    protected $assignment;

    public function __construct(basecontroller $controller, $sortcolumn, $learnerid) {
        parent::__construct('manageproduct_table');

        $this->controller = $controller;
        $this->assignment = $controller->get_assign();

        $wheres = ['ag.assignment = :assignmentid'];
        $params = ['assignmentid' => $this->assignment->get_instance()->id];

        $columns = ['grader', 'timecreated', 'grade', 'actions'];
        $headers = [
            get_string('grader', 'assignsubmission_avgblindmarking'),
            get_string('timecreated', 'assignsubmission_avgblindmarking'),
            get_string('blindgrade', 'assignsubmission_avgblindmarking'),
            get_string('actions'),
            '',
        ];

        if (!empty($learnerid)) {
            $wheres[] = 'lrnr.id = :learnerid';
            $params['learnerid'] = $learnerid;
        } else {
            array_unshift($headers, get_string('learner', 'assignsubmission_avgblindmarking'));
            array_unshift($columns, 'learner');
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->collapsible(false);
        $this->sortable(true);
        $this->pageable(true);
        $this->is_downloadable(false);
        $this->sort_default_column = $sortcolumn;

        $learnernamesql = \core_user\fields::for_name()->get_sql('lrnr', true, 'lrnr')->selects;
        $gradernamesql = \core_user\fields::for_name()->get_sql('grdr', true, 'grdr')->selects;

        $this->set_sql("ag.id $learnernamesql $gradernamesql, ag.timecreated, ag.grader, bg.userid as learner, ag.grade",
            '{assign_grades} ag
                    INNER JOIN {assignsubmission_ass_grade} bg on bg.assigngradeid = ag.id
                    INNER JOIN {user} lrnr on lrnr.id = bg.userid
                    INNER JOIN {user} grdr on grdr.id = ag.grader',
            implode(' AND ', $wheres),
            $params
        );
    }

    function col_learner($row) {
        global $COURSE;

        $user = (object)[];
        username_load_fields_from_object($user, $row, 'lrnr');

        $name = fullname($user);

        $profileurl = new moodle_url('/user/view.php',
            ['id' => $row->learner, 'course' => $COURSE->id]);

        return html_writer::link($profileurl, $name);
    }

    function col_grader($row) {
        global $COURSE;

        $user = (object)[];
        username_load_fields_from_object($user, $row, 'grdr');

        $name = fullname($user);

        $profileurl = new moodle_url('/user/view.php',
            ['id' => $row->grader, 'course' => $COURSE->id]);

        return html_writer::link($profileurl, $name);
    }

    public function col_timecreated($row) {
        if (empty($row->timecreated)) {
            return '';
        } else {
            return userdate($row->timecreated);
        }
    }

    public function col_grade($row) {
        return $this->assignment->display_grade($row->grade, false);
    }

    public function col_actions($row) {
        global $OUTPUT;

        $out = '';

        $icon = $OUTPUT->pix_icon('t/viewdetails', get_string('viewblindgrade', 'assignsubmission_avgblindmarking'));
        $out .= $OUTPUT->action_link($this->controller->getinternallink('viewblindgrade', ['assigngradeid' => $row->id]), $icon);

        return $out;
    }
}
