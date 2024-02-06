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

use assign;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/lib/tablelib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

class avgblindsubmissiontable extends \table_sql {

    /** @var avgblindsubmissioncontroller */
    private $avgblindsubmissioncontroller;
    public function __construct(assign $assignment, $sortcolumn) {
        $this->avgblindsubmissioncontroller = new avgblindsubmissioncontroller($assignment);

        parent::__construct('manageavgblindsubmissions_table');

        $columns = ['grader', 'submissionmodified', 'actions'];
        $headers = [
            get_string('grader', 'assignsubmission_avgblindmarking'),
            get_string('submissionmodified', 'assignsubmission_avgblindmarking'),
            ''
        ];

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->collapsible(false);
        $this->sortable(true);
        $this->pageable(true);
        $this->is_downloadable(false);
        $this->sort_default_column = $sortcolumn;

        $this->useridfield = 'graderalloc.graderuserid';
        $usernamesql = \core_user\fields::for_name()->get_sql('grader')->selects;
        $this->set_sql("CONCAT(graderalloc.id, '_', subs.id), blindsub.graderallocid, blindsub.originalsubmissionid $usernamesql, subs.timecreated, subs.timemodified,",
                '{assign_submission} subs
                    INNER JOIN {assignsubmission_graderalloc} graderalloc on graderalloc.learneruserid = subs.userid and groupid = 0
                    INNER JOIN {user} grader on grader.id = graderalloc.graderuserid
                    LEFT JOIN {assignsubmission_blindsub} blindsub on blindsub.graderallocid = graderalloc.id and blindsub.originalsubmissionid = subs.id
                    LEFT JOIN {assign_submission} blindsubinstance on blindsubinstance.id = blindsub.submissionid',
                "subs.assignment = :assignmentid",
                ['assignmentid' => $assignment->get_instance()->id]);
    }

    public function col_actions($row) {
        global $OUTPUT;

        $url = $this->avgblindsubmissioncontroller->getinternallink('viewavgblindsubmission');
        $url->param('graderallocid', $row->graderallocid);
        $url->param('originalsubmissionid', $row->originalsubmissionid);

        $out = '';

        $icon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
        $out .= $OUTPUT->action_link($url, $icon);

        return $out;
    }
}
