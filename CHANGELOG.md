# Changelog

All notable changes to local_agentdetect (Lite) are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
