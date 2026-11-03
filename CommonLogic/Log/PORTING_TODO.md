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

- [x] Find the hook point in `CsvBuilder` / `CsvBuilderTrait::readCsv()`: a single place where data
  addresses are resolved to column indexes. **Done:** the trait got three hooks with backward-compatible
  defaults: `getCsvHeader()` (passed to `Statement::process()` in `readCsv()` and `countRowsFiltered()`),
  `isCsvColumnAddress()` (replaces `is_numeric()`), `getEscape()` (`setEscape()` only if not `null`).
  `CsvBuilder::read()`/`count()` now go through `queryFile()`, so subclasses can choose the file.
- [x] Create `QueryBuilders\LogCsvBuilder extends CsvBuilder`
  - [x] Detect the version from the file name being read (`CsvLogConfig::getVersionFromFileName()`)
  - [x] Map columns by **name** for columns, filters and sorters: league/csv keys every record by the
    header from `getCsvHeader()` (the version's columns, then all other known columns), so data addresses,
    filter and sorter aliases are column names - no name → index translation needed
  - [x] Return `null` for columns that do not exist in the file's version (league/csv pads the header)
  - [x] Take delimiter/enclosure/escape from `CsvLogConfig` instead of `DATA_ADDRESS_PROPS`; no header rows
- [x] Check how query builders are assigned (per data source, not per object): new data source
  `LOG_FILES` ("Log files", `LogCsvBuilder`, same connection as `LOCAL_CSV_FILE`, read-only) in
  `Model/05_DATASRC.json`; `LOG_ENTRY` points to it, its `DATA_ADDRESS_PROPS` were removed
- [x] Change `LOG_ENTRY` attribute DATA_ADDRESS from indexes (`0`–`9`) to column names
  (`Model/exface.Core.LOG_ENTRY/04_ATTRIBUTE.json`; `levelname` → `level_name`, `timestamp` → `datetime`)
- [ ] Verify after the model import (installer, out of process): `LOG_ENTRY` reads through `LogCsvBuilder`,
  error dialog log tab and `Debugger::getLogData()` work for legacy and versioned files.
  **Left to the developer** (decision: no DB changes by Copilot); run the core installer, then check the
  error dialog's log tab, "Open trace" and the log viewer, each with a legacy and a `.temp` file.
- [x] Check `TRACE_LOG` entries: same reader, or keep them on plain `CsvBuilder`?
  **Decision: no change.** `TRACE_LOG` is a file listing (`FileBuilder`) that extracts action/URL/page/user
  with regexes on `"` - unaffected by enclosure/escape and by the version key (`*.csv` mask, sorting by name
  still works). The entries of a trace ("Open trace" = `DEFAULT_EDITOR_UXON`, `Debugger::getLogData()`) are
  read through `LOG_ENTRY` with `logfile` = the trace file, i.e. already through `LogCsvBuilder`.
- [x] Choose a file naming scheme so the `LOG` / `TRACE_LOG` file masks (`logs/*.log`,
  `logs/traces/*.csv`) match all versions: `<base>[.<key>].<extension>`, documented in `CsvLogConfig`.
- [x] Writer side: build file names from `CsvLogConfig` in `LoggerFactory` and `Tracer`
  (`CsvLogConfig::buildFileName()`). New files are named `<date>.temp.log` /
  `<time>.temp.csv` until the key is renamed (see Testing and Cleanup).
- [ ] `MONITOR_ERROR.LOG_FILE` builds the path in SQL as `logs/<date>.log` and is used as `logfile` filter
  on `LOG_ENTRY` (log entries tab of the error dialog). Versioned files are not found, so the tab stays
  empty for new errors. **Decision (fallback):** `LogCsvBuilder` reads the requested file if it exists,
  otherwise the file of the same base name from `CsvLogConfig::findFiles()` (newest version). The SQL stays
  unchanged, so old rows keep working. One file per query - no merging of several files.
  **Implemented** in `LogCsvBuilder::queryFile()` (resolved before querying, so a connection with
  `error_if_file_not_found` does not throw); `logfile` of the rows is the file actually read. Remaining:
  verify in the UI after the model import.
- [x] **One file per date:** `CsvLogConfig::getVersionForWriting()` + `LoggerFactory` continue an existing
  file of today in its (older) version, so a new format starts with the next day's log. This makes the
  fallback exact: on the upgrade day there is no second file the monitor could miss. The format is derived
  from the file name (`MonologCsvFileHandler`), `CsvFormatter`/`CsvHandler` take the version.
  Limitation: a process started *before* the deploy keeps writing its legacy file, so it can still create
  a legacy file after a new-version file of the same date exists (only if it logs first that day).
- [x] Reader escape char: `CsvBuilderTrait::initCsvReader()` never called `setEscape()`, so it read with
  league/csv's default `\`. **Done:** `LogCsvBuilder::getEscape()` returns `CsvLogConfig::getEscape()`.
- [x] **Pre-existing `CsvBuilder` bug - pagination with offset:** `readCsv()` let the statement skip the
  offset *and* capped the rows at `limit + offset`, so `limit 1, offset 1` returned 2 rows; with any filter
  `CsvBuilder` did not paginate at all (page 2 = pages 1 + 2). **Fixed in `CsvBuilderTrait`:** header rows
  are excluded in the statement's `where` (by record offset, also for counting), the statement paginates
  after filtering/sorting, output is capped at `limit`. `$readAll` (used by `CsvUrlBuilder`, whose caller
  paginates) still returns the first `offset + limit` rows. `CsvBuilder::read()` always paginates in the
  reader now. Side fixes: header rows were not skipped with offset > 0 and could be filtered away before
  the offset; `countCsvRows()` crashed on missing files.
- [x] `LogCleaner`: validate field count and repair per version via `CsvLogConfig`. **Done** together with
  the parser-based repair (`MONOLOG_MIGRATION.md` step 6, risk #9):
  - Parses logical records with `fgetcsv()` and the version's settings, so valid multi-line records are kept
    byte by byte.
  - `isValidLogRecord()`: field count of the version, ID without whitespace, known numeric level with a
    matching `level_name`, date-time prefix + `strtotime()`, `context`/`extra` empty (fallback rows) or valid JSON.
  - **Quarantine (decision: removed records only):** broken records are appended as raw bytes to
    `logs/quarantine/<log file>` *before* the rewrite, so a repair can be undone by appending them back. If
    the rewrite fails (file open on Windows), the quarantine append is rolled back. Quarantine files expire
    with their log file. Reports logical record numbers.
  - **Active files (decision: keep current behaviour):** files are still rewritten via temp file + rename,
    even if a process still writes to them (on Linux its next rows go to the replaced file).
  - No `trim()` anymore: the old repair removed the last line break, so the next row was glued to the last one.
  - Limitation: rows that parse correctly but carry values mangled by the legacy escaping (3 of the 5
    baseline-broken corpus cases) are structurally valid and kept.
  - Tested with harness `cleaner.php`: oracle corpus (all 41 valid rows decode identically, 2 broken records
    quarantined, no bytes lost, idempotent), synthetic versioned file, rollback, retention.
- [x] `LogCleaner::cleanupLogsAndDetails()` derived the details folder via `pathinfo(PATHINFO_FILENAME)`,
  i.e. `<date>.temp` for versioned files, so their expired detail folders were never deleted. Now uses
  `CsvLogConfig::getBaseName()`.
- [ ] Test: mixed v1 + current-version files render correctly, incl. filtering and sorting.
  **Query builder level done** (harness `reader.php`: legacy + versioned file, enclosure/escape round
  trip, filter, sorter, pagination incl. combinations and header rows, count, fallback with relative and
  absolute paths, missing file, today's real log). Still open on data sheet / UI level after the model import.

## Levels

- [x] **`LogLevelDataType`** (was missing from this list): `compareLogLevels()` subtracted `Level` enums
  (`TypeError`, disabled `MonitorLogHandler` on the first log), `convertToMonologLevel()` returned a `Level`
  instead of `int`, `getValuesOfMonologLevels()` used the removed `Logger::getLevels()`. Ported to
  `Level->value` / `toPsrLogLevel()` / `Level::cases()`; public signatures unchanged, so the callers
  (`Logger`, `MonitorLogHandler`, `ExceptionTrait`, `axenox/etl` `StepNote`) are untouched. E2E harness B4 passes now.

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
- [ ] **Decide the escape char** (unblocked: `LogCsvBuilder` exists and reads with the per-version escape
  from `CsvLogConfig`): the escape strategy in
  `MONOLOG_MIGRATION.md` must be proven against the reader that consumes new-version files, so it cannot
  be decided before the version-aware reader is ported. Candidate: `''` (no escape, enclosure doubling).
  With the current `\`, values ending in a backslash fail the round-trip and become fallback rows. Once
  decided: set `PRP_ESCAPE` for the current version in `CsvLogConfig` (resolves the TODO there) and remove
  the `str_replace('\\', '\\\\')` workaround in `MonologCsvFileHandler`, if no longer needed.
- [ ] **Readers:** `LogCsvBuilder` / `LogCleaner` should take `LOCK_SH` while reading, or ignore a trailing
  line without line ending. (`LogCleaner` now quarantines an incomplete trailing record unless it happens to
  be valid; `LogCsvBuilder` shows it padded with NULLs.)
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
- [x] `repair_sim.php` / new repair on `corpus.log`: no valid multi-line record destroyed
      (verified with harness `cleaner.php`, see `LogCleaner` above).
- [ ] Remaining test matrix rows and acceptance gate in `MONOLOG_MIGRATION.md` checked; unverified items marked as such.

**Remove temporary re-routes**
- [ ] `CsvLogConfig`: rename the placeholder version key `temp` (e.g. to `v2`) before release. Dev files
  named `*.temp.log` / `*.temp.csv` are then read as legacy (same layout, unless the escape char changed).
- [ ] Root `composer.json`: set `exface/core` back to `dev-1.x-dev as 1.0`.
- [ ] Root `composer.json`: remove the `hail-cookies/Core.git` `vcs` repository.
- [ ] `composer update exface/core` and confirm the lock points at `ExFace/Core` again.
- [ ] Confirm no harness files or oracle outputs were committed.

**PackageManager prerequisite (risk #10)**
- [ ] Design and implement out-of-process `StaticInstaller` entry points in `axenox/packagemanager`.
- [ ] Release PackageManager and roll it out to all installations before the Monolog 3 core.
- [ ] Run the pending `exface.Core` installer in `exface-m3` (out of process) and verify it succeeds.
- [ ] `composer update` **with scripts** in `exface-m3` completes without the psr/log fatal error.