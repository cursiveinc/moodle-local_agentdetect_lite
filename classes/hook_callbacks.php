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
 * Hook callbacks for local_agentdetect.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_agentdetect;

/**
 * Hook callbacks container.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inject detection JavaScript before the page footer is rendered.
     *
     * Replaces the legacy local_agentdetect_before_footer() callback which
     * triggered the debugging notice on Moodle 5.0 (get_plugins_with_function
     * now routes legacy callbacks through process_legacy_callbacks()).
     *
     * @param \core\hook\output\before_footer_html_generation $hook The hook instance.
     */
    public static function before_footer_html_generation(
        \core\hook\output\before_footer_html_generation $hook
    ): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (get_config('local_agentdetect', 'enabled')) {
            self::load_detector();
        }

        self::load_quiz_badges();
    }

    /**
     * Load the detection engine on quiz pages only.
     */
    protected static function load_detector(): void {
        global $PAGE, $USER;

        if (strpos($PAGE->pagetype, 'mod-quiz-') !== 0) {
            return;
        }

        $context = $PAGE->context;

        $config = [
            'enabled' => true,
            'reportInterval' => (int) get_config('local_agentdetect', 'reportinterval') ?: 30000,
            'minReportScore' => (int) get_config('local_agentdetect', 'minreportscore') ?: 10,
            'contextId' => $context->id,
            'userId' => (int) $USER->id,
            'sessionKey' => sesskey(),
            'debug' => (bool) get_config('local_agentdetect', 'debug'),
        ];

        $PAGE->requires->js_call_amd('local_agentdetect/detector', 'init', [$config]);
    }

    /**
     * Load quiz badge icons on quiz report/review pages for teachers.
     */
    protected static function load_quiz_badges(): void {
        global $PAGE;

        $pagetype = $PAGE->pagetype;
        if ($pagetype === 'mod-quiz-report' || $pagetype === 'mod-quiz-report-overview') {
            $mode = 'overview';
        } else if ($pagetype === 'mod-quiz-review') {
            $mode = 'review';
        } else {
            return;
        }

        $coursecontext = $PAGE->context->get_course_context(false);
        if (!$coursecontext) {
            return;
        }

        if (!has_capability('local/agentdetect:viewreports', $coursecontext)) {
            return;
        }

        $courseid = $coursecontext->instanceid;
        $reporturl = new \moodle_url('/local/agentdetect/coursereport.php', ['courseid' => $courseid]);

        $config = [
            'mode' => $mode,
            'courseid' => $courseid,
            'contextid' => $coursecontext->id,
            'reportUrl' => $reporturl->out(false),
        ];

        $PAGE->requires->js_call_amd('local_agentdetect/quiz_badge', 'init', [$config]);
    }
}
