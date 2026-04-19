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
 * Library functions for local_agentdetect.
 *
 * Footer JS injection is registered via db/hooks.php using the
 * before_footer_html_generation hook (Moodle 4.4+). The legacy
 * local_agentdetect_before_footer() callback has been removed to
 * avoid the Moodle 5.0 debugging notice about process_legacy_callbacks().
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Format a context ID as a human-readable link to the course / quiz it belongs to.
 *
 * Used by the admin Signals and Flags tables to help teachers and admins jump
 * from a flagged session straight to the quiz (or course) where the detection
 * happened. Handles deleted contexts gracefully.
 *
 * @param int|null $contextid The stored context ID, may be null for legacy rows.
 * @return string HTML fragment — a link, a plain label, or a dash.
 */
function local_agentdetect_format_context_link(?int $contextid): string {
    if (empty($contextid)) {
        return html_writer::tag('span', '-', ['class' => 'text-muted']);
    }

    $context = context::instance_by_id($contextid, IGNORE_MISSING);
    if (!$context) {
        return html_writer::tag('span', '-', ['class' => 'text-muted', 'title' => 'Context ' . $contextid . ' no longer exists']);
    }

    if ($context instanceof context_module) {
        $cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return html_writer::tag('span', '-', ['class' => 'text-muted']);
        }
        $label = format_string($cm->name);
        $url = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
        $icon = html_writer::tag('span', ucfirst($cm->modname), ['class' => 'badge badge-light mr-1']);
        return $icon . html_writer::link($url, $label, ['title' => $cm->modname . ': ' . $label]);
    }

    if ($context instanceof context_course) {
        $course = get_course($context->instanceid);
        $url = new moodle_url('/course/view.php', ['id' => $course->id]);
        return html_writer::link($url, format_string($course->shortname), ['title' => format_string($course->fullname)]);
    }

    if ($context instanceof context_system) {
        return html_writer::tag('span', get_string('site'), ['class' => 'text-muted']);
    }

    return html_writer::tag('span', $context->get_context_name(false), ['class' => 'text-muted']);
}

/**
 * Extend course navigation with agent detection report link.
 *
 * @param navigation_node $navigation The navigation node.
 * @param stdClass $course The course object.
 * @param context $context The course context.
 */
function local_agentdetect_extend_navigation_course(
    navigation_node $navigation,
    stdClass $course,
    context $context
): void {
    if (!has_capability('local/agentdetect:viewreports', $context)) {
        return;
    }

    $url = new moodle_url('/local/agentdetect/coursereport.php', ['courseid' => $course->id]);
    $navigation->add(
        get_string('coursereport', 'local_agentdetect'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'agentdetect_coursereport',
        new pix_icon('i/report', '')
    );
}
