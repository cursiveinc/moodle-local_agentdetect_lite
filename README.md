# Agent Detection Lite (`local_agentdetect`)

[![Moodle Plugin CI](https://github.com/cursiveinc/moodle-local_agentdetect_lite/actions/workflows/ci.yml/badge.svg)](https://github.com/cursiveinc/moodle-local_agentdetect_lite/actions/workflows/ci.yml)

A lightweight Moodle local plugin that detects automated browser agents (e.g. Perplexity Comet) during quizzes.

This is the **Lite** edition: quiz-only, no DOM mutation observer, compact data collection and storage. A commercial Pro edition adds page mutation detection (AI helper extensions), autotyper identification, and expanded surface coverage.

## Detection approach

- **Browser fingerprinting** — WebDriver flag, headless indicators, CDP artifacts, known automation globals, basic WebGL/canvas anomaly checks.
- **Interaction analysis** — mouse movement, click precision, keystroke timing, scroll patterns, per-page mouse-to-click ratios.
- **Comet agent signals** — targeted checks for Perplexity's Comet browser (extension scripts / stylesheets / runtime globals, ultra-precise centre clicks, zero-keystroke sessions).

## Requirements

- Moodle 4.5 or later (uses the `core\hook\output\before_footer_html_generation` hook).
- PHP 8.1 or later.

## Installation

1. Copy the plugin directory into `local/agentdetect/` in your Moodle installation.
2. Visit **Site Administration → Notifications** to trigger the database install.
3. Configure at **Site Administration → Plugins → Local plugins → Agent Detection**.

## What Lite does *not* include

| Feature | Lite | Pro |
|---|---|---|
| Quiz interaction + fingerprint detection | ✅ | ✅ |
| Perplexity Comet signals | ✅ | ✅ |
| DOM mutation observer (AI helper extensions) | — | ✅ |
| Autotyper identification | — | ✅ |
| Assignment / forum / broader coverage | — | ✅ |
| Expanded fingerprint surface + extension probing | — | ✅ |

## Settings

| Setting | Description | Default |
|---|---|---|
| Enable agent detection | Master switch | Off |
| Detection threshold | Score above which sessions are flagged | 70 |
| Minimum report score | Only report signals at or above this score | 10 |
| Report interval (ms) | How often the browser reports to the server | 30000 |
| Debug mode | Browser console logging | Off |

Lite monitors quiz pages only (`mod-quiz-*`) by design.

## Capabilities

| Capability | Context | Default roles |
|---|---|---|
| `local/agentdetect:viewreports` | Course | Teacher, Editing teacher, Manager |
| `local/agentdetect:manageflags` | Course | Editing teacher, Manager |
| `local/agentdetect:viewsignals` | System | Manager |
| `local/agentdetect:configure` | System | Manager |

## Reports

- **Admin report**: Site Administration → Reports → Agent Detection (requires `viewsignals`).
- **Course report**: Course navigation → Reports → Agent Detection Report (requires `viewreports`).
- **Quiz badges**: Appear next to student names on quiz review pages when flags exist.

## Privacy

Stores user IDs, IP addresses, user agent strings, summary scores, and top-weighted anomaly names. Implements the Moodle privacy API for GDPR data export and deletion.

## Building the JS bundle

The plugin ships with pre-built `amd/build/*.min.js` files. To rebuild after editing `amd/src/*.js`:

```
npm install
node build.js
```

CI rebuilds via `moodle-plugin-ci grunt` on each push.

## License

GNU GPL v3 or later — see [COPYING](https://www.gnu.org/licenses/gpl-3.0.html).
