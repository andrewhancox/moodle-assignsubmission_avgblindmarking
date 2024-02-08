<?php

namespace assignsubmission_avgblindmarking;

use assign;
use MoodleQuickForm;
use stdClass;

class extendedassign extends assign {
    public static function get_from_instanceid(int $instanceid): extendedassign {
        list($course, $cm) = get_course_and_cm_from_instance($instanceid, 'assign');
        return new self($cm->context, $cm, $course);
    }

    /**
     * Get an instance of a grading form if advanced grading is enabled.
     * This is specific to the assignment, marker and student.
     *
     * @param int $userid - The student userid
     * @param stdClass|false $grade - The grade record
     * @param bool $gradingdisabled
     * @return mixed gradingform_instance|null $gradinginstance
     */
    public function get_grading_instance_pub($userid, $grade, $gradingdisabled) {
        return $this->get_grading_instance($userid, $grade, $gradingdisabled);
    }
}