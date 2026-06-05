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
 * Main agent detection module (Lite).
 *
 * Orchestrates fingerprint and interaction detection, combines results,
 * and reports a compact summary to the Moodle backend.
 *
 * @module     local_agentdetect/detector
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Fingerprint from 'local_agentdetect/fingerprint';
import * as Interaction from 'local_agentdetect/interaction';
import Ajax from 'core/ajax';
import Log from 'core/log';

let config = {
    enabled: true,
    reportInterval: 30000,
    minReportScore: 10,
    contextId: null,
    userId: null,
    sessionKey: null,
    debug: false,
};

let reportTimer = null;
let sessionId = null;
const SESSION_MAX_AGE = 30 * 60 * 1000;
const MAX_REPORTED_ANOMALIES = 8;
let initialized = false;

// Timestamp of the last report actually sent to the server. Used to throttle
// the visibilitychange-triggered report so rapid tab switching cannot burst
// writes (MOO-13) — we never report more often than reportInterval from it.
let lastReportTime = 0;

// Cached so handlePageUnload can build a full scored payload synchronously
// inside beforeunload (Fingerprint.collect is async and cannot be awaited there).
let lastFingerprint = null;

// Moodle question type classes used on div.que elements. Used to tell the
// interaction analyzer whether the page contains text-input questions (so
// zero_keystrokes is a meaningful agent signal) or only click-only questions.
const KNOWN_QTYPES = [
    'multichoice', 'multichoiceset', 'truefalse', 'match', 'shortanswer',
    'numerical', 'essay', 'calculated', 'calculatedmulti', 'calculatedsimple',
    'ddwtos', 'ddimageortext', 'ddmarker', 'gapselect', 'multianswer',
    'ordering', 'randomsamatch', 'description',
];

/**
 * Scan the DOM for Moodle quiz question elements and extract their qtype
 * classes. Moodle renders each question as div.que with classes like
 * "que multichoice deferredfeedback notyetanswered" — we pick out the tokens
 * that match known question types.
 *
 * @returns {Array<string>} Array of qtype strings found on this page.
 */
const detectQuestionTypes = () => {
    const types = [];
    try {
        const elements = document.querySelectorAll('div.que');
        elements.forEach((el) => {
            for (const qt of KNOWN_QTYPES) {
                if (el.classList.contains(qt) && !types.includes(qt)) {
                    types.push(qt);
                }
            }
        });
    } catch (e) {
        // Ignore DOM access errors.
    }
    return types;
};

/**
 * Initialize the agent detection system.
 *
 * @param {Object} options Configuration options from PHP.
 * @returns {Promise<void>}
 */
export const init = async(options = {}) => {
    if (initialized) {
        return;
    }

    config = {...config, ...options};

    if (!config.enabled) {
        return;
    }

    sessionId = restoreOrCreateSessionId();

    if (config.debug) {
        Log.debug('[AgentDetect] Initializing', {sessionId});
    }

    Interaction.startMonitoring({contextId: config.contextId, userId: config.userId});
    Interaction.noteQuestionTypes(detectQuestionTypes());

    const initialFingerprint = await Fingerprint.collect();
    lastFingerprint = initialFingerprint;

    if (initialFingerprint.score >= config.minReportScore) {
        await reportSignals('fingerprint', buildFingerprintPayload(initialFingerprint));
    }

    startPeriodicReporting();

    window.addEventListener('beforeunload', handlePageUnload);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    initialized = true;
};

const generateSessionId = () => {
    const timestamp = Date.now().toString(36);
    const random = Math.random().toString(36).substring(2, 10);
    return `${timestamp}-${random}`;
};

const restoreOrCreateSessionId = () => {
    // Scope session ID to the current user so two admins / test accounts
    // sharing a browser tab don't inherit each other's sessionId and, via
    // the ratio analyses, each other's event history.
    const storageKey = 'agentdetect_session';
    try {
        const stored = sessionStorage.getItem(storageKey);
        if (stored) {
            const parsed = JSON.parse(stored);
            const age = Date.now() - (parsed.timestamp || 0);
            const sameUser = !config.userId || parsed.userId === config.userId;
            if (sameUser && age < SESSION_MAX_AGE && parsed.id) {
                return parsed.id;
            }
        }
    } catch (e) {
        // SessionStorage unavailable.
    }

    const newId = generateSessionId();
    try {
        sessionStorage.setItem(storageKey, JSON.stringify({
            id: newId,
            userId: config.userId || null,
            timestamp: Date.now(),
        }));
    } catch (e) {
        // Ignore.
    }
    return newId;
};

const startPeriodicReporting = () => {
    if (reportTimer) {
        clearInterval(reportTimer);
    }
    reportTimer = setInterval(async() => {
        await collectAndReport();
    }, config.reportInterval);
};

const stopPeriodicReporting = () => {
    if (reportTimer) {
        clearInterval(reportTimer);
        reportTimer = null;
    }
};

/**
 * Collect all signals and report to server.
 *
 * @returns {Promise<Object>} Combined analysis results.
 */
export const collectAndReport = async() => {
    // Refresh question-type context in case the DOM was updated (multi-page quizzes).
    Interaction.noteQuestionTypes(detectQuestionTypes());
    const fingerprint = await Fingerprint.collect();
    lastFingerprint = fingerprint;
    const interaction = Interaction.analyze();

    const comet = extractCometSignals(fingerprint, interaction);
    const combinedScore = calculateCombinedScore(
        fingerprint.score,
        interaction.score,
        comet.score
    );

    const verdict = getVerdict(combinedScore);

    const payload = buildCombinedPayload(fingerprint, interaction, comet, combinedScore, verdict);

    if (combinedScore >= config.minReportScore) {
        await reportSignals('combined', payload);
    }

    return payload;
};

/**
 * Collect all fingerprint sub-signals into a flat list.
 *
 * @param {Object} fp Fingerprint object.
 * @returns {Array} All fingerprint signals.
 */
const flattenFingerprintSignals = (fp) => [
    ...(fp.webdriver?.signals || []),
    ...(fp.headless?.signals || []),
    ...(fp.extensions?.signals || []),
    ...(fp.cometExtension?.signals || []),
    ...(fp.cometRuntime?.signals || []),
    ...(fp.globals?.signals || []),
    ...(fp.domMarkers?.signals || []),
    ...(fp.canvas?.anomalies || []),
    ...(fp.webgl?.anomalies || []),
];

/**
 * Build the compact fingerprint-only payload sent on initial detection.
 *
 * @param {Object} fp Full fingerprint object.
 * @returns {Object} Compact nested payload.
 */
const buildFingerprintPayload = (fp) => ({
    fingerprint: {
        score: fp.score,
        signals: topSignals(flattenFingerprintSignals(fp)),
    },
});

/**
 * Build the compact combined payload. Raw per-event data is intentionally
 * omitted — we only keep the summary needed for scoring + display.
 *
 * @param {Object} fp Fingerprint object.
 * @param {Object} inter Interaction analysis.
 * @param {Object} cometSummary Comet summary (with raw signals list).
 * @param {number} combinedScore Combined score.
 * @param {string} verdict Verdict string.
 * @returns {Object} Compact nested payload.
 */
const buildCombinedPayload = (fp, inter, cometSummary, combinedScore, verdict) => ({
    fingerprint: {
        score: fp.score,
        signals: topSignals(flattenFingerprintSignals(fp)),
    },
    interaction: {
        score: inter.score,
        eventCounts: inter.eventCounts,
        duration: inter.duration,
        pageLoadCount: inter.pageLoadCount,
        questionTypes: inter.questionTypes,
        textInputFocusCount: inter.textInputFocusCount,
        anomalies: topSignals(inter.anomalies),
    },
    comet: {
        detected: cometSummary.detected,
        score: cometSummary.score,
        signals: topSignals(cometSummary.signals),
    },
    combinedScore,
    verdict,
    // detectedAgent is only promoted to 'ai_browser_agent' once the CDP-agent
    // score clears the same >= 40 threshold the combined-score formula uses
    // for its bonus, so teachers do not see an AGENT badge next to a
    // LOW_SUSPICION verdict when a single weight-3 signal fires on its own.
    // The label is deliberately generic — the 'comet.*' signal prefix is a
    // naming artifact, these signals catch any CDP-driven browser agent
    // (Perplexity Comet, Claude Chrome, Playwright, Puppeteer, etc.).
    detectedAgent: (cometSummary.detected && cometSummary.score >= 40) ? 'ai_browser_agent' : null,
});

/**
 * Reduce signals/anomalies to the top N by weight, stripped of raw values.
 *
 * @param {Array} signals Array of {name, value, weight} objects.
 * @returns {Array} Top entries with name + weight only.
 */
const topSignals = (signals) => {
    return (signals || [])
        .slice()
        .sort((a, b) => (b.weight || 0) - (a.weight || 0))
        .slice(0, MAX_REPORTED_ANOMALIES)
        .map((a) => ({name: a.name, weight: a.weight || 0}));
};

/**
 * Calculate combined score from fingerprint, interaction, and Comet signals.
 *
 * @param {number} fingerprintScore Fingerprint score (0-100).
 * @param {number} interactionScore Interaction score (0-100).
 * @param {number} cometScore Comet agentic mode score (0-100).
 * @returns {number} Combined score (0-100).
 */
const calculateCombinedScore = (fingerprintScore, interactionScore, cometScore = 0) => {
    let score = interactionScore;

    if (fingerprintScore >= 70) {
        score = Math.min(100, score + 30);
    } else if (fingerprintScore >= 40) {
        score = Math.min(100, score + 15);
    } else if (fingerprintScore >= 20) {
        score = Math.min(100, score + 5);
    }

    if (cometScore >= 70) {
        score = Math.max(score, 80);
        score = Math.min(100, score + 10);
    } else if (cometScore >= 40) {
        score = Math.min(100, score + 15);
    } else if (cometScore >= 20) {
        score = Math.min(100, score + 5);
    }

    return Math.round(score);
};

/**
 * Extract Comet-specific signals from fingerprint and interaction results.
 *
 * @param {Object} fingerprint Fingerprint results.
 * @param {Object} interaction Interaction analysis results.
 * @returns {Object} Comet detection summary with signals and score.
 */
const extractCometSignals = (fingerprint, interaction) => {
    const signals = [];

    if (fingerprint.cometExtension) {
        signals.push(...(fingerprint.cometExtension.signals || []));
    }
    if (fingerprint.cometRuntime) {
        signals.push(...(fingerprint.cometRuntime.signals || []));
    }

    const webdriverChange = (fingerprint.webdriver?.signals || []).find(
        (s) => s.name === 'webdriver.changed_mid_session'
    );
    if (webdriverChange) {
        signals.push(webdriverChange);
    }

    const cometAnomalies = (interaction.anomalies || []).filter(
        (a) => a.name.startsWith('comet.')
    );
    signals.push(...cometAnomalies);

    return {
        detected: signals.length > 0,
        signalCount: signals.length,
        signals,
        score: calculateCometScore(signals),
    };
};

/**
 * Calculate Comet agentic mode score from extracted signals using tiered scoring.
 *
 * @param {Array} signals Comet-specific signals.
 * @returns {number} Score from 0-100.
 */
const calculateCometScore = (signals) => {
    if (signals.length === 0) {
        return 0;
    }

    const totalWeight = signals.reduce((sum, s) => sum + (s.weight || 0), 0);

    const hasDefinitiveSignal = signals.some((s) =>
        s.name === 'comet.extension.script_injected' ||
        s.name === 'comet.extension.link_injected' ||
        s.name === 'comet.extension.stylesheet' ||
        s.name.startsWith('comet.runtime.')
    );

    if (hasDefinitiveSignal) {
        return Math.min(100, 70 + totalWeight);
    }

    const TIER1_WEIGHTED = [
        'comet.ultra_precise_center',
        'comet.low_mouse_to_action_ratio',
        'comet.low_per_page_mouse_ratio',
    ];
    const TIER1_ANY = ['comet.zero_keystrokes'];
    const tier1 = signals.filter((s) =>
        (TIER1_WEIGHTED.includes(s.name) && s.weight >= 10) ||
        TIER1_ANY.includes(s.name)
    );
    const tier2 = signals.filter((s) => s.name.startsWith('comet.') && !tier1.includes(s));

    if (tier1.length >= 1 && tier2.length >= 2) {
        return Math.min(100, totalWeight * 2);
    }
    if (tier1.length >= 1) {
        return Math.min(100, Math.round(totalWeight * 1.5));
    }
    return Math.min(40, totalWeight);
};

const getVerdict = (score) => {
    if (score >= 80) {
        return 'HIGH_CONFIDENCE_AGENT';
    } else if (score >= 60) {
        return 'PROBABLE_AGENT';
    } else if (score >= 40) {
        return 'SUSPICIOUS';
    } else if (score >= 20) {
        return 'LOW_SUSPICION';
    }
    return 'LIKELY_HUMAN';
};

/**
 * Report signals to the Moodle backend.
 *
 * @param {string} type Signal type string.
 * @param {Object} data Compact payload.
 * @returns {Promise<void>}
 */
const reportSignals = async(type, data) => {
    if (!config.sessionKey) {
        return;
    }

    lastReportTime = Date.now();

    try {
        await Ajax.call([{
            methodname: 'local_agentdetect_report_signals',
            args: {
                sesskey: config.sessionKey,
                contextid: config.contextId,
                sessionid: sessionId,
                signaltype: type,
                signaldata: JSON.stringify(data),
            },
        }])[0];
    } catch (error) {
        if (config.debug) {
            Log.error('[AgentDetect] Report failed:', error);
        }
    }
};

/**
 * Handle page unload - save state for cross-page continuity and send beacon.
 *
 * @returns {void}
 */
const handlePageUnload = () => {
    Interaction.saveToSessionStorage();

    if (!navigator.sendBeacon || !config.sessionKey) {
        return;
    }

    // Build a fully-scored payload synchronously — fingerprint is reused from
    // the last async collect(), interaction is computed inline. This is
    // critical: short-lived quiz pages (answered in <reportInterval) only send
    // this unload beacon, so without scoring here a flag is never raised.
    const interaction = Interaction.analyze();
    const fingerprint = lastFingerprint || {score: 0};
    const comet = extractCometSignals(fingerprint, interaction);
    const combinedScore = calculateCombinedScore(
        fingerprint.score,
        interaction.score,
        comet.score
    );

    // Gate the unload beacon by minReportScore, matching the init and periodic
    // paths. The beacon was previously ungated, so nearly every quiz page exit
    // wrote a row — by far the highest-volume ingestion path and the main
    // amplifier behind the session-lock contention in MOO-13. Pages scoring
    // below the threshold are uninteresting (likely human, no signal) and are
    // dropped here; genuinely suspicious short-lived pages still report.
    if (combinedScore < config.minReportScore) {
        return;
    }

    const verdict = getVerdict(combinedScore);
    const beaconData = buildCombinedPayload(fingerprint, interaction, comet, combinedScore, verdict);

    const payload = {
        sesskey: config.sessionKey,
        contextid: config.contextId,
        sessionid: sessionId,
        signaltype: 'unload',
        signaldata: JSON.stringify(beaconData),
    };

    const url = M.cfg.wwwroot + '/local/agentdetect/beacon.php';
    navigator.sendBeacon(url, JSON.stringify(payload));
};

const handleVisibilityChange = async() => {
    if (document.visibilityState !== 'hidden') {
        return;
    }

    // Throttle: skip if we already reported within the last reportInterval.
    // Without this, repeatedly switching away from the quiz tab fires a fresh
    // collectAndReport() each time, multiplying ingestion writes and the
    // session-lock pressure they create (MOO-13). The periodic timer still
    // guarantees coverage at the configured cadence.
    if (Date.now() - lastReportTime < config.reportInterval) {
        return;
    }

    await collectAndReport();
};

/**
 * Manually trigger detection analysis.
 *
 * @returns {Promise<Object>} Analysis results.
 */
export const runAnalysis = async() => {
    return await collectAndReport();
};

/**
 * Get current detection status.
 *
 * @returns {Object} Status information.
 */
export const getStatus = () => {
    return {
        initialized,
        sessionId,
        isMonitoring: Interaction.getRawData().isMonitoring,
        config: {
            enabled: config.enabled,
            reportInterval: config.reportInterval,
            minReportScore: config.minReportScore,
        },
    };
};

/**
 * Shutdown detection and cleanup.
 *
 * @returns {void}
 */
export const shutdown = () => {
    stopPeriodicReporting();
    Interaction.stopMonitoring();
    window.removeEventListener('beforeunload', handlePageUnload);
    document.removeEventListener('visibilitychange', handleVisibilityChange);
    initialized = false;
};

export default {
    init,
    runAnalysis,
    getStatus,
    shutdown,
    collectAndReport,
};
