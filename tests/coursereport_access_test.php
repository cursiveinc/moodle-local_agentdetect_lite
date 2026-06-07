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
 * local/agentdetect:viewreports on a course could open detection details for
 * any user ID, regardless of whether the target was enrolled in the course or
 * visible to the viewer under separate-groups mode. Exercises the
 * local_agentdetect_user_visible_in_course() helper that gates the detail
 * view in coursereport.php.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */

namespace local_agentdetect;

/**
 * Tests for the course-report student detail visibility gate.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class coursereport_access_test extends \advanced_testcase {
    /**
     * Pull in lib.php so the procedural helper under test is defined.
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        global $CFG;
        require_once($CFG->dirroot . '/local/agentdetect/lib.php');
    }

    /**
     * Enrolled user passes the visibility check — happy path. Confirms the
     * helper does not falsely reject access for a legitimately viewable user.
     */
    public function test_enrolled_user_is_visible(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->setUser($teacher);

        $this->assertTrue(local_agentdetect_user_visible_in_course(
            $course->id,
            (int) $student->id,
            $context
        ));
    }

    /**
     * Unenrolled user fails the visibility check. Before MOO-12 the userid was
     * trusted and the user record loaded without any participant check.
     */
    public function test_non_enrolled_user_is_not_visible(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $outsider = $this->getDataGenerator()->create_user();

        $this->setUser($teacher);

        $this->assertFalse(local_agentdetect_user_visible_in_course(
            $course->id,
            (int) $outsider->id,
            $context
        ));
    }

    /**
     * Under separate-groups mode, a teacher restricted to one group cannot see
     * a student in a different group. Confirms the helper honours group
     * boundaries, not just enrolment.
     */
    public function test_separate_groups_block_cross_group_access(): void {
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

        $this->assertTrue(local_agentdetect_user_visible_in_course(
            $course->id,
            (int) $studenta->id,
            $context
        ));
        $this->assertFalse(local_agentdetect_user_visible_in_course(
            $course->id,
            (int) $studentb->id,
            $context
        ));
    }
}
