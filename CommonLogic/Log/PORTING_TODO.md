# Monolog 3 porting todo

## Processors (prerequisite for CSV writer)

Target pattern: each processor implements `Monolog\Processor\ProcessorInterface`, takes and returns a
`LogRecord`, and writes its value into `extra` under a key constant. Column order is decided by the
formatter (`COLUMNS`), not by the processors.

- [x] `IdProcessor` — move `context['id']` to `extra['id']`
  - [x] Make idempotent: `$record->context['id'] ?? $record->extra['id'] ?? null`
  - [x] Add `ProcessorInterface` and a `KEY` constant
  - [x] Create Abstract to generalize moving a context key to extra, with idempotent behaviour.
  - [x] Superseded by `LogEntry`: reads the ID from the log entry in the record context (key passed via
    constructor) and writes it to `extra[CsvLogConfig::KEY_ID]`. The context key and the CSV column are
    no longer coupled.
- [x] `RequestIdProcessor` — write request scope ID to `extra`; fix key casing (`requestid` → `requestId`)
- [x] `UserNameProcessor` — write user name to `extra`
- [x] `ActionAliasProcessor` — write action alias to `extra`
- [x] `ContextFilterProcessor` — remove keys via `$record->with(context: ...)` (`context` is readonly)
- [x] `DebugWidgetProcessor` — port to `LogRecord`; extract standalone detail-JSON generator for `MonitorLogHandler`
- [x] Remove `AbstractColumnPositionProcessor` once no subclass uses it
- [x] Use the processors' `KEY` constants in `CsvLogConfig::VERSIONS` columns
- [x] CSV formatter: strip all column keys from the column-10 `extra` JSON
- [x] **Timing:** processors on the inner CSV handler run at `FingersCrossedHandler` *flush* time, not log
  time. Buffered entries therefore get the user/action current at flush. Decide whether to push
  `UserNameProcessor`/`ActionAliasProcessor` (and `RequestIdProcessor`) onto the `FingersCrossedHandler`
  to capture log-time values. Pre-existing behaviour — document the decision either way.
  **Decision:** context processors run on the `FingersCrossedHandler` (log time); `ContextFilterProcessor`
  stays on the `CsvHandler` (flush time). See "Processor timing" in `MONOLOG_MIGRATION.md`.
- [ ] **Log-time processors must be cheap and never throw** (consequence of the timing decision): they now
  run for every collected record, including those discarded without a trigger, and an exception escapes
  from `Logger::log()`.
  - [x] `UserNameProcessor`: restore the Monolog 1 `isStarted()` guard and catch `\Throwable` (the port
    dropped the guard, so logging before the workbench/security is initialized throws).
  - [x] `RequestIdProcessor`: same guard; `getContext()` is unsafe before the workbench is started.
  - [x] `UserNameProcessor` can trigger authentication: if no token exists yet,
    `SecurityManager::getAuthenticatedToken()` lazily authenticates (remember-me), which may fire events and
    log recursively. Pre-existing in Monolog 1, but now happens at log time instead of flush. Fix: add a
    side-effect-free accessor (e.g. return the current token or NULL without authenticating) to the
    security manager and use it in the processor.
    **Done:** `SecurityManagerInterface::getAuthenticatedTokenIfExists()`. Records logged before the first
    authentication of a request now have an empty user name instead of the remembered/anonymous user.
  - [ ] Optional — reduce per-record cost: cache user name and action alias per request and refresh them on
    workbench events (authentication, action start/end) instead of resolving them for every record.
    Measure first; only worth it if profiling shows the lookups matter for debug-heavy requests.

## Version-aware reader (`LogCsvBuilder`)

Goal: render logs of *all* format versions through a single `exface.Core.LOG_ENTRY` object. The model
addresses columns by **name**; the query builder translates names to positions per file version using
`CsvLogConfig`. Old files are never migrated.

- [ ] Find the hook point in `CsvBuilder` / `CsvBuilderTrait::readCsv()`: a single place where data
  addresses are resolved to column indexes
- [ ] Create `QueryBuilders\LogCsvBuilder extends CsvBuilder`
  - [ ] Detect the version from the file name being read (`file_suffix` in `CsvLogConfig::VERSIONS`;
    no suffix = version 1)
  - [ ] Translate name → index via `CsvLogConfig::getColumns($version)` for **columns, filters and
    sorters** (filters currently use the numeric data address as alias, `CsvBuilder` line ~104)
  - [ ] Return `null` for columns that do not exist in the file's version
  - [ ] Take delimiter/enclosure/escape from `CsvLogConfig` instead of `DATA_ADDRESS_PROPS`
- [ ] Check how query builders are assigned (per data source, not per object); likely add a dedicated
  "log files" data source using `LogCsvBuilder` and point `LOG_ENTRY` at it
- [ ] Change `LOG_ENTRY` attribute DATA_ADDRESS from indexes (`0`–`10`) to column names
  (`Model/exface.Core.LOG_ENTRY/04_ATTRIBUTE.json`)
- [ ] Check `TRACE_LOG` entries: same reader, or keep them on plain `CsvBuilder`?
- [ ] Choose a file naming scheme so the `LOG` / `TRACE_LOG` file masks (`logs/*.log`,
  `logs/traces/*.csv`) match all versions, e.g. `<date>.v2.log`
- [ ] Writer side: build file names from `CsvLogConfig` in `LoggerFactory` and `Tracer`
- [ ] `LogCleaner`: validate field count and repair per version via `CsvLogConfig`
- [ ] `LogCleaner::cleanUpLogs()` (line ~128) derives the details folder via `pathinfo(PATHINFO_FILENAME)`.
  For `<date>.v2.log` that yields `<date>.v2` instead of `<date>` → expired v2 detail folders are never
  deleted. Strip the version suffix (shared helper in `CsvLogConfig`)
- [ ] Test: mixed v1 + current-version files render correctly, incl. filtering and sorting

## Row integrity

`CsvFormatter` sanitizes every field (invalid UTF-8 → U+FFFD, failed JSON → placeholder), so a
malformed value never drops a row. **Decision:** sanitize instead of throw-on-failure — this replaces the
original plan (step 3 in `MONOLOG_MIGRATION.md` is updated accordingly).

- [x] **Sanitizing in `CsvFormatter`** instead of `JSON_THROW_ON_ERROR`.
- [x] **Round-trip check in `CsvHandler`:** build the line via `fputcsv` into `php://memory`, parse it back
  with `str_getcsv()` using delimiter/enclosure/escape from `CsvLogConfig`, and compare against the
  formatted row. On mismatch, write a fallback row (id, datetime, level, `[row encoding failed]`).
- [x] **Locking in `CsvHandler`:** `useLocking` defaults to `true`; each line is written with one `fwrite`
  under `LOCK_EX`; the lock is released even if writing throws.
- [x] **Atomic detail files in `DebugMessageMonologHandler`:** temp file in the target folder → validate JSON →
  `link()` (no overwrite); invalid or missing log IDs throw. Durability on power loss is not guaranteed (no
  `fsync()`, by decision). Tested with 8 concurrent writers.
- [x] **Publish ordering (detail before CSV row):** composite handler in `MONOLOG_MIGRATION.md` step 4.
  **Done:** `CsvWithDetailsHandler` replaces the `GroupHandler`; record by record, also within
  `FingersCrossedHandler` batches. A failed detail file skips only its row (reported via `error_log()`).
  Still to verify with `stress.php` with kill (see Testing).
- [ ] **Decide the escape char** (blocked until `LogCsvBuilder` exists): the escape strategy in
  `MONOLOG_MIGRATION.md` must be proven against the reader that consumes new-version files, so it cannot
  be decided before the version-aware reader is ported. Candidate: `''` (no escape, enclosure doubling).
  With the current `\`, values ending in a backslash fail the round-trip and become fallback rows. Once
  decided: set `PRP_ESCAPE` for the current version in `CsvLogConfig` (resolves the TODO there) and remove
  the `str_replace('\\', '\\\\')` workaround in `MonologCsvFileHandler`, if no longer needed.
- [ ] **Readers:** `LogCsvBuilder` / `LogCleaner` should take `LOCK_SH` while reading, or ignore a trailing
  line without line ending.
- [ ] **Batches (optional):** override `CsvHandler::handleBatch()` to write a `FingersCrossedHandler` flush as
  one contiguous block under a single lock.
- [ ] **Checksum column (optional):** add a `crc32` of the remaining fields as a new column (requires a new
  version in `CsvLogConfig`). Lets `LogCsvBuilder` / `LogCleaner` detect truncated, interleaved or manually
  edited rows at read time. Only needed if `LogCleaner` should detect corruption beyond field count.

## Testing and Cleanup

Finalization checklist. The baseline oracle harness (step 1 in `MONOLOG_MIGRATION.md`) lives outside the repo, in the
Copilot session folder `files\oracle\` (copy it somewhere permanent if needed). Results from the
Monolog 1 baseline are in `out\`.

**Verify against the oracle**
- [ ] `corpus.php`: all 41 baseline-OK cases decode to the same values as `out\corpus\oracle.json`
      (compare decoded fields; ignore request ID, user, action and timestamp values).
- [ ] `corpus.php`: the 5 baseline-BROKEN cases now pass (`bslash_apos`, `bslash_apos_end`,
      `ctx_bad_utf8`, `ctx_bslash_apos`, `win_path_quoted`). Depends on the escape char decision; with
      `\` the backslash cases are expected to become fallback rows instead.
- [ ] Decide and document whether message backslash doubling (`handle()`) is kept.
- [ ] `stress.php` (8×300 ×3, 16×300 ×2): 0 missing, corrupted or unattributed rows
      (baseline lost ~1.8%).
- [ ] `stress.php` with kill (1 worker, several delays): no CSV row without a valid detail JSON.
- [ ] `repair_sim.php` / new repair on `corpus.log`: no valid multi-line record destroyed.
- [ ] Remaining test matrix rows and acceptance gate in `MONOLOG_MIGRATION.md` checked; unverified items marked as such.

**Remove temporary re-routes**
- [ ] Root `composer.json`: set `exface/core` back to `dev-1.x-dev as 1.0`.
- [ ] Root `composer.json`: remove the `hail-cookies/Core.git` `vcs` repository.
- [ ] `composer update exface/core` and confirm the lock points at `ExFace/Core` again.
- [ ] Confirm no harness files or oracle outputs were committed.

**PackageManager prerequisite (risk #10)**
- [ ] Design and implement out-of-process `StaticInstaller` entry points in `axenox/packagemanager`.
- [ ] Release PackageManager and roll it out to all installations before the Monolog 3 core.
- [ ] Run the pending `exface.Core` installer in `exface-m3` (out of process) and verify it succeeds.
- [ ] `composer update` **with scripts** in `exface-m3` completes without the psr/log fatal error.