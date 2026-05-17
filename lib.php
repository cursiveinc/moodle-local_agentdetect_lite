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

    // Cache resolved HTML per contextid for the lifetime of the request. The
    // admin report table calls this once per signal row, and a single user
    // typically has many signals sharing the same quiz context — without this
    // cache each row re-runs context::instance_by_id + get_coursemodule_from_id
    // + get_course, which is a classic N+1 pattern on multi-hundred-row tables.
    static $cache = [];
    if (array_key_exists($contextid, $cache)) {
        return $cache[$contextid];
    }

    $build = static function () use ($contextid): string {
        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            return html_writer::tag('span', '-', ['class' => 'text-muted']);
        }

        $truncate = static function (string $text, int $max): string {
            return core_text::strlen($text) > $max
                ? core_text::substr($text, 0, $max - 3) . '...'
                : $text;
        };

        // Note on escaping: html_writer::link() does NOT escape link text but
        // DOES escape attribute values (via html_writer::attributes(), which
        // calls s() on each). So for body text we call s() ourselves, and for
        // attributes we pass raw strings. We deliberately avoid format_string()
        // here because in Moodle 5.0 it can double-encode ampersands when
        // certain filters are active — producing AI&amp;amp;201 from AI&201.
        // Course/module names are short identifiers that don't need multilang
        // filter processing for these labels.
        if ($context instanceof context_module) {
            $cm = get_coursemodule_from_id(null, $context->instanceid, 0, false, IGNORE_MISSING);
            if (!$cm) {
                return html_writer::tag('span', '-', ['class' => 'text-muted']);
            }
            $course = get_course($cm->course);
            $shortshort = $truncate($course->shortname, 10);
            $shortmod = $truncate($cm->name, 25);
            $url = new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]);
            return html_writer::link(
                $url,
                s($shortshort . ' / ' . $shortmod),
                ['title' => $course->shortname . ' / ' . $cm->name]
            );
        }

        if ($context instanceof context_course) {
            $course = get_course($context->instanceid);
            $label = $truncate($course->shortname, 10);
            $url = new moodle_url('/course/view.php', ['id' => $course->id]);
            return html_writer::link(
                $url,
                s($label),
                ['title' => $course->fullname]
            );
        }

        if ($context instanceof context_system) {
            return html_writer::tag('span', get_string('site'), ['class' => 'text-muted']);
        }

        return html_writer::tag('span', $context->get_context_name(false), ['class' => 'text-muted']);
    };

    return $cache[$contextid] = $build();
}

/**
 * Format a flag type as a Bootstrap badge with translated label.
 *
 * Shared between the admin report and the course-level report so both views
 * render flag types consistently, translated via the 'flagtype:*' lang keys.
 *
 * @param string $flagtype The flag type enum (agent_suspected, low_suspicion, ...).
 * @return string HTML badge.
 */
function local_agentdetect_format_flag_badge(string $flagtype): string {
    $stringkey = 'flagtype:' . $flagtype;
    $manager = get_string_manager();
    $label = $manager->string_exists($stringkey, 'local_agentdetect')
        ? get_string($stringkey, 'local_agentdetect')
        : $flagtype;
    if ($flagtype === \local_agentdetect\signal_manager::FLAG_SUSPECTED
            || $flagtype === \local_agentdetect\signal_manager::FLAG_CONFIRMED) {
        return html_writer::tag('span', $label, ['class' => 'badge badge-danger']);
    } else if ($flagtype === \local_agentdetect\signal_manager::FLAG_LOW_SUSPICION) {
        return html_writer::tag('span', $label, ['class' => 'badge badge-warning']);
    }
    return html_writer::tag('span', $label, ['class' => 'badge badge-secondary']);
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
