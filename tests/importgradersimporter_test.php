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

class importgradersimporter_test extends advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;

    public function test_get_next_marker() {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'student1@example.com']);
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'student2@example.com']);
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'student3@example.com']);
        $student4 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'student4@example.com']);
        $student5 = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'student5@example.com']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher', ['email' => 'teacher1@example.com']);
        $teacher2 = $this->getDataGenerator()->create_and_enrol($course, 'teacher', ['email' => 'teacher2@example.com']);
        $teacher3 = $this->getDataGenerator()->create_and_enrol($course, 'teacher', ['email' => 'teacher3@example.com']);

        $notateacher = $this->getDataGenerator()->create_and_enrol($course, 'student', ['email' => 'notateacher@example.com']);

        $assign = $this->create_instance($course, [
            'assignsubmission_onlinetext_enabled' => true,
        ]);

        $importer = new importgradersimporter($assign);
        $filecontent = file_get_contents(__DIR__ . '/fixtures/test.csv');
        $importer->loaddata($filecontent);

        $this->assertEquals(5, $importer->gradercount);
        $this->assertCount(2, $importer->errors);

        $this->assertEquals($teacher->id, eventhandlers::get_next_marker($student->id, $assign));
        $this->assertEquals($teacher2->id, eventhandlers::get_next_marker($student2->id, $assign));

        $assigngradeid = $DB->insert_record('assign_grades', (object)[
            'assignment' => $assign->get_instance()->id,
            'userid' => $student->id,
            'grader' => $teacher->id,
        ]);
        $DB->insert_record('assignsubmission_ass_grade', (object)[
            'assigngradeid' => $assigngradeid,
            'userid' => $student->id,
            'attemptnumber' => 0
        ]);
        $DB->insert_record('assign_submission', (object)[
            'assignment' => $assign->get_instance()->id,
            'userid' => $student->id,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
        $this->assertEquals($teacher2->id, eventhandlers::get_next_marker($student->id, $assign));
        $this->assertEquals($teacher2->id, eventhandlers::get_next_marker($student2->id, $assign));
    }
}
