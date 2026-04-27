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
 * Language strings for local_agentdetect.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['agentdetect:configure'] = 'Configure agent detection settings';
$string['agentdetect:manageflags'] = 'Manage agent detection flags';
$string['agentdetect:viewreports'] = 'View agent detection reports';
$string['agentdetect:viewsignals'] = 'View detailed detection signal data';
$string['coursereport'] = 'Agent Detection Report';
$string['coursereport:caveat'] = 'Important: These results are not definitive proof of academic dishonesty. This report is based on automated behavioural analysis of the student\'s browser session, including mouse movement patterns, click behaviour, and keyboard activity. Unusual patterns may have legitimate explanations. Please use this information as one factor among many when making academic integrity decisions.';
$string['coursereport:date'] = 'Date';
$string['coursereport:detectioncount'] = 'Detection count';
$string['coursereport:flaggedstudents'] = 'Flagged students';
$string['coursereport:flagtype'] = 'Flag type';
$string['coursereport:highestscore'] = 'Highest score';
$string['coursereport:highestverdict'] = 'Highest verdict';
$string['coursereport:lastdetected'] = 'Last detected';
$string['coursereport:maxscore'] = 'Max score';
$string['coursereport:noexplanation'] = 'No detailed signal data available for this session.';
$string['coursereport:noflags'] = 'No students flagged in this course.';
$string['coursereport:nosignals'] = 'No detection signals found for this student.';
$string['coursereport:score'] = 'Score';
$string['coursereport:sessioncount'] = 'Sessions with signals';
$string['coursereport:sessionid'] = 'Session';
$string['coursereport:signalcount'] = 'Signals';
$string['coursereport:studentsignals'] = 'Detection signals for {$a}';
$string['coursereport:summary'] = 'Detection summary';
$string['coursereport:title'] = 'Agent Detection Report';
$string['coursereport:verdict'] = 'Verdict';
$string['coursereport:viewadminreport'] = 'View admin report';
$string['coursereport:viewdetails'] = 'View details';
$string['coursereport:whyflagged'] = 'Why was this session flagged?';
$string['error:signalstorefailed'] = 'Failed to store detection signal.';
$string['event:signaldetected'] = 'Agent detection signal recorded';
$string['event:userflagged'] = 'User flagged by agent detection';
$string['explain:click.center_precision'] = 'Clicks landed at the exact mathematical centre of page elements, a pattern consistent with programmatic clicking rather than a human hand.';
$string['explain:click.no_hover'] = 'Click targets were never hovered over before being clicked, which is unusual for human mouse interaction.';
$string['explain:click.no_movement'] = 'No mouse movement at all was detected during the session — all interaction consisted of clicks without any visible cursor activity.';
$string['explain:click.perfect_timing'] = 'The timing between consecutive clicks was unusually uniform, suggesting automated pacing rather than natural human rhythm.';
$string['explain:click.superhuman_speed'] = 'Some clicks occurred faster than typical human reaction time allows.';
$string['explain:click.teleport_pattern'] = 'The mouse cursor jumped instantly between distant screen positions without any intermediate movement — consistent with automated cursor positioning.';
$string['explain:comet.action_burst'] = 'Rapid bursts of actions (clicking answers in quick succession) were detected at a rate well above typical human quiz-taking speed.';
$string['explain:comet.extension.cached'] = 'A known AI agent browser extension was detected as installed.';
$string['explain:comet.extension.link_injected'] = 'Resource links associated with a known AI agent extension were found in the page.';
$string['explain:comet.extension.resource_probe'] = 'Probing for known AI agent extension resources returned a positive result.';
$string['explain:comet.extension.script_injected'] = 'Scripts associated with a known AI agent extension were found injected into the page.';
$string['explain:comet.extension.stylesheet'] = 'Stylesheets associated with a known AI agent extension were detected.';
$string['explain:comet.low_mouse_to_action_ratio'] = 'Very few mouse movements were recorded relative to the number of clicks. Human users naturally move the mouse before and between clicks.';
$string['explain:comet.low_per_page_mouse_ratio'] = 'Across most quiz pages, the number of mouse movements was extremely low compared to clicks — a strong indicator of programmatic interaction.';
$string['explain:comet.missing_pointer_events'] = 'Expected pointer interaction events (mouse down/up sequences) were missing or incomplete during click actions.';
$string['explain:comet.no_mousemove_trail'] = 'Clicks occurred without any mouse movement trail beforehand — human users almost always generate visible cursor movement before clicking.';
$string['explain:comet.rapid_focus_sequence'] = 'Page focus changed rapidly multiple times, consistent with an automated tool switching between browser tabs or windows.';
$string['explain:comet.read_then_act'] = 'A repeated pattern of pausing (as if reading the question) followed by an immediate precise answer was detected across multiple questions.';
$string['explain:comet.runtime.global'] = 'Global JavaScript variables associated with a known AI agent were found.';
$string['explain:comet.runtime.inline_style'] = 'Inline styles characteristic of an AI agent overlay were detected on the page.';
$string['explain:comet.runtime.script'] = 'Runtime scripts characteristic of an AI agent were detected on the page.';
$string['explain:comet.scroll_then_click'] = 'A high proportion of clicks were immediately preceded by a scroll event, suggesting automated "scroll to element, then click" behaviour.';
$string['explain:comet.ultra_precise_center'] = 'Multiple clicks hit the precise pixel centre of their target elements, which is extremely unlikely for a human using a mouse or trackpad.';
$string['explain:comet.uniform_hold_duration'] = 'Keys were held down for nearly identical durations across all keystrokes, which is atypical of natural human typing.';
$string['explain:comet.uniform_keystroke_cadence'] = 'Keystrokes were typed at a suspiciously uniform speed, lacking the natural variation in timing that human typing exhibits.';
$string['explain:comet.zero_keystrokes'] = 'No keyboard input was recorded during the session despite multiple clicks and page navigations — the session appeared to be driven entirely by clicking.';
$string['explain:sequence.direct_focus'] = 'Form elements received focus directly without the preceding mouse movement that would normally occur with human navigation.';
$string['explain:sequence.low_hover_ratio'] = 'The proportion of elements hovered before clicking was unusually low compared to typical human browsing patterns.';
$string['flag:clearedbyadmin'] = 'Cleared by admin';
$string['flagtype:agent_confirmed'] = 'Agent confirmed';
$string['flagtype:agent_suspected'] = 'Agent suspected';
$string['flagtype:cleared'] = 'Cleared';
$string['flagtype:likely_human'] = 'Likely human';
$string['flagtype:low_suspicion'] = 'Low suspicion';
$string['page'] = 'Page';
$string['pluginname'] = 'Agent Detection';
$string['privacy:metadata:local_agentdetect_flags'] = 'Stores aggregated agent detection flags per user.';
$string['privacy:metadata:local_agentdetect_flags:contextid'] = 'The Moodle context for this flag.';
$string['privacy:metadata:local_agentdetect_flags:detectioncount'] = 'The number of suspicious detections recorded.';
$string['privacy:metadata:local_agentdetect_flags:flagtype'] = 'The type of flag assigned to the user.';
$string['privacy:metadata:local_agentdetect_flags:maxscore'] = 'The maximum detection score observed for this user.';
$string['privacy:metadata:local_agentdetect_flags:notes'] = 'Admin notes about this flag.';
$string['privacy:metadata:local_agentdetect_flags:timecreated'] = 'Timestamp when the flag was first created.';
$string['privacy:metadata:local_agentdetect_flags:timemodified'] = 'Timestamp when the flag was last updated.';
$string['privacy:metadata:local_agentdetect_flags:userid'] = 'The ID of the flagged user.';
$string['privacy:metadata:local_agentdetect_signals'] = 'Stores browser fingerprint and interaction signals for agent detection.';
$string['privacy:metadata:local_agentdetect_signals:combinedscore'] = 'Combined detection score (0-100).';
$string['privacy:metadata:local_agentdetect_signals:contextid'] = 'The Moodle context where the detection occurred.';
$string['privacy:metadata:local_agentdetect_signals:fingerprintscore'] = 'Browser fingerprint detection score (0-100).';
$string['privacy:metadata:local_agentdetect_signals:interactionscore'] = 'Interaction anomaly detection score (0-100).';
$string['privacy:metadata:local_agentdetect_signals:ipaddress'] = 'The client IP address at the time of detection.';
$string['privacy:metadata:local_agentdetect_signals:sessionid'] = 'A unique identifier for the detection session.';
$string['privacy:metadata:local_agentdetect_signals:signaldata'] = 'JSON-encoded detailed signal data collected from the browser.';
$string['privacy:metadata:local_agentdetect_signals:signaltype'] = 'The type of detection signal recorded.';
$string['privacy:metadata:local_agentdetect_signals:timecreated'] = 'Timestamp when the signals were collected.';
$string['privacy:metadata:local_agentdetect_signals:useragent'] = 'The browser user agent string.';
$string['privacy:metadata:local_agentdetect_signals:userid'] = 'The ID of the user whose session is being analyzed.';
$string['privacy:metadata:local_agentdetect_signals:verdict'] = 'The detection verdict string.';
$string['report:allsessions'] = 'All sessions';
$string['report:allusers'] = 'All users';
$string['report:anomalyweight'] = '({$a->value}) w:{$a->weight}';
$string['report:avgscore'] = 'Avg score';
$string['report:clearfilters'] = 'Clear filters';
$string['report:combined'] = 'Combined';
$string['report:cometagent'] = 'Agent';
$string['report:cometagentscore'] = '[AGENT] Score: {$a}';
$string['report:cometsignal'] = '[AGENT] {$a->name} w:{$a->weight}';
$string['report:context'] = 'Context';
$string['report:details'] = 'Details';
$string['report:detailsweight'] = '{$a->name} ({$a->value}) w:{$a->weight}';
$string['report:downloadjson'] = 'Download JSON';
$string['report:eventcounts'] = 'events: m:{$a->moves} c:{$a->clicks} k:{$a->keys}';
$string['report:filter'] = 'Filter';
$string['report:filtersignals'] = 'Filter signals';
$string['report:fp'] = 'Fingerprint';
$string['report:int'] = 'Interaction';
$string['report:maxscore'] = 'Max score';
$string['report:noflags'] = 'No users flagged yet.';
$string['report:nosignals'] = 'No signals recorded yet.';
$string['report:sessionoption'] = '{$a->sessionid} (max: {$a->maxscore})';
$string['report:showing'] = 'Showing latest {$a->limit} of {$a->total} total signals.';
$string['report:signalsfor'] = 'Signals for {$a}';
$string['report:storedsignals'] = 'Stored detection signals';
$string['report:title'] = 'Agent Detection Signals';
$string['report:userflags'] = 'User flags';
$string['report:useroption'] = '{$a->fullname} ({$a->signalcount} signals, max: {$a->maxscore})';
$string['report:usersummary'] = 'User summary: ';
$string['report:viewsignals'] = 'View signals';
$string['settings:debug'] = 'Debug mode';
$string['settings:debug_desc'] = 'Enable debug logging in browser console.';
$string['settings:enabled'] = 'Enable agent detection';
$string['settings:enabled_desc'] = 'When enabled, the plugin will collect browser signals and flag potential automated sessions.';
$string['settings:minreportscore'] = 'Minimum report score';
$string['settings:minreportscore_desc'] = 'Only report signals with combined score at or above this value (0-100).';
$string['settings:reportinterval'] = 'Report interval (ms)';
$string['settings:reportinterval_desc'] = 'How often to send detection reports to the server (in milliseconds).';
$string['settings:threshold'] = 'Detection threshold';
$string['settings:threshold_desc'] = 'Agent probability score (0-100) above which a session is flagged. Lower values are more sensitive.';
$string['type'] = 'Type';
$string['verdict:high'] = 'HIGH';
$string['verdict:highconfidenceagent'] = 'High confidence agent';
$string['verdict:human'] = 'HUMAN';
$string['verdict:likelyhuman'] = 'Likely human';
$string['verdict:low'] = 'LOW';
$string['verdict:lowsuspicion'] = 'Low suspicion';
$string['verdict:probable'] = 'PROBABLE';
$string['verdict:probableagent'] = 'Probable agent';
$string['verdict:suspicious'] = 'Suspicious';
$string['verdict:suspiciouslabel'] = 'SUSPICIOUS';
