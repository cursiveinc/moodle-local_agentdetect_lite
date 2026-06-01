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
 * Unit tests for the get_user_flags external function.
 *
 * Regression coverage for MOO-12 finding #2: the AJAX flag lookup must not
 * surface flag data for users the caller cannot see in the supplied context,
 * and must not leak NULL-context (system-level) records to course-scoped
 * callers.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_agentdetect\external\get_user_flags
 */

namespace local_agentdetect;

use local_agentdetect\external\get_user_flags;

/**
 * Tests for the get_user_flags external function.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_agentdetect\external\get_user_flags
 */
final class get_user_flags_test extends \advanced_testcase {
    /**
     * Test that user IDs not enrolled in the course are filtered out of the
     * AJAX flag lookup, even if the caller passes them explicitly. Before
     * MOO-12 the function trusted any caller-supplied userid and queried flag
     * data for it across the context tree.
     *
     * @covers \local_agentdetect\external\get_user_flags::execute
     */
    public function test_filters_out_non_enrolled_userids(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $enrolled = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $outsider = $this->getDataGenerator()->create_user();

        // Both users have flag rows tagged to this course context.
        $manager = new signal_manager();
        $manager->store_signal(
            $enrolled->id,
            $coursecontext->id,
            'sess-a',
            'combined',
            ['combinedscore' => 85, 'verdict' => 'HIGH_CONFIDENCE_AGENT']
        );
        $manager->store_signal(
            $outsider->id,
            $coursecontext->id,
            'sess-b',
            'combined',
            ['combinedscore' => 85, 'verdict' => 'HIGH_CONFIDENCE_AGENT']
        );

        $this->setUser($teacher);

        $result = get_user_flags::execute(
            [(int) $enrolled->id, (int) $outsider->id],
            $coursecontext->id
        );

        // The execute() return shape casts userid to int; assert against ints
        // so PHPUnit's strict assertContains matches.
        $returneduserids = array_column($result, 'userid');
        $this->assertContains((int) $enrolled->id, $returneduserids);
        $this->assertNotContains(
            (int) $outsider->id,
            $returneduserids,
            'AJAX flag lookup must not return rows for users not enrolled in the course.'
        );
    }

    /**
     * Test that NULL-context (system-level) records do not leak through a
     * course-scoped lookup. MOO-12 finding #2: before the fix, every course
     * context with viewreports could read global detection state via the
     * OR f.contextid IS NULL fallthrough.
     *
     * @covers \local_agentdetect\external\get_user_flags::execute
     */
    public function test_null_context_records_hidden_from_course_callers(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        // Student has only a NULL-context flag (e.g., from a beacon with
        // contextid=0 outside of any course).
        $manager = new signal_manager();
        $manager->store_signal(
            $student->id,
            0,
            'null-ctx-session',
            'combined',
            ['combinedscore' => 85, 'verdict' => 'HIGH_CONFIDENCE_AGENT']
        );

        $this->setUser($teacher);

        $result = get_user_flags::execute([(int) $student->id], $coursecontext->id);

        // The student has a flag, but it's NULL-context — not visible to a
        // course-scoped caller. Expect either no entry or only the
        // likely_human pseudo-flag (which itself should be absent because no
        // signal exists in this course's context tree).
        $flagged = array_filter($result, static fn($r) => in_array(
            $r['flagtype'],
            ['agent_suspected', 'agent_confirmed', 'low_suspicion'],
            true
        ));
        $this->assertEmpty(
            $flagged,
            'Course-scoped AJAX caller must not see flags whose contextid IS NULL.'
        );
    }

    /**
     * Test that empty input returns an empty list (no DB query, no error).
     *
     * @covers \local_agentdetect\external\get_user_flags::execute
     */
    public function test_empty_userids_returns_empty(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');

        $this->setUser($teacher);

        $result = get_user_flags::execute([], $coursecontext->id);
        $this->assertSame([], $result);
    }
}
