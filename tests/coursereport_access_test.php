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
 * Access-control tests for the course-level detection report detail view.
 *
 * Regression coverage for MOO-12 finding #2: a viewer with
 * local/agentdetect:viewreports on a course could open detection details
 * for any user ID, regardless of whether the target was enrolled in the
 * course or visible to the viewer under separate-groups mode.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_agentdetect;

/**
 * Tests for the course-report student detail visibility gate.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class coursereport_access_test extends \advanced_testcase {
    /**
     * Load the procedural helpers from coursereport.php exactly once.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        global $CFG;
        require_once($CFG->dirroot . '/local/agentdetect/coursereport.php');
    }

    /**
     * A teacher with viewreports may open the detail page for an enrolled
     * student. Asserts the visibility check does not reject the happy path.
     */
    public function test_enrolled_user_passes_visibility_check(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        // The function emits HTML; capture and discard. We only care that it
        // returns without throwing — i.e., the visibility check passed.
        ob_start();
        try {
            local_agentdetect_display_student_signals($course->id, $student->id, $context);
        } finally {
            ob_end_clean();
        }
        $this->assertTrue(true, 'Enrolled student must be viewable by teacher.');
    }

    /**
     * A teacher with viewreports must NOT be able to open the detail page for
     * a user who is not enrolled in the course. Before MOO-12 the userid was
     * trusted and the user record loaded without any participant check.
     */
    public function test_non_enrolled_user_blocked(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $outsider = $this->getDataGenerator()->create_user();

        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        local_agentdetect_display_student_signals($course->id, $outsider->id, $context);
    }

    /**
     * Under separate-groups mode, a teacher restricted to one group must NOT
     * be able to open the detail page for a student in a different group.
     * Skipped on Moodle stacks without group-management capability surface.
     */
    public function test_separate_groups_blocks_cross_group_access(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course([
            'groupmode' => SEPARATEGROUPS,
            'groupmodeforce' => 1,
        ]);
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $studenta = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $studentb = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        groups_add_member($groupa, $teacher);
        groups_add_member($groupa, $studenta);
        groups_add_member($groupb, $studentb);

        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        local_agentdetect_display_student_signals($course->id, $studentb->id, $context);
    }
}
