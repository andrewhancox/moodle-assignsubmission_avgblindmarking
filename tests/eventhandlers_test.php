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

use advanced_testcase;
use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

class eventhandlers_test extends advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    public function test_get_next_marker() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'student1@example.com']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher', ['email' => 'teacher1@example.com']);
        $teacher2 = $this->getDataGenerator()->create_and_enrol($course, 'teacher', ['email' => 'teacher2@example.com']);

        $assign = $this->create_instance($course, [
            'assignsubmission_onlinetext_enabled' => true,
            'blindmarking' => 1
        ]);

        $graderalloc = new graderalloc();
        $graderalloc->set('assignid', $assign->get_instance()->id);
        $graderalloc->set('learneruserid', $student->id);
        $graderalloc->set('graderuserid', $teacher->id);
        $graderalloc->save();

        $graderalloc = new graderalloc();
        $graderalloc->set('assignid', $assign->get_instance()->id);
        $graderalloc->set('learneruserid', $student->id);
        $graderalloc->set('graderuserid', $teacher2->id);
        $graderalloc->save();

        $this->assertFalse($DB->record_exists('assign_user_flags', ['userid' => $student->id, 'assignment' => $assign->get_instance()->id]));

        $this->add_submission($student, $assign);
        $this->submit_for_grading($student, $assign);

        $assign_user_flags = $DB->get_record('assign_user_flags', ['userid' => $student->id, 'assignment' => $assign->get_instance()->id]);
        $this->assertEquals($teacher->id, $assign_user_flags->allocatedmarker);
        $this->assertEquals(ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED, $assign_user_flags->workflowstate);

        $this->setUser($teacher);
        $data = new \stdClass();
        $data->grade = '50.0';
        $assign->testable_apply_grade_to_user($data, $student->id, 0);
        $assign->testable_process_set_batch_marking_workflow_state($student->id, ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW);
        eventhandlers::finalisedisconnect();

        $assign_user_flags = $DB->get_record('assign_user_flags', ['userid' => $student->id, 'assignment' => $assign->get_instance()->id]);
        $this->assertEquals($teacher2->id, $assign_user_flags->allocatedmarker);
        $this->assertEquals(ASSIGN_MARKING_WORKFLOW_STATE_NOTMARKED, $assign_user_flags->workflowstate);

        $this->setUser($teacher2);
        $data = new \stdClass();
        $data->grade = '100.0';
        $assign->testable_apply_grade_to_user($data, $student->id, 0);
        $assign->testable_process_set_batch_marking_workflow_state($student->id, ASSIGN_MARKING_WORKFLOW_STATE_READYFORREVIEW);
        eventhandlers::finalisedisconnect();

        $assign_user_flags = $DB->get_record('assign_user_flags', ['userid' => $student->id, 'assignment' => $assign->get_instance()->id]);
        $this->assertEquals(0, $assign_user_flags->allocatedmarker);
        $this->assertEquals(ASSIGN_MARKING_WORKFLOW_STATE_READYFORRELEASE, $assign_user_flags->workflowstate);

        $this->assertEquals(75, $assign->get_user_grade($student->id, false)->grade);

        $table = new blindgradestable(new manageblindgradescontroller($assign), 'timecreated', 0);
        $table->define_baseurl(new \moodle_url(''));
        $table->setup();
        $table->query_db(5000, false);

        $this->assertCount(2, $table->rawdata);
        $first = array_shift($table->rawdata);
        $second = array_shift($table->rawdata);

        $this->assertEquals(50, $first->grade);
        $this->assertEquals(100, $second->grade);
        $this->assertStringContainsString('Participant', $table->col_learner($first));
        $this->assertStringNotContainsString($student->firstname, $table->col_learner($first));

        $this->setAdminUser();
        $assign->reveal_identities();

        $table = new blindgradestable(new manageblindgradescontroller($assign), 'timecreated', 0);
        $table->define_baseurl(new \moodle_url(''));
        $table->setup();
        $table->query_db(5000, false);

        $this->assertCount(2, $table->rawdata);
        $first = array_shift($table->rawdata);
        $second = array_shift($table->rawdata);

        $this->assertEquals(50, $first->grade);
        $this->assertEquals(100, $second->grade);
        $this->assertStringNotContainsString('Participant', $table->col_learner($first));
        $this->assertStringContainsString($student->firstname, $table->col_learner($first));
    }
}
