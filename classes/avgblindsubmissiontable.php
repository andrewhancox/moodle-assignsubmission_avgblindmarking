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

        $columns = ['title', 'actions'];
        $headers = [get_string('avgblindsubmissiontitle', 'assignsubmission_avgblindmarking'), ''];

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->collapsible(false);
        $this->sortable(true);
        $this->pageable(true);
        $this->is_downloadable(false);
        $this->sort_default_column = $sortcolumn;

        $this->set_sql("asex.id, asex.title",
                '{assignsubmission_avgbm_sub} asex
                                            inner join {assign_submission} subs on asex.submissionid = subs.id',
                "subs.assignment = :assignmentid",
                ['assignmentid' => $assignment->get_instance()->id]);
    }

    public function col_actions($row) {
        global $OUTPUT;

        $url = $this->avgblindsubmissioncontroller->getinternallink('addavgblindsubmission');
        $deleteurl = $this->avgblindsubmissioncontroller->getinternallink('deleteavgblindsubmission');

        $url->param('avgblindsubmissionid', $row->id);
        $deleteurl->param('avgblindsubmissionid', $row->id);

        $out = '';

        $icon = $OUTPUT->pix_icon('t/edit', get_string('edit'));
        $out .= $OUTPUT->action_link($url, $icon);

        $icon = $OUTPUT->pix_icon('t/delete', get_string('delete'));
        $out .= $OUTPUT->action_link($deleteurl, $icon);

        return $out;
    }
}
