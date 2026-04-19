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
 * Technical fingerprinting module for agent detection (Lite).
 *
 * Collects browser fingerprint signals to identify automation tools,
 * headless browsers, and known AI/automation extensions. This lite build
 * avoids any network probing and does not transmit full browser fingerprints
 * (canvas data URLs, full navigator objects, resource timing entries).
 *
 * @module     local_agentdetect/fingerprint
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const COMET_EXTENSION_ID = 'npclhjbddhklpbnacpjloidibaggcgon';

const initialWebdriverState = navigator.webdriver;

const KNOWN_EXTENSIONS = [
    {id: COMET_EXTENSION_ID, name: 'Comet Agent (Perplexity)', weight: 10, pattern: /comet.*agent|perplexity/i},
    {id: 'claudeinchrome', name: 'Claude in Chrome (MCP)', weight: 10, pattern: /claude.*mcp|mcp.*claude/i},
    {id: 'anthropic', name: 'Anthropic Browser Agent', weight: 10, pattern: /anthropic/i},
    {id: 'selenium', name: 'Selenium IDE', weight: 10, pattern: /selenium/i},
    {id: 'puppeteer', name: 'Puppeteer Recorder', weight: 9, pattern: /puppeteer/i},
    {id: 'playwright', name: 'Playwright Inspector', weight: 9, pattern: /playwright/i},
];

const AUTOMATION_GLOBALS = [
    {name: 'webdriver', weight: 10},
    {name: '__webdriver_evaluate', weight: 10},
    {name: '__selenium_evaluate', weight: 10},
    {name: '__fxdriver_evaluate', weight: 10},
    {name: '__driver_evaluate', weight: 10},
    {name: '__selenium_unwrapped', weight: 10},
    {name: '_phantom', weight: 9},
    {name: '__nightmare', weight: 9},
    {name: '_selenium', weight: 10},
    {name: 'callPhantom', weight: 9},
    {name: '__playwright', weight: 10},
    {name: '__puppeteer', weight: 10},
    {name: 'cdc_adoQpoasnfa76pfcZLmcfl_Array', weight: 10},
    {name: 'cdc_adoQpoasnfa76pfcZLmcfl_Promise', weight: 10},
    {name: 'cdc_adoQpoasnfa76pfcZLmcfl_Symbol', weight: 10},
];

const DOM_MARKERS = [
    {selector: '[data-mcp]', attribute: 'data-mcp', pattern: /.+/, name: 'MCP data attribute', weight: 10},
    {selector: '[data-claude]', attribute: 'data-claude', pattern: /.+/, name: 'Claude data attribute', weight: 10},
    {selector: '[data-anthropic]', attribute: 'data-anthropic', pattern: /.+/, name: 'Anthropic marker', weight: 10},
    {selector: '[data-selenium]', attribute: 'data-selenium', pattern: /.+/, name: 'Selenium marker', weight: 10},
    {selector: '[data-puppeteer]', attribute: 'data-puppeteer', pattern: /.+/, name: 'Puppeteer marker', weight: 9},
];

/**
 * Collect all technical fingerprint signals.
 *
 * @returns {Promise<Object>} Fingerprint data with detected signals and score.
 */
export const collect = async() => {
    const signals = {
        timestamp: Date.now(),
        webdriver: detectWebdriver(),
        headless: detectHeadless(),
        extensions: detectExtensions(),
        cometExtension: detectCometExtensionDom(),
        cometRuntime: detectCometRuntimeArtifacts(),
        globals: detectAutomationGlobals(),
        domMarkers: detectDomMarkers(),
        canvas: detectCanvasAnomalies(),
        webgl: collectWebGLInfo(),
    };

    signals.score = calculateFingerprintScore(signals);

    return signals;
};

/**
 * Detect WebDriver flag in navigator.
 *
 * @returns {Object} Detection results.
 */
const detectWebdriver = () => {
    const results = {detected: false, signals: []};

    if (navigator.webdriver === true) {
        results.detected = true;
        results.signals.push({name: 'navigator.webdriver', value: true, weight: 10});
    }

    if (navigator.webdriver === true && initialWebdriverState === false) {
        results.detected = true;
        results.signals.push({name: 'webdriver.changed_mid_session', value: true, weight: 10});
    }

    return results;
};

/**
 * Detect headless browser indicators.
 *
 * @returns {Object} Detection results.
 */
const detectHeadless = () => {
    const results = {detected: false, signals: []};

    if (navigator.plugins && navigator.plugins.length === 0) {
        results.signals.push({name: 'plugins.empty', value: true, weight: 6});
    }

    if (!navigator.languages || navigator.languages.length === 0) {
        results.signals.push({name: 'languages.empty', value: true, weight: 7});
    }

    if (window.chrome === undefined && /Chrome/.test(navigator.userAgent)) {
        results.signals.push({name: 'chrome.missing', value: true, weight: 8});
    }

    if (/HeadlessChrome|PhantomJS|SlimerJS/.test(navigator.userAgent)) {
        results.signals.push({name: 'useragent.headless', value: true, weight: 10});
    }

    if (window.outerWidth === 0 || window.outerHeight === 0) {
        results.signals.push({name: 'window.dimensions.zero', value: true, weight: 8});
    }

    results.detected = results.signals.some((s) => s.weight >= 7);

    return results;
};

/**
 * Detect automation-related global objects.
 *
 * @returns {Object} Detection results.
 */
const detectAutomationGlobals = () => {
    const results = {detected: [], signals: []};

    for (const global of AUTOMATION_GLOBALS) {
        if (global.name in window) {
            results.detected.push(global.name);
            results.signals.push({name: `global.${global.name}`, value: true, weight: global.weight});
        }
    }

    // CDP artifact keys on document.
    try {
        const docPropNames = Object.getOwnPropertyNames(document);
        for (const key of docPropNames) {
            if (/^(\$?cdc_|_cdc_|\$chrome_asyncScriptInfo)/.test(key)) {
                results.detected.push(key);
                results.signals.push({name: `document.cdp.${key}`, value: true, weight: 10});
            }
        }
    } catch (e) {
        // Ignore.
    }

    return results;
};

/**
 * Detect DOM markers injected by automation tools.
 *
 * @returns {Object} Detection results.
 */
const detectDomMarkers = () => {
    const results = {detected: [], signals: []};

    for (const marker of DOM_MARKERS) {
        const elements = document.querySelectorAll(marker.selector);
        for (const el of elements) {
            const value = el.getAttribute(marker.attribute);
            if (marker.pattern.test(value)) {
                results.detected.push(marker.name);
                results.signals.push({name: `dom.${marker.attribute}`, value: true, weight: marker.weight});
                break;
            }
        }
    }

    return results;
};

/**
 * Scan for known automation extensions via DOM class/id patterns and
 * extension-origin stylesheets. No network probing.
 *
 * @returns {Object} Detection results.
 */
const detectExtensions = () => {
    const results = {detected: [], signals: []};

    for (const ext of KNOWN_EXTENSIONS) {
        const elements = document.querySelectorAll(`[class*="${ext.id}"], [id*="${ext.id}"]`);
        if (elements.length > 0) {
            results.detected.push(ext.name);
            results.signals.push({name: `extension.dom.${ext.id}`, value: elements.length, weight: ext.weight});
        }
    }

    // Stylesheets injected by extensions.
    try {
        for (const sheet of document.styleSheets) {
            if (!sheet.href || !sheet.href.startsWith('chrome-extension://')) {
                continue;
            }
            const matchedExt = KNOWN_EXTENSIONS.find((ext) => ext.pattern.test(sheet.href));
            if (matchedExt) {
                results.detected.push(matchedExt.name);
                results.signals.push({
                    name: `extension.stylesheet.${matchedExt.id}`,
                    value: true,
                    weight: matchedExt.weight,
                });
            }
        }
    } catch (e) {
        // Cross-origin stylesheet.
    }

    return results;
};

/**
 * Detect Perplexity Comet extension presence via DOM/script/stylesheet scan.
 * Lite build avoids extension resource probing (no chrome-extension:// image loads).
 *
 * @returns {Object} Detection results.
 */
const detectCometExtensionDom = () => {
    const results = {detected: false, signals: []};

    try {
        if (sessionStorage.getItem('agentdetect_comet_detected') === 'true') {
            results.detected = true;
            results.signals.push({name: 'comet.extension.cached', value: true, weight: 10});
        }
    } catch (e) {
        // Ignore.
    }

    const scripts = document.querySelectorAll('script[src*="' + COMET_EXTENSION_ID + '"]');
    if (scripts.length > 0) {
        results.detected = true;
        results.signals.push({name: 'comet.extension.script_injected', value: true, weight: 10});
    }

    const links = document.querySelectorAll('link[href*="' + COMET_EXTENSION_ID + '"]');
    if (links.length > 0) {
        results.detected = true;
        results.signals.push({name: 'comet.extension.link_injected', value: true, weight: 10});
    }

    try {
        for (const sheet of document.styleSheets) {
            if (sheet.href && sheet.href.includes(COMET_EXTENSION_ID)) {
                results.detected = true;
                results.signals.push({name: 'comet.extension.stylesheet', value: true, weight: 10});
                break;
            }
        }
    } catch (e) {
        // Cross-origin.
    }

    if (results.detected) {
        try {
            sessionStorage.setItem('agentdetect_comet_detected', 'true');
        } catch (e) {
            // Ignore.
        }
    }

    return results;
};

/**
 * Detect Comet-specific runtime artifacts.
 *
 * @returns {Object} Detection results.
 */
const detectCometRuntimeArtifacts = () => {
    const results = {detected: false, signals: []};

    try {
        const styled = document.querySelectorAll(`[style*="${COMET_EXTENSION_ID}"]`);
        if (styled.length > 0) {
            results.detected = true;
            results.signals.push({name: 'comet.runtime.inline_style', value: true, weight: 10});
        }
    } catch (e) {
        // Ignore.
    }

    const cometGlobals = ['__comet__', '__perplexity__', '__pplx__', 'cometAgent', 'perplexityAgent'];
    for (const name of cometGlobals) {
        if (name in window) {
            results.detected = true;
            results.signals.push({name: 'comet.runtime.global', value: true, weight: 10});
        }
    }

    return results;
};

/**
 * Lightweight canvas check — does NOT store the data URL or a hash,
 * only flags if canvas rendering is suspiciously short (headless signal).
 *
 * @returns {Object} Anomaly results.
 */
const detectCanvasAnomalies = () => {
    const results = {anomalies: []};

    try {
        const canvas = document.createElement('canvas');
        canvas.width = 100;
        canvas.height = 30;
        const ctx = canvas.getContext('2d');
        ctx.textBaseline = 'top';
        ctx.font = '14px Arial';
        ctx.fillStyle = '#069';
        ctx.fillText('probe', 2, 2);
        const len = canvas.toDataURL().length;
        if (len < 1000) {
            results.anomalies.push({name: 'canvas.data.short', weight: 6});
        }
    } catch (e) {
        results.anomalies.push({name: 'canvas.error', weight: 5});
    }

    return results;
};

/**
 * Collect WebGL renderer signals (headless detection only).
 *
 * @returns {Object} WebGL information.
 */
const collectWebGLInfo = () => {
    const results = {anomalies: []};

    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');

        if (!gl) {
            results.anomalies.push({name: 'webgl.unavailable', weight: 5});
            return results;
        }

        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        if (debugInfo) {
            const renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
            const headlessRenderers = ['SwiftShader', 'llvmpipe', 'Mesa', 'Software'];
            for (const hr of headlessRenderers) {
                if (renderer.includes(hr)) {
                    results.anomalies.push({name: `webgl.renderer.${hr.toLowerCase()}`, weight: 8});
                }
            }
        }
    } catch (e) {
        // Ignore.
    }

    return results;
};

/**
 * Calculate overall fingerprint score.
 *
 * @param {Object} signals Collected signals.
 * @returns {number} Score 0-100.
 */
const calculateFingerprintScore = (signals) => {
    const allSignals = [
        ...(signals.webdriver.signals || []),
        ...(signals.headless.signals || []),
        ...(signals.extensions.signals || []),
        ...(signals.cometExtension?.signals || []),
        ...(signals.cometRuntime?.signals || []),
        ...(signals.globals.signals || []),
        ...(signals.domMarkers.signals || []),
        ...(signals.canvas.anomalies || []),
        ...(signals.webgl.anomalies || []),
    ];

    let totalWeight = 0;
    let maxWeight = 0;
    for (const signal of allSignals) {
        totalWeight += signal.weight || 0;
        maxWeight += 10;
    }

    if (maxWeight === 0) {
        return 0;
    }

    const rawScore = (totalWeight / Math.max(maxWeight, 50)) * 100;
    return Math.min(100, Math.round(rawScore));
};

export default {
    collect,
    KNOWN_EXTENSIONS,
    AUTOMATION_GLOBALS,
};
