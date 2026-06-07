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
 * Course-level agent detection report for teachers.
 *
 * Shows flagged students in the course (summary view) or
 * per-session signal summaries for a specific student (detail view).
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

$course = get_course($courseid);
require_login($course);

$context = context_course::instance($courseid);
require_capability('local/agentdetect:viewreports', $context);

$pageurl = new moodle_url('/local/agentdetect/coursereport.php', ['courseid' => $courseid]);
if ($userid) {
    $pageurl->param('userid', $userid);
}

$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('coursereport:title', 'local_agentdetect'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursereport:title', 'local_agentdetect'));

if ($userid) {
    // Student detail view — per-session signal summaries.
    local_agentdetect_display_student_signals($courseid, $userid, $context);
} else {
    // Summary view — flagged students in this course.
    local_agentdetect_display_flagged_students($courseid, $context);
}

echo $OUTPUT->footer();

/**
 * Display flagged students in the course.
 *
 * @param int $courseid The course ID.
 * @param context_course $context The course context.
 */
function local_agentdetect_display_flagged_students(int $courseid, context_course $context): void {
    global $DB;

    // Get all context IDs for this course and its child modules.
    $contextids = local_agentdetect_get_course_context_ids($context);

    if (empty($contextids)) {
        echo html_writer::div(
            get_string('coursereport:noflags', 'local_agentdetect'),
            'alert alert-info'
        );
        return;
    }

    // Get enrolled users who have flags in these contexts.
    [$ctxinsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');

    $sql = "SELECT f.userid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename, u.email,
                   MAX(f.maxscore) AS maxscore,
                   MAX(f.detectioncount) AS detectioncount,
                   MAX(f.timemodified) AS lastdetected,
                   f.flagtype
              FROM {local_agentdetect_flags} f
              JOIN {user} u ON u.id = f.userid
             WHERE (f.contextid {$ctxinsql} OR f.contextid IS NULL)
               AND f.flagtype != 'cleared'
          GROUP BY f.userid, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                   u.middlename, u.alternatename, u.email, f.flagtype
          ORDER BY maxscore DESC, lastdetected DESC";

    $flags = $DB->get_records_sql($sql, $ctxparams);

    if (empty($flags)) {
        echo html_writer::div(
            get_string('coursereport:noflags', 'local_agentdetect'),
            'alert alert-info'
        );
        return;
    }

    // Filter to only enrolled users.
    $enrolledusers = get_enrolled_users($context, '', 0, 'u.id');
    $enrolledids = array_keys($enrolledusers);

    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('coursereport:flagtype', 'local_agentdetect'),
        get_string('coursereport:maxscore', 'local_agentdetect'),
        get_string('coursereport:detectioncount', 'local_agentdetect'),
        get_string('coursereport:lastdetected', 'local_agentdetect'),
        '',
    ];
    $table->attributes['class'] = 'table table-striped table-sm';

    $hasrows = false;
    foreach ($flags as $flag) {
        if (!in_array((int) $flag->userid, $enrolledids)) {
            continue;
        }
        $hasrows = true;

        $userlink = html_writer::link(
            new moodle_url('/user/view.php', ['id' => $flag->userid, 'course' => $courseid]),
            fullname($flag)
        );

        $flagbadge = local_agentdetect_format_flag_badge($flag->flagtype);

        $detailurl = new moodle_url('/local/agentdetect/coursereport.php', [
            'courseid' => $courseid,
            'userid' => $flag->userid,
        ]);
        $actions = html_writer::link(
            $detailurl,
            get_string('coursereport:viewdetails', 'local_agentdetect'),
            ['class' => 'btn btn-sm btn-outline-primary']
        );

        $table->data[] = [
            $userlink,
            $flagbadge,
            $flag->maxscore,
            $flag->detectioncount,
            userdate($flag->lastdetected, '%Y-%m-%d %H:%M'),
            $actions,
        ];
    }

    if ($hasrows) {
        echo html_writer::table($table);
    } else {
        echo html_writer::div(
            get_string('coursereport:noflags', 'local_agentdetect'),
            'alert alert-info'
        );
    }
}

/**
 * Display signal sessions for a specific student.
 *
 * @param int $courseid The course ID.
 * @param int $userid The user ID.
 * @param context_course $context The course context.
 */
function local_agentdetect_display_student_signals(int $courseid, int $userid, context_course $context): void {
    global $DB, $OUTPUT;

    // MOO-12 finding #2: enforce target-user visibility before loading any
    // detection data. local/agentdetect:viewreports gates entry to this
    // function but does NOT grant access to arbitrary user IDs — the target
    // must (a) be enrolled in the course and (b) be visible to the viewer
    // under any active group-mode restriction. The check lives in lib.php so
    // unit tests can exercise it without loading this page script.
    if (!local_agentdetect_user_visible_in_course($courseid, $userid, $context)) {
        throw new \moodle_exception('error:cannotviewuser', 'local_agentdetect');
    }

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);

    // Breadcrumb back to summary.
    $summaryurl = new moodle_url('/local/agentdetect/coursereport.php', ['courseid' => $courseid]);
    echo html_writer::link(
        $summaryurl,
        '&laquo; ' . get_string('coursereport:flaggedstudents', 'local_agentdetect'),
        ['class' => 'mb-3 d-block']
    );

    echo $OUTPUT->heading(get_string('coursereport:studentsignals', 'local_agentdetect', fullname($user)), 3);

    // Caveat notice.
    echo html_writer::div(
        get_string('coursereport:caveat', 'local_agentdetect'),
        'alert alert-warning'
    );

    // Get all context IDs for this course.
    $contextids = local_agentdetect_get_course_context_ids($context);

    if (empty($contextids)) {
        echo html_writer::div(
            get_string('coursereport:nosignals', 'local_agentdetect'),
            'alert alert-info'
        );
        return;
    }

    [$ctxinsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
    $ctxparams['userid'] = $userid;

    // Get session summaries — grouped by sessionid, sorted by highest score first.
    // Verdict is derived per-session below from the highest-scoring signal record,
    // not via SQL MAX(verdict) which would return the alphabetic max of the enum
    // strings (e.g. LIKELY_HUMAN > HIGH_CONFIDENCE_AGENT lexically) and label
    // agent sessions as human whenever the early-session report fires with score 0.
    $sql = "SELECT s.sessionid,
                   MIN(s.timecreated) AS firstseen,
                   MAX(s.combinedscore) AS maxscore,
                   COUNT(s.id) AS signalcount
              FROM {local_agentdetect_signals} s
             WHERE s.userid = :userid
               AND s.contextid {$ctxinsql}
          GROUP BY s.sessionid
          ORDER BY maxscore DESC, firstseen DESC";

    $sessions = $DB->get_records_sql($sql, $ctxparams, 0, 50);

    if (empty($sessions)) {
        echo html_writer::div(
            get_string('coursereport:nosignals', 'local_agentdetect'),
            'alert alert-info'
        );
        return;
    }

    // Bulk-load the highest-scoring combined signal record for each session in one query.
    // This avoids an N+1 query pattern (one DB call per session in the loop).
    $sessionids = array_keys($sessions);
    [$sessinsql, $sessparams] = $DB->get_in_or_equal($sessionids, SQL_PARAMS_NAMED, 'sess');
    // MOO-12 finding #3: reapply the same course-scoped context restriction
    // used above. Filtering only by sessionid + userid would pull records
    // tagged with contexts outside this course whenever the browser reused
    // the same sessionid (it persists for ~30 minutes), leaking verdict and
    // signaldata from a context the viewer may not have access to and
    // distorting the per-session card with cross-context data. We must
    // reuse the $ctxinsql/$ctxparams pair generated upstream and merge the
    // params under fresh placeholder names to avoid colliding with $sessparams.
    [$ctx2insql, $ctx2params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx2');
    $sessparams = array_merge($sessparams, $ctx2params);
    $sessparams['userid'] = $userid;

    // The primary key s.id is listed first so Moodle's get_records_sql keys
    // the result array by id. Without it, Moodle defaults to keying on the first
    // selected column (sessionid here) — every row in a multi-page session
    // shares the same sessionid, so collisions leave only the last-iterated
    // row per session, which with ORDER BY combinedscore DESC is the
    // *lowest*-scoring record. That caused the per-session loop below to bind
    // to a near-empty low-score signal even when high-score 100-point agent
    // signals existed for the same session, rendering "No detection signals
    // found" on agent-heavy sessions.
    $allsignalrecords = $DB->get_records_sql(
        "SELECT s.id, s.sessionid, s.signaldata, s.combinedscore, s.verdict
           FROM {local_agentdetect_signals} s
          WHERE s.sessionid {$sessinsql}
            AND s.userid = :userid
            AND s.contextid {$ctx2insql}
            AND s.signaltype = 'combined'
       ORDER BY s.combinedscore DESC",
        $sessparams
    );

    // Index by sessionid, keeping only the highest-scoring record per session.
    $signalsbysession = [];
    foreach ($allsignalrecords as $rec) {
        if (!isset($signalsbysession[$rec->sessionid])) {
            $signalsbysession[$rec->sessionid] = $rec;
        }
    }

    $sessioncards = [];
    $overallmaxscore = 0;
    $overallmaxverdict = 'LIKELY_HUMAN';
    $totalsessions = 0;
    $sessionswithsignals = 0;
    $allsignalnames = [];

    // Verdict severity ordering for roll-up "worst" verdict.
    $verdictseverity = [
        'LIKELY_HUMAN' => 0,
        'LOW_SUSPICION' => 1,
        'SUSPICIOUS' => 2,
        'PROBABLE_AGENT' => 3,
        'HIGH_CONFIDENCE_AGENT' => 4,
    ];

    foreach ($sessions as $session) {
        $totalsessions++;

        // Look up the pre-loaded signal record for this session.
        $signalrecord = $signalsbysession[$session->sessionid] ?? null;

        $explanations = [];
        if ($signalrecord && $signalrecord->signaldata) {
            $data = json_decode($signalrecord->signaldata);
            $explanations = local_agentdetect_build_signal_explanations($data);
        }

        // Skip sessions with no explainable signal data.
        if (empty($explanations)) {
            continue;
        }

        $sessionswithsignals++;

        // Track roll-up metrics. Verdict comes from the highest-scoring signal
        // record for the session (falling back to a score-derived verdict)
        // so we never end up with score=100 + verdict=LIKELY_HUMAN on the
        // same card due to SQL MAX(verdict) alphabetic ordering.
        $score = (int) $session->maxscore;
        if ($score > $overallmaxscore) {
            $overallmaxscore = $score;
        }
        $verdict = $signalrecord->verdict ?? local_agentdetect_verdict_from_score($score);
        $session->verdict = $verdict;
        $currentseverity = $verdictseverity[$verdict] ?? 0;
        $maxseverity = $verdictseverity[$overallmaxverdict] ?? 0;
        if ($currentseverity > $maxseverity) {
            $overallmaxverdict = $verdict;
        }

        // Collect unique signal names for the roll-up.
        foreach ($explanations as $explanation) {
            $allsignalnames[] = $explanation;
        }

        $sessioncards[] = [
            'session' => $session,
            'explanations' => $explanations,
        ];
    }

    if (empty($sessioncards)) {
        echo html_writer::div(
            get_string('coursereport:nosignals', 'local_agentdetect'),
            'alert alert-info'
        );
        return;
    }

    // Roll-up summary card at top.
    $scorebadge = local_agentdetect_format_score_badge($overallmaxscore);
    $verdictbadge = local_agentdetect_format_verdict_badge($overallmaxverdict);

    echo html_writer::start_div('card mb-4 border-primary');
    echo html_writer::start_div('card-header bg-primary text-white');
    echo html_writer::tag('strong', get_string('coursereport:summary', 'local_agentdetect'));
    echo html_writer::end_div();
    echo html_writer::start_div('card-body');

    // Metrics row.
    echo html_writer::start_div('d-flex flex-wrap mb-3');

    echo html_writer::start_div('mr-4 mb-2');
    echo html_writer::tag(
        'small',
        get_string('coursereport:highestscore', 'local_agentdetect'),
        ['class' => 'd-block text-muted']
    );
    echo html_writer::tag('span', $scorebadge, ['class' => 'h5']);
    echo html_writer::end_div();

    echo html_writer::start_div('mr-4 mb-2');
    echo html_writer::tag(
        'small',
        get_string('coursereport:highestverdict', 'local_agentdetect'),
        ['class' => 'd-block text-muted']
    );
    echo html_writer::tag('span', $verdictbadge, ['class' => 'h5']);
    echo html_writer::end_div();

    echo html_writer::start_div('mr-4 mb-2');
    echo html_writer::tag(
        'small',
        get_string('coursereport:sessioncount', 'local_agentdetect'),
        ['class' => 'd-block text-muted']
    );
    echo html_writer::tag(
        'span',
        $sessionswithsignals . ' / ' . $totalsessions,
        ['class' => 'h5']
    );
    echo html_writer::end_div();

    echo html_writer::end_div(); // Metrics row.

    echo html_writer::end_div(); // Card body.
    echo html_writer::end_div(); // Card.

    // Per-session cards (filtered, highest score first).
    foreach ($sessioncards as $card) {
        $session = $card['session'];
        $explanations = $card['explanations'];

        $scorebadge = local_agentdetect_format_score_badge($session->maxscore);
        $verdictbadge = local_agentdetect_format_verdict_badge($session->verdict);
        $shortsessionid = substr($session->sessionid, 0, 12) . '...';

        // Card header.
        echo html_writer::start_div('card mb-3');
        echo html_writer::start_div('card-header d-flex justify-content-between align-items-center');
        echo html_writer::tag('span', userdate($session->firstseen, '%Y-%m-%d %H:%M') . ' &mdash; ' . $verdictbadge
            . ' ' . $scorebadge);
        echo html_writer::tag('small', $shortsessionid, ['class' => 'text-muted', 'title' => $session->sessionid]);
        echo html_writer::end_div();

        // Card body — signal explanations.
        echo html_writer::start_div('card-body');

        echo html_writer::tag(
            'p',
            get_string('coursereport:whyflagged', 'local_agentdetect'),
            ['class' => 'font-weight-bold mb-2']
        );
        echo html_writer::start_tag('ul', ['class' => 'mb-0']);
        foreach ($explanations as $explanation) {
            echo html_writer::tag('li', $explanation);
        }
        echo html_writer::end_tag('ul');

        echo html_writer::end_div(); // Card body.
        echo html_writer::end_div(); // Card.
    }

    // If user also has viewsignals, show link to admin report.
    if (has_capability('local/agentdetect:viewsignals', context_system::instance())) {
        $adminurl = new moodle_url('/local/agentdetect/report.php', ['userid' => $userid]);
        $adminlink = html_writer::link(
            $adminurl,
            get_string('coursereport:viewadminreport', 'local_agentdetect'),
            ['class' => 'btn btn-sm btn-outline-secondary']
        );
        echo html_writer::div($adminlink, 'mt-3');
    }
}

/**
 * Get all context IDs for a course and its child modules.
 *
 * @param context_course $context The course context.
 * @return array Array of context IDs.
 */
function local_agentdetect_get_course_context_ids(context_course $context): array {
    global $DB;

    $path = $context->path;
    $like = $DB->sql_like('path', ':path');
    $params = ['path' => $path . '/%', 'selfid' => $context->id];

    return $DB->get_fieldset_select('context', 'id', "{$like} OR id = :selfid", $params);
}

/**
 * Format a score as a coloured Bootstrap badge.
 *
 * @param int|string $score The score value.
 * @return string HTML badge.
 */
function local_agentdetect_format_score_badge($score): string {
    if (!is_numeric($score)) {
        return (string) $score;
    }
    if ($score >= 70) {
        return html_writer::tag('span', $score, ['class' => 'badge badge-danger']);
    } else if ($score >= 40) {
        return html_writer::tag('span', $score, ['class' => 'badge badge-warning']);
    }
    return html_writer::tag('span', $score, ['class' => 'badge badge-success']);
}

/**
 * Format a verdict as a Bootstrap badge.
 *
 * @param string|null $verdict The verdict string.
 * @return string HTML badge.
 */
/**
 * Derive a verdict string from a numeric combined score.
 *
 * Mirrors the client-side getVerdict() thresholds in amd/src/detector.js so
 * the server can always produce a verdict even when a specific record is
 * missing one.
 *
 * @param int $score Combined score 0–100.
 * @return string Verdict enum.
 */
function local_agentdetect_verdict_from_score(int $score): string {
    if ($score >= 80) {
        return 'HIGH_CONFIDENCE_AGENT';
    }
    if ($score >= 60) {
        return 'PROBABLE_AGENT';
    }
    if ($score >= 40) {
        return 'SUSPICIOUS';
    }
    if ($score >= 20) {
        return 'LOW_SUSPICION';
    }
    return 'LIKELY_HUMAN';
}

/**
 * Format a verdict enum into a colour-coded badge for display.
 *
 * @param string|null $verdict One of HIGH_CONFIDENCE_AGENT, PROBABLE_AGENT,
 *                             SUSPICIOUS, LOW_SUSPICION, LIKELY_HUMAN, or null.
 * @return string HTML for the badge, or '-' when the verdict is null.
 */
function local_agentdetect_format_verdict_badge(?string $verdict): string {
    if ($verdict === null) {
        return '-';
    }
    switch ($verdict) {
        case 'HIGH_CONFIDENCE_AGENT':
            return html_writer::tag(
                'span',
                get_string('verdict:high', 'local_agentdetect'),
                ['class' => 'badge badge-danger', 'title' => get_string('verdict:highconfidenceagent', 'local_agentdetect')]
            );
        case 'PROBABLE_AGENT':
            return html_writer::tag(
                'span',
                get_string('verdict:probable', 'local_agentdetect'),
                ['class' => 'badge badge-warning', 'title' => get_string('verdict:probableagent', 'local_agentdetect')]
            );
        case 'SUSPICIOUS':
            return html_writer::tag(
                'span',
                get_string('verdict:suspiciouslabel', 'local_agentdetect'),
                ['class' => 'badge badge-warning']
            );
        case 'LOW_SUSPICION':
            return html_writer::tag(
                'span',
                get_string('verdict:low', 'local_agentdetect'),
                ['class' => 'badge badge-info', 'title' => get_string('verdict:lowsuspicion', 'local_agentdetect')]
            );
        case 'LIKELY_HUMAN':
            return html_writer::tag(
                'span',
                get_string('verdict:human', 'local_agentdetect'),
                ['class' => 'badge badge-success', 'title' => get_string('verdict:likelyhuman', 'local_agentdetect')]
            );
        default:
            // Defense in depth: signal_manager::store_signal() rejects verdicts
            // outside the allowlist, so this branch should only ever fire for
            // legacy rows written before that check. Escape the value with s()
            // so any pre-existing stored XSS payloads from before MOO-12 cannot
            // execute in the report.
            return html_writer::tag('span', s($verdict), ['class' => 'badge badge-secondary']);
    }
}

/**
 * Build plain-language explanations from signal data.
 *
 * Extracts the most significant signals from the JSON signal data and
 * returns teacher-friendly descriptions of what was detected.
 *
 * @param object $data Decoded JSON signal data.
 * @return array Array of explanation strings (HTML safe).
 */
function local_agentdetect_build_signal_explanations(object $data): array {
    $explanations = [];

    // Collect all anomaly signals with their weights.
    $signals = [];

    // Interaction anomalies.
    if (isset($data->interaction->anomalies) && is_array($data->interaction->anomalies)) {
        foreach ($data->interaction->anomalies as $a) {
            $signals[] = (object) [
                'name' => $a->name,
                'value' => $a->value ?? null,
                'weight' => $a->weight ?? 0,
            ];
        }
    }

    // Agent detection signals.
    if (isset($data->comet->signals) && is_array($data->comet->signals)) {
        foreach ($data->comet->signals as $cs) {
            $weight = $cs->weight ?? $cs->maxWeight ?? 0;
            $signals[] = (object) [
                'name' => $cs->name,
                'value' => $cs->value ?? null,
                'weight' => $weight,
            ];
        }
    }

    // Fingerprint signals.
    if (isset($data->fingerprint->signals) && is_array($data->fingerprint->signals)) {
        foreach ($data->fingerprint->signals as $fs) {
            $signals[] = (object) [
                'name' => $fs->name,
                'value' => $fs->value ?? null,
                'weight' => $fs->weight ?? 0,
            ];
        }
    }

    // Sort by weight descending and take the most significant ones.
    usort($signals, function ($a, $b) {
        return $b->weight - $a->weight;
    });

    // Deduplicate by name.
    $seen = [];
    foreach ($signals as $signal) {
        if (isset($seen[$signal->name])) {
            continue;
        }
        $seen[$signal->name] = true;

        $explanation = local_agentdetect_explain_signal($signal->name, $signal->value, $signal->weight);
        if ($explanation) {
            $explanations[] = $explanation;
        }

        // Cap at 8 explanations to keep it readable.
        if (count($explanations) >= 8) {
            break;
        }
    }

    return $explanations;
}

/**
 * Return a plain-language explanation for a detection signal.
 *
 * @param string $name The signal name.
 * @param mixed $value The signal value.
 * @param int $weight The signal weight.
 * @return string|null Human-readable explanation, or null if not mapped.
 */
function local_agentdetect_explain_signal(string $name, $value, int $weight): ?string {
    // Only map known signal names. The translated explanation is pulled from
    // lang/en/local_agentdetect.php via the 'explain:<signal_name>' key, so
    // translators can ship localisations without touching PHP.
    static $known = [
        'click.center_precision',
        'click.no_hover',
        'click.no_movement',
        'click.perfect_timing',
        'click.superhuman_speed',
        'click.teleport_pattern',
        'comet.action_burst',
        'comet.extension.cached',
        'comet.extension.link_injected',
        'comet.extension.resource_probe',
        'comet.extension.script_injected',
        'comet.extension.stylesheet',
        'comet.low_mouse_to_action_ratio',
        'comet.low_per_page_mouse_ratio',
        'comet.missing_pointer_events',
        'comet.no_mousemove_trail',
        'comet.rapid_focus_sequence',
        'comet.read_then_act',
        'comet.runtime.global',
        'comet.runtime.inline_style',
        'comet.runtime.script',
        'comet.scroll_then_click',
        'comet.ultra_precise_center',
        'comet.uniform_hold_duration',
        'comet.uniform_keystroke_cadence',
        'comet.zero_keystrokes',
        'sequence.direct_focus',
        'sequence.low_hover_ratio',
    ];

    if (!in_array($name, $known, true)) {
        return null;
    }

    return get_string('explain:' . $name, 'local_agentdetect');
}
