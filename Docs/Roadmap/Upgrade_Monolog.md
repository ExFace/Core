# Upgrade the workbench logger to Monolog 3

## Goal

Upgrade the workbench logger from Monolog 1 to the newest Monolog 3 release while preserving the
existing log storage contract. At the time of planning, the current release is Monolog 3.12.0.

This is not a change from file logging to another storage system. Daily CSV log files must remain
readable as `exface.Core.LOG_ENTRY` through the metamodel and `CsvBuilder`. The JSON detail files are
part of that contract, not optional diagnostic output: the UI and other consumers use the log ID in a
CSV row to load `log/details/<date>/<log-id>.json`.

## Non-negotiable compatibility contract

- Keep one daily `<date>.log` CSV file and the matching `details/<date>/` directory.
- Keep comma as delimiter, apostrophe (`'`) as enclosure, no header row, and UTF-8 text.
- Keep the physical column order below. The first ten columns are mapped by
	`exface.Core.LOG_ENTRY`; column 10 is retained for compatibility even though it is not modeled.

| Index | Value |
|---:|---|
| 0 | Log ID |
| 1 | Request ID |
| 2 | User name |
| 3 | Action alias |
| 4 | Message |
| 5 | Context as JSON |
| 6 | Numeric Monolog level |
| 7 | Uppercase level name |
| 8 | Channel |
| 9 | Timestamp in `Y-m-d H:i:s.v` format |
| 10 | Extra data as JSON |

- Preserve the current buffering semantics: collect records from `LOG.MINIMUM_LEVEL_TO_LOG`, flush
	the buffer at `LOG.PERSIST_LOG_LEVEL`, and independently persist records at
	`LOG.PASSTHROUGH_LOG_LEVEL`.
- Every persisted CSV row must keep the same log ID as its detail JSON filename.
- A CSV row must not be published until its detail file has been written successfully. Existing
	detail files for the same log ID must never be truncated or appended to.
- Existing log and detail files must remain readable; no data migration is required for old files.
- Keep the PHP `error_log` fallback operational when initialization or file persistence fails.

## Current implementation and risks

The default logger is assembled in `LoggerFactory` and delegates to `MonologCsvFileHandler`. That
handler combines the third-party `femtopixel/monolog-csvhandler`, a custom detail-file handler,
processors, `GroupHandler`, and `FingersCrossedHandler`.

The following must be addressed as one migration:

1. `femtopixel/monolog-csvhandler` requires Monolog 1 and PSR Log 1. It cannot be retained with
	 Monolog 3. Its CSV schema also depends on the incidental array order produced by
	 `NormalizerFormatter`.
2. Monolog 3 passes immutable `LogRecord` objects to handlers, formatters, and processors. The custom
	 code currently accepts and mutates record arrays, including adding arbitrary top-level fields.
3. Monolog 3 uses the `Level` enum. Calls to the old integer conversion helpers and assumptions about
	 integer handler levels must be replaced deliberately.
4. Monolog 3.12 requires PHP 8.1 or newer and PSR Log 2 or 3, while core currently declares PHP
	 `^8`.
5. The current CSV handler calls `fputcsv()` without enabling stream locking. Concurrent web
	 requests are a plausible cause of partial or interleaved records and must be tested before and
	 after the change.
6. The current repair code reads physical lines and judges them by comma count. That is not CSV
	 parsing: quoted commas and valid embedded newlines make the result unreliable, and a repair can
	 remove valid data.
7. Detail files are checked with `file_exists()` and then streamed directly. This check/write race
	 and a process interruption can leave a partial JSON file.

## Implementation plan

### 1. Establish a reproducible baseline

Before changing dependencies, create a temporary diagnostic harness around the current logger. Do
not commit the harness or generated logs.

- Write records containing empty values, commas, apostrophes, double quotes, backslashes, CR, LF,
	CRLF, tabs, Unicode, long messages, nested context arrays, exceptions, and values that require
	JSON normalization.
- Parse the result with the same `league/csv` settings used by `CsvBuilder`, not with `explode()` or
	physical-line counting. Assert exactly eleven fields and verify every value after round-trip.
- Verify that every row has valid context/extra JSON and a parseable timestamp and level.
- Verify that every row resolves to valid JSON in `details/<date>/<id>.json` and that the file
	renders in the existing log-details dialog.
- Run multiple PHP workers writing distinctive records to the same file. Record whether rows become
	truncated, interleaved, duplicated, or detached from their detail file.
- Keep representative output as the compatibility oracle for the new implementation, but do not
	copy malformed output into fixtures.

This check can disprove the current working hypothesis that concurrency and CSV escaping are the
main corruption sources. If corruption is reproducible only during shutdown, buffering, disk-full,
or process termination scenarios, adapt the implementation in step 3 to that measured failure
mode.

### 2. Resolve platform and Composer prerequisites

- Confirm that all supported ExFace installations run PHP 8.1 or newer. Raise the core requirement
	from `php: ^8` to an explicit PHP 8.1+ constraint. If PHP 8.0 must remain supported, Monolog 3 is
	not a valid target and this roadmap must stop until that support decision changes.
- Use `composer why-not monolog/monolog 3.12.0` and the equivalent PSR Log check in a complete root
	installation to find transitive blockers before editing the lock file.
- Require `monolog/monolog:^3.12` and allow the compatible PSR Log major selected by the complete
	dependency graph.
- Remove `femtopixel/monolog-csvhandler`; do not fork its Monolog 1 implementation merely to satisfy
	type signatures.
- Update dependencies from the application root and review the complete Composer diff for unrelated
	upgrades.

### 3. Introduce a first-party CSV writer with an explicit schema

Add a core-owned Monolog 3 handler and formatter under `CommonLogic/Log/Monolog`. Their public
responsibility is to write the exact eleven columns documented above; they must not serialize a
generic `LogRecord::toArray()` result.

- Normalize each field explicitly. Convert `Level` to its numeric value and uppercase name and
	format the immutable timestamp explicitly.
- Encode context and extra as JSON with exceptions on encoding failure. Remove `exception` and
	`sender` only from the CSV context representation; leave the original record available to detail
	generation.
- Continue using comma and apostrophe so no metamodel change is needed. Select the `fputcsv()` escape
	argument explicitly and prove apostrophe/backslash round-trips with `league/csv`. Prefer an empty
	proprietary escape character and standard enclosure doubling if the reader compatibility check
	succeeds; otherwise document and test the required legacy escape mode.
- Enable exclusive stream locking around each complete CSV write. Treat a short or failed write as
	an exception so the workbench fallback logger can report it.
- Keep one append operation per record and avoid manual concatenation or replacement of delimiters,
	enclosures, and line breaks.
- Make directory creation and permission failures visible through the existing fallback logger.

This isolates the metamodel-facing file format from future Monolog record-layout changes.

### 4. Make detail persistence atomic and preserve row/detail integrity

Adapt `DebugMessageMonologHandler` to Monolog 3, but strengthen its persistence contract at the same
time.

- Generate the complete detail JSON before touching the final path.
- Write to a uniquely named temporary file in the target directory, flush and close it, validate it
	with `json_decode(..., JSON_THROW_ON_ERROR)`, and atomically rename it to `<log-id>.json`.
- Use exclusive creation/locking so concurrent attempts for the same ID cannot combine content.
	Treat an already complete, valid final file as success; never append to or overwrite it.
- Couple detail and CSV persistence in a core-owned composite handler: persist/confirm the detail
	file first and append the CSV row only afterward. Wrap this composite in the existing
	`FingersCrossedHandler` so buffering and passthrough behavior remain unchanged.
- If CSV append fails after the detail write, an orphan detail file is acceptable and can be cleaned
	by retention. A CSV row with a missing or partial detail file is not acceptable.
- Verify fallback detail generation for startup/installation failures, exceptions, and ordinary
	messages without a sender.

### 5. Port all Monolog integration points to `LogRecord` and `Level`

- Convert `IdProcessor`, `RequestIdProcessor`, `UserNameProcessor`, `ActionAliasProcessor`, and
	`ContextFilterProcessor` to accept and return `LogRecord`. Store custom enrichment in `extra` or
	let the explicit formatter derive it; do not invent arbitrary `LogRecord` properties.
- Convert `DebugWidgetProcessor` to a record-aware adapter while extracting a separate method for
	generating detail JSON. `MonitorLogHandler` currently invokes this processor with a hand-built
	array and should call the explicit generation method instead.
- Change `DebugMessageMonologHandler::write()` to the Monolog 3 signature and update handler calls to
	pass `LogRecord` objects.
- Update `MessageOnlyFormatter` to the Monolog 3 `FormatterInterface` signatures and return types.
- Replace uses of `Logger::toMonologLevel()`, `Logger::getLevelName()`, and `Logger::getLevels()` in
	handlers and `LogLevelDataType` with `Level` conversions. Preserve the existing public behavior:
	PSR-3 strings at ExFace interfaces and numeric values only where the metamodel requests them.
- Verify `MonologErrorLogHandler`, `ErrorHandler::register()`, `ErrorLogHandler`, `GroupHandler` or its
	replacement, and the exact Monolog 3 `FingersCrossedHandler` constructor semantics.
- Keep the ExFace `LoggerInterface` and sender argument stable; this upgrade must not leak Monolog
	types into callers.

### 6. Replace line-based repair with parser-based validation

Keep repair available during rollout, but make it understand the same format as the reader.

- Parse logical CSV records with `league/csv` using comma and apostrophe, including records with
	embedded newlines.
- Validate exactly eleven physical fields, required identifiers, numeric/known level, timestamp,
	and JSON in context/extra. Also verify the referenced detail file when the date can be resolved.
- Before repair, create a backup or quarantine copy. Rewrite valid records to a temporary file and
	atomically replace the original while holding an exclusive lock.
- Never log the repair result back into the file currently being scanned or rewritten.
- Report rejected logical record numbers and reasons, not physical line numbers.
- Keep repair for at least one release after migration. Remove or reduce it only after production
	monitoring shows that new files remain valid; do not use its presence to hide writer defects.

### 7. Verification and rollout

Run the baseline harness against the new implementation and compare behavior rather than raw bytes.
Core does not currently maintain unit tests, so use the existing Behat package where a suitable
integration scenario exists and retain a documented manual verification matrix for this migration.

Acceptance criteria:

- Composer installs Monolog 3.12.x without ignored platform requirements and without the FemtoPixel
	package.
- PHP syntax checks pass for every changed PHP file, and normal workbench startup produces no
	deprecations or handler initialization fallback.
- All adversarial values round-trip through the generated CSV and `CsvBuilder` with exactly eleven
	fields per logical record.
- The concurrent-writer stress run produces the expected record count, unique IDs, no malformed
	records, and no missing/invalid detail JSON.
- Debug, passthrough, and trigger-level scenarios preserve current `FingersCrossedHandler` behavior.
- The log viewer can filter/sort the same modeled columns and open details for ordinary messages,
	exceptions, and fallback messages.
- Existing pre-upgrade `.log` and detail files remain readable in the same UI.
- Simulated unwritable directory, disk/write failure, malformed UTF-8/JSON, and interrupted detail
	write scenarios reach the PHP error-log fallback without publishing a dangling CSV row.
- Cleanup removes expired daily logs and matching detail directories, and parser-based repair does
	not remove valid multiline records.

Deploy first to a test environment with representative concurrent traffic. Track fallback logger
events, repair detections, invalid JSON, rows without details, and orphan detail files for at least
one retention/cleanup cycle before general rollout. Roll back the code and Composer lock together;
the unchanged on-disk contract allows the previous version to continue reading files created by the
new logger.

## Testing

### Test setup and evidence

Run the same temporary integration harness against the current logger before the upgrade and against
the new logger afterward. Exercise the logger assembled by `LoggerFactory`, not just the formatter,
so processors, buffering, detail generation, persistence, and fallback handling are covered together.
Use an isolated log directory and a separate PHP error-log destination; never inject failures into
production logs. Preserve representative valid pre-upgrade files for backward-read checks.

Compare decoded values and observable behavior, not raw file bytes. Record the PHP and dependency
versions, logger configuration, worker count, expected and actual record counts, rejected records,
and fallback events for each run. Baseline defects are evidence to investigate, not behavior to
preserve. Do not commit the temporary harness or generated logs. Retain the verification procedure
and a results summary; reuse existing Behat scenarios where suitable rather than introducing a core
unit-test suite.

### CSV and detail integrity

Use the adversarial values listed in implementation step 1, including combinations of apostrophes,
backslashes, commas, and embedded newlines. Include ordinary messages, nested context, exceptions,
sender-backed debug messages, and messages without a sender.

- Read logical records with the exact `league/csv` delimiter, enclosure, and escape settings used by
	`CsvBuilder`, then verify access through `exface.Core.LOG_ENTRY` as well.
- Assert exactly eleven fields in the documented order, expected identifiers and enrichment, valid
	context/extra JSON, the numeric level and matching uppercase name, and the timestamp format.
- Compare decoded values with the expected normalized input. Verify that `exception` and `sender`
	are excluded only from CSV context and remain available for detail generation.
- Resolve every row's log ID to its date-specific detail file and decode it with
	`JSON_THROW_ON_ERROR`. Confirm the generated details render in the existing dialog.

### Buffering, passthrough, and shutdown

Choose test thresholds that distinguish collection, trigger, and passthrough behavior. Use unique
message markers and inspect files both before shutdown and after the worker exits.

| Scenario | Required result |
|---|---|
| Record below `LOG.MINIMUM_LEVEL_TO_LOG` | Not collected or persisted. |
| Collected records without a trigger | Remain buffered; shutdown persists only records eligible under the configured passthrough behavior. |
| Buffered records followed by `LOG.PERSIST_LOG_LEVEL` | Trigger flushes the eligible buffer, including the triggering record. |
| Record eligible for `LOG.PASSTHROUGH_LOG_LEVEL` without a trigger | Persisted independently of trigger activation, at the lifecycle point established by the baseline. |
| Passthrough-eligible record followed by a trigger | Persisted exactly once, not duplicated by later flushing or shutdown. |
| Records emitted after activation | Persistence matches the configured `FingersCrossedHandler` behavior. |

Test threshold boundaries, normal shutdown, and exception-driven shutdown. Check row/detail
integrity in every scenario. Document the observed passthrough timing rather than assuming that
passthrough means an immediate write.

### Concurrent persistence

Launch multiple PHP workers writing distinctive records into the same daily file. Record worker
completion and compare the expected marker set with the parsed result: no missing, duplicate,
truncated, or interleaved records, unique generated IDs, and valid matching details for every row.
Repeat the run to exercise contention rather than relying on one successful attempt.

Separately exercise concurrent detail persistence using the same log ID. Start with no final file,
then repeat with an existing valid file. Assert that the final JSON is complete and that an existing
file's bytes never change. This checks detail-file exclusivity, not CSV row deduplication.

### Failure handling and interruption

In isolated workers, simulate unwritable directories, detail-write and CSV-write failures, malformed
UTF-8/JSON, initialization failure, and termination between temporary-detail creation and publication.
Use controlled fault injection where permissions or a full filesystem cannot reproduce the failure
reliably. Capture the PHP error-log destination independently of the workbench logger.

- Recoverable initialization and persistence failures must reach the PHP error-log fallback.
- A failed or interrupted detail write must never publish a CSV row referencing missing or partial
	JSON. Forced process termination cannot itself be expected to execute fallback logging.
- A CSV failure after successful detail publication may leave an orphan detail file, but must not
	modify an existing detail file or silently report persistence success.
- Retry after interruption and verify that temporary or invalid files do not count as valid final
	details. Check fallback detail generation during startup/installation failures too.

### UI, existing files, repair, and retention

Start the workbench and confirm there are no deprecations or unexpected initialization fallback.
In the log viewer, filter and sort the modeled columns and open details for ordinary messages,
exceptions, sender-backed messages, and fallback-generated messages. Repeat using valid pre-upgrade
CSV/detail pairs; successful JSON decoding alone does not prove UI compatibility.

Give repair a mixture of malformed logical records and valid records containing quoted commas and
embedded newlines. Verify the backup/quarantine copy, rejection reasons and logical record numbers,
preservation of all valid records, and absence of repair messages written into the file being repaired.
Run cleanup with expired and current daily files and matching detail directories; only expired data
must be removed.

### Release gate

Verify Composer resolves Monolog 3.12.x without FemtoPixel or ignored platform requirements and run
`php -l` on every changed PHP file. All scenarios above and the acceptance criteria in implementation
step 7 must pass before rollout. Mark unavailable checks as unverified, not passed.

After these checks, run representative concurrent traffic in a test environment for at least one
complete retention/cleanup cycle. Review fallback events, repair detections, invalid details, dangling
CSV rows, and orphan details before approving general deployment. Record any remaining limitations
alongside the results.

## Expected files affected

- `composer.json` and the installation-level Composer lock file
- `Factories/LoggerFactory.php`
- `CommonLogic/Log/Handlers/MonologCsvFileHandler.php`
- `CommonLogic/Log/Handlers/MonologErrorLogHandler.php`
- `CommonLogic/Log/Monolog/DebugMessageMonologHandler.php`
- `CommonLogic/Log/Monolog/MessageOnlyFormatter.php`
- `CommonLogic/Log/Processors/*.php`
- `CommonLogic/Log/Handlers/MonitorLogHandler.php`
- `CommonLogic/Log/LogCleaner.php`
- `DataTypes/LogLevelDataType.php`

No change to the `exface.Core.LOG_ENTRY` metamodel is expected. If validation proves that its current
CSV enclosure or escape configuration cannot safely represent the required values, any format
change must be designed as a separate backward-compatible migration with dual-read support; it must
not be folded silently into the Monolog upgrade.