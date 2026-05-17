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
 * Signal manager for storing and processing detection signals.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_agentdetect;

/**
 * Manages storage and processing of agent detection signals.
 *
 * @package    local_agentdetect
 * @copyright  2026 Cursive Technology <joe@cursivetechnology.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signal_manager {
    /** @var int Threshold score for automatic flagging. */
    const FLAG_THRESHOLD_HIGH = 70;

    /** @var int Threshold score for suspicious activity. */
    const FLAG_THRESHOLD_SUSPICIOUS = 40;

    /** @var string Flag type for suspected agent usage. */
    const FLAG_SUSPECTED = 'agent_suspected';

    /** @var string Flag type for confirmed agent usage. */
    const FLAG_CONFIRMED = 'agent_confirmed';

    /** @var string Flag type for cleared users. */
    const FLAG_CLEARED = 'cleared';

    /** @var string Flag type for low-suspicion matches (score below high threshold). */
    const FLAG_LOW_SUSPICION = 'low_suspicion';

    /**
     * Store a detection signal and update flags if necessary.
     *
     * @param int $userid User ID.
     * @param int $contextid Context ID.
     * @param string $sessionid Detection session ID.
     * @param string $signaltype Type of signal.
     * @param array $data Signal data.
     * @return array Result with flag status.
     */
    public function store_signal(
        int $userid,
        int $contextid,
        string $sessionid,
        string $signaltype,
        array $data
    ): array {
        global $DB;

        // Extract scores from data.
        $fingerprintscore = $data['fingerprint']['score'] ?? $data['fingerprintscore'] ?? null;
        $interactionscore = $data['interaction']['score'] ?? $data['interactionscore'] ?? null;
        $combinedscore = $data['combinedScore'] ?? $data['combinedscore'] ?? null;
        $verdict = $data['verdict'] ?? null;

        // Safety net: if a partial payload arrives without combinedscore (e.g.
        // an older client sending an interaction-only unload beacon), fall back
        // to interactionscore so flagging still runs. Without this, a page
        // answered in less than reportInterval ms never raises a flag.
        if ($combinedscore === null && $interactionscore !== null) {
            $combinedscore = $interactionscore;
        }

        // Build the record.
        $record = new \stdClass();
        $record->userid = $userid;
        $record->contextid = $contextid ?: null;
        $record->sessionid = $sessionid;
        $record->signaltype = $signaltype;
        $record->fingerprintscore = $fingerprintscore;
        $record->interactionscore = $interactionscore;
        $record->combinedscore = $combinedscore;
        $record->verdict = $verdict;
        $record->signaldata = json_encode(self::compact_signal_data($data));
        $record->useragent = \core_useragent::get_user_agent_string() ?? '';
        $record->ipaddress = getremoteaddr();
        $record->timecreated = time();

        // Insert the signal record.
        $record->id = $DB->insert_record('local_agentdetect_signals', $record);

        // Fire signal_detected event.
        $context = $contextid ? \context::instance_by_id($contextid, IGNORE_MISSING) : null;
        if (!$context) {
            $context = \context_system::instance();
        }
        $event = \local_agentdetect\event\signal_detected::create([
            'objectid' => $record->id,
            'context' => $context,
            'relateduserid' => $userid,
            'other' => [
                'sessionid' => $sessionid,
                'signaltype' => $signaltype,
                'combinedscore' => $combinedscore,
                'verdict' => $verdict,
            ],
        ]);
        $event->trigger();

        // Update user flag if score warrants it.
        $flagstatus = $this->update_user_flag($userid, $contextid, $combinedscore, $sessionid);

        return [
            'signal_id' => $record->id,
            'flag_status' => $flagstatus,
        ];
    }

    /**
     * Reduce a signal payload to the minimum needed for scoring + reporting.
     *
     * Drops raw event arrays, per-page stats, canvas data URLs, navigator
     * details, and any fields not consumed by the admin or course reports.
     *
     * @param array $data Decoded signal data from browser.
     * @return array Compacted payload.
     */
    private static function compact_signal_data(array $data): array {
        $limit = static function (array $arr, int $max = 8): array {
            $out = [];
            foreach (array_slice($arr, 0, $max) as $item) {
                if (!is_array($item) && !is_object($item)) {
                    continue;
                }
                $item = (array) $item;
                $row = [];
                if (isset($item['name'])) {
                    $row['name'] = (string) $item['name'];
                }
                if (isset($item['weight'])) {
                    $row['weight'] = (int) $item['weight'];
                }
                $out[] = $row;
            }
            return $out;
        };

        $compact = [];

        if (isset($data['fingerprint']) && is_array($data['fingerprint'])) {
            $compact['fingerprint'] = [
                'score' => (int) ($data['fingerprint']['score'] ?? 0),
                'signals' => $limit($data['fingerprint']['signals'] ?? []),
            ];
        }
        if (isset($data['interaction']) && is_array($data['interaction'])) {
            $qtypes = $data['interaction']['questionTypes'] ?? [];
            if (!is_array($qtypes)) {
                $qtypes = [];
            }
            // Keep qtype list short and string-only; we only care about the
            // set of kinds present, not ordering or duplicates.
            $qtypes = array_values(array_unique(array_filter(
                array_map(static fn($t) => is_string($t) ? $t : null, $qtypes)
            )));
            $compact['interaction'] = [
                'score' => (int) ($data['interaction']['score'] ?? 0),
                'eventCounts' => $data['interaction']['eventCounts'] ?? null,
                'duration' => (int) ($data['interaction']['duration'] ?? 0),
                'pageLoadCount' => (int) ($data['interaction']['pageLoadCount'] ?? 0),
                'questionTypes' => $qtypes,
                'textInputFocusCount' => (int) ($data['interaction']['textInputFocusCount'] ?? 0),
                'anomalies' => $limit($data['interaction']['anomalies'] ?? []),
            ];
        }
        if (isset($data['comet']) && is_array($data['comet'])) {
            $compact['comet'] = [
                'detected' => (bool) ($data['comet']['detected'] ?? false),
                'score' => (int) ($data['comet']['score'] ?? 0),
                'signals' => $limit($data['comet']['signals'] ?? []),
            ];
        }

        foreach (['combinedScore', 'verdict', 'detectedAgent'] as $key) {
            if (isset($data[$key])) {
                $compact[$key] = $data[$key];
            }
        }

        return $compact;
    }

    /**
     * Update user flag based on detection score.
     *
     * @param int $userid User ID.
     * @param int|null $contextid Context ID.
     * @param int|null $score Combined detection score.
     * @param string $sessionid Session ID.
     * @return string Flag status.
     */
    protected function update_user_flag(
        int $userid,
        ?int $contextid,
        ?int $score,
        string $sessionid
    ): string {
        global $DB;

        if ($score === null || $score < self::FLAG_THRESHOLD_SUSPICIOUS) {
            return 'none';
        }

        $now = time();

        // The (userid, contextid) unique index does not constrain duplicate
        // NULL contextid rows because MySQL/MariaDB and PostgreSQL treat NULL
        // as distinct in unique indexes. Wrap the read-modify-write in a
        // delegated transaction so an in-flight failure cannot leave a
        // partially-written flag, and so concurrent writes on the same row
        // serialise on the DB rather than racing in PHP. Concurrent NULL-context
        // writes from the same user remain theoretically possible but the
        // window is tiny and the worst case is a single duplicate admin row.
        $transaction = $DB->start_delegated_transaction();
        try {
            // Check for existing flag.
            $conditions = ['userid' => $userid];
            if ($contextid) {
                $conditions['contextid'] = $contextid;
                $existingflag = $DB->get_record('local_agentdetect_flags', $conditions);
            } else {
                // For null context, we need special handling.
                $existingflag = $DB->get_record_select(
                    'local_agentdetect_flags',
                    'userid = :userid AND contextid IS NULL',
                    ['userid' => $userid]
                );
            }

            if ($existingflag) {
                // Update existing flag.
                $existingflag->detectioncount++;
                $existingflag->lastsessionid = $sessionid;
                $existingflag->timemodified = $now;

                if ($score > $existingflag->maxscore) {
                    $existingflag->maxscore = $score;
                }

                // Escalate flag type if score is high enough.
                $oldflagtype = $existingflag->flagtype;
                if ($score >= self::FLAG_THRESHOLD_HIGH && $existingflag->flagtype !== self::FLAG_CONFIRMED) {
                    $existingflag->flagtype = self::FLAG_SUSPECTED;
                }

                $DB->update_record('local_agentdetect_flags', $existingflag);
                $transaction->allow_commit();

                // Fire event if flag type was escalated (outside the
                // transaction so listeners don't run inside the critical
                // section).
                if ($existingflag->flagtype !== $oldflagtype) {
                    $this->trigger_user_flagged_event(
                        $userid,
                        $contextid,
                        $existingflag->id,
                        $existingflag->flagtype,
                        $existingflag->maxscore
                    );
                }

                return $existingflag->flagtype;
            }

            // Create new flag.
            $flag = new \stdClass();
            $flag->userid = $userid;
            $flag->contextid = $contextid ?: null;
            $flag->flagtype = $score >= self::FLAG_THRESHOLD_HIGH
                ? self::FLAG_SUSPECTED
                : self::FLAG_LOW_SUSPICION;
            $flag->maxscore = $score;
            $flag->detectioncount = 1;
            $flag->lastsessionid = $sessionid;
            $flag->timecreated = $now;
            $flag->timemodified = $now;

            $flagid = $DB->insert_record('local_agentdetect_flags', $flag);
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            $transaction->rollback($e);
        }

        // Fire user_flagged event outside the transaction.
        $this->trigger_user_flagged_event(
            $userid,
            $contextid,
            $flagid,
            $flag->flagtype,
            $flag->maxscore
        );

        return $flag->flagtype;
    }

    /**
     * Get all flags for a user.
     *
     * @param int $userid User ID.
     * @return array Array of flag records.
     */
    public function get_user_flags(int $userid): array {
        global $DB;

        return $DB->get_records('local_agentdetect_flags', ['userid' => $userid], 'timemodified DESC');
    }

    /**
     * Get flag for a specific user and context.
     *
     * @param int $userid User ID.
     * @param int|null $contextid Context ID.
     * @return \stdClass|false Flag record or false.
     */
    public function get_flag(int $userid, ?int $contextid) {
        global $DB;

        if ($contextid === null) {
            $sql = "userid = :userid AND contextid IS NULL";
            return $DB->get_record_select('local_agentdetect_flags', $sql, ['userid' => $userid]);
        }

        return $DB->get_record('local_agentdetect_flags', [
            'userid' => $userid,
            'contextid' => $contextid,
        ]);
    }

    /**
     * Get all flagged users, optionally filtered by context.
     *
     * @param int|null $contextid Context ID to filter by.
     * @param string|null $flagtype Flag type to filter by.
     * @param int $minscore Minimum score to include.
     * @return array Array of flag records with user info.
     */
    public function get_flagged_users(
        ?int $contextid = null,
        ?string $flagtype = null,
        int $minscore = 0
    ): array {
        global $DB;

        $sql = "SELECT f.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
                  FROM {local_agentdetect_flags} f
                  JOIN {user} u ON u.id = f.userid
                 WHERE f.maxscore >= :minscore";
        $params = ['minscore' => $minscore];

        if ($contextid !== null) {
            $sql .= " AND f.contextid = :contextid";
            $params['contextid'] = $contextid;
        }

        if ($flagtype !== null) {
            $sql .= " AND f.flagtype = :flagtype";
            $params['flagtype'] = $flagtype;
        }

        $sql .= " ORDER BY f.maxscore DESC, f.timemodified DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get signal history for a user.
     *
     * @param int $userid User ID.
     * @param int $limit Maximum records to return.
     * @return array Array of signal records.
     */
    public function get_user_signals(int $userid, int $limit = 100): array {
        global $DB;

        return $DB->get_records(
            'local_agentdetect_signals',
            ['userid' => $userid],
            'timecreated DESC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Get signals for a specific session.
     *
     * @param string $sessionid Session ID.
     * @return array Array of signal records.
     */
    public function get_session_signals(string $sessionid): array {
        global $DB;

        return $DB->get_records(
            'local_agentdetect_signals',
            ['sessionid' => $sessionid],
            'timecreated ASC'
        );
    }

    /**
     * Manually set a user flag.
     *
     * @param int $userid User ID to flag.
     * @param string $flagtype Flag type.
     * @param int|null $contextid Context ID.
     * @param string|null $notes Admin notes.
     * @param int $flaggedby User ID of admin setting the flag.
     * @return int Flag record ID.
     */
    public function set_flag(
        int $userid,
        string $flagtype,
        ?int $contextid = null,
        ?string $notes = null,
        int $flaggedby = 0
    ): int {
        global $DB;

        $existingflag = $this->get_flag($userid, $contextid);
        $now = time();

        if ($existingflag) {
            $existingflag->flagtype = $flagtype;
            $existingflag->notes = $notes;
            $existingflag->flaggedby = $flaggedby ?: null;
            $existingflag->timemodified = $now;

            $DB->update_record('local_agentdetect_flags', $existingflag);

            return $existingflag->id;
        } else {
            $flag = new \stdClass();
            $flag->userid = $userid;
            $flag->contextid = $contextid;
            $flag->flagtype = $flagtype;
            $flag->maxscore = 0;
            $flag->detectioncount = 0;
            $flag->notes = $notes;
            $flag->flaggedby = $flaggedby ?: null;
            $flag->timecreated = $now;
            $flag->timemodified = $now;

            return $DB->insert_record('local_agentdetect_flags', $flag);
        }
    }

    /**
     * Clear a user's flag.
     *
     * @param int $userid User ID.
     * @param int|null $contextid Context ID.
     * @param int $clearedby User ID of admin clearing the flag.
     * @return bool Success.
     */
    public function clear_flag(int $userid, ?int $contextid = null, int $clearedby = 0): bool {
        return $this->set_flag(
            $userid,
            self::FLAG_CLEARED,
            $contextid,
            get_string('flag:clearedbyadmin', 'local_agentdetect'),
            $clearedby
        ) > 0;
    }

    /**
     * Trigger the user_flagged event.
     *
     * @param int $userid The flagged user ID.
     * @param int|null $contextid Context ID.
     * @param int $flagid The flag record ID.
     * @param string $flagtype The flag type.
     * @param int $maxscore The maximum score.
     */
    protected function trigger_user_flagged_event(
        int $userid,
        ?int $contextid,
        int $flagid,
        string $flagtype,
        int $maxscore
    ): void {
        $context = $contextid ? \context::instance_by_id($contextid, IGNORE_MISSING) : null;
        if (!$context) {
            $context = \context_system::instance();
        }
        $event = \local_agentdetect\event\user_flagged::create([
            'objectid' => $flagid,
            'context' => $context,
            'relateduserid' => $userid,
            'other' => [
                'flagtype' => $flagtype,
                'maxscore' => $maxscore,
            ],
        ]);
        $event->trigger();
    }
}
