# Changelog

All notable changes to local_agentdetect (Lite) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.4.2] - 2026-05-31

### Security — Third-party security review remediation ([MOO-12](https://linear.app/cursive-tech/issue/MOO-12))

- **🔴 High — Stored XSS via client-controlled `verdict`.** The verdict field
  was accepted as `PARAM_RAW` and rendered into the admin report's
  default-case branch without escaping; an authenticated user could store a
  JavaScript payload via a crafted beacon or AJAX call that fired when a
  manager later viewed the report. `signal_manager::store_signal()` now
  rejects any verdict outside the `VERDICT_*` allowlist with
  `invalid_parameter_exception`. `signaltype` is similarly enum-checked
  (`fingerprint` / `interaction` / `combined` / `unload`). Both
  `report.php` and `coursereport.php` now escape any unknown verdict in
  their fallback render path as defense in depth for any legacy stored
  rows.
- **🟠 Medium — Course report & AJAX flag lookup did not enforce target-user
  visibility or group boundaries.** A teacher with
  `local/agentdetect:viewreports` could load the per-student detail page
  for any user ID, and the AJAX `local_agentdetect_get_user_flags`
  service trusted any user ID list the caller supplied. The detail path
  now requires the target user to be enrolled in the course and visible
  under the active group mode (`is_enrolled` + `groups_user_groups_visible`).
  The AJAX service filters the input user-ID list to course-visible
  participants before querying, and no longer returns NULL-context
  (system-level) records to course-scoped callers.
- **🟠 Medium — Course detail view could surface signal data from another
  context with the same browser session ID.** The second query that loaded
  the highest-scoring combined record per session filtered only by
  `sessionid` + `userid`. Browser session IDs persist for ~30 minutes and
  can span multiple courses, so the query could pull a record tagged with
  a context the viewer had no access to and render its verdict /
  explanations on the current report. The query now also restricts
  `s.contextid` to the course's context tree.

### Added

- New language string `error:cannotviewuser` for the visibility-rejection
  path in the course report detail view.
- Regression tests covering verdict + signaltype allowlist rejection,
  `get_user_flags` participant filtering and NULL-context gating, and
  course-report detail access control under enrolment + separate groups.

## [0.4.1] - 2026-05-17

### Fixed — Moodle 5.0 debugging warnings

- `fullname()` debugging notice "name fields are missing from the user object"
  on the Signals page. Added `firstnamephonetic`, `lastnamephonetic`, `middlename`,
  and `alternatename` to all user-record SELECTs feeding into `fullname()`
  (admin signals report, course report, signal manager).
- `Undefined property: stdClass::$value` and `round(): Passing null` warnings
  on the Signals page details column. Anomaly fields are now guarded with
  null-coalescing defaults before being passed to `round()`.

### Fixed — Third-party security audit

- **Beacon endpoint missing context access check.** `beacon.php` now calls
  `require_login()` against the course/cm derived from the submitted
  `contextid`, mirroring the external function path. Prevents authenticated
  users from posting signals tagged with contexts they have no access to.
- **Double HTML-escaping of course/module names** in the context column
  caused names containing `&`, `<`, or `>` to render as literal entities
  (`R&amp;D 101` instead of `R&D 101`). Removed redundant `s()`/`format_string()`
  wrapping and now truncate raw values before single-encoding for output.
- **Dead `pageUrl`/`pageTitle` references** in the admin signals report
  referenced fields the Lite client never emits. Removed the table column,
  link-rendering block, JSON download keys, and the unused `$string['page']`
  language string.
- **`update_user_flag()` read-modify-write** now runs inside a delegated
  transaction. The `(userid, contextid)` unique index can't constrain
  duplicate NULL-context rows on MySQL/MariaDB or PostgreSQL, so the
  transaction provides atomicity against in-flight failures and partial
  serialisation against concurrent writes.
- **Magic string `'low_suspicion'`** is now a class constant
  (`signal_manager::FLAG_LOW_SUSPICION`) alongside `FLAG_SUSPECTED`,
  `FLAG_CONFIRMED`, and `FLAG_CLEARED`. PHP literals replaced; the JS
  badge renderer and language key still match the string value.

### Changed — Audit hardening (informational findings)

- **Privacy provider** no longer unconditionally adds the system context to
  every user's contextlist. `get_contexts_for_userid()` checks for NULL-context
  signals or flags before adding it, so privacy tooling no longer reports
  "data exists at system context" for users with none.
- **Backup definition** no longer carries `useragent` or `ipaddress` fields
  in signal records. Personal identifiers do not need to travel with course
  backups; per-user export and deletion of these fields on the live database
  remains covered by the Privacy API.

### Removed

- `fix-indent.js` — one-off developer utility that was never executed at
  runtime and not referenced from any other file. Should not have been in
  distributable releases.

## [0.4.0] - 2026-04-19

- Initial baseline used for this changelog.
