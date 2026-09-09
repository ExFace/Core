---
description: "Use when profiling or optimizing performance, especially for vague requests like 'this page is slow'. Covers the exfTools.perf (JS) and DebugStopWatch (PHP) profilers."
name: "Performance profiling & optimization"
applyTo: "Facades/AbstractAjaxFacade/js/exfTools.js, CommonLogic/Utils/DebugStopWatch.php, CommonLogic/DataSheets/CrudCounter.php"
---
# Performance profiling tools (JS + PHP)

## Measure first, never guess

When asked to optimize performance - **always measure before changing anything**. This is
non-negotiable and matters most when the request is vague, floaty or imprecise (e.g. "this page is
slow, fix it", "make it faster"). In those cases:

1. Do NOT jump to a fix based on intuition or a plausible-looking hot spot.
2. Instrument the suspected area with the profilers below, reproduce the slow scenario, and read the
   numbers.
3. Let the measurements identify the real bottleneck, then optimize only what the data points to.
4. Re-measure after the change to prove the improvement (and that you did not regress elsewhere).

Guessing wastes effort on code that is not actually the bottleneck and often makes things worse. If
you cannot measure for some reason, say so explicitly and ask - do not silently guess.

Both profilers are development-only, temporary instrumentation and **must be removed before
committing** - each deliberately announces itself (a console error / a logged exception) on
instantiation to remind you.

## JS performance profiler: `exfTools.perf`

Use for client-side JS: hot loops in facade widgets, validation passes, render bottlenecks,
round-trips.

- Location: `Facades/AbstractAjaxFacade/js/exfTools.js` → `exfTools.perf`.
- Create ONE profiler: `exfTools.perf.createProfiler({calls: [...], timeMs: [...]})`.
- API:
  - `count(sName)` - increment a call counter
  - `start(sName)` / `stop(sName)` - manual timing
  - `measure(sName, fn)` - time a synchronous block (safe on throw), returns `fn`'s result
  - `reset()`, `getStats()`, `log()` (console.table), `now()`
  - The instance also exposes plain `.calls` / `.timeMs` objects, so counters may be incremented
    inline (`oProfiler.calls.x++`) in extremely hot paths where a function call would itself
    distort the measurement.
- Workflow: add `count()` / `measure()` at suspected hot spots → run the scenario → `log()` →
  attribute cost precisely. For a first broad pass, the browser's built-in profiler is often faster
  to reach for; use `exfTools.perf` to pin down specific functions once you know the area.
- Reminder: `createProfiler()` logs a `console.error` on instantiation. Do not silence it - remove
  the profiler usage instead.

## PHP performance profiler: `DebugStopWatch`

The PHP counterpart to `exfTools.perf`. Profiles server-side execution time **and** CRUD (database)
operations.

- Location: `CommonLogic/Utils/DebugStopWatch.php` (uses `CommonLogic/DataSheets/CrudCounter.php`).
- Two modes:
  - Local instance: `new DebugStopWatch($workbench)`.
  - Global singleton via the `*Global()` static methods - instrument code deep in the call stack
    without threading an instance through every method.
- API: `start([$objects])` → `takeLap($label, $from)` / `measure($label, $fn)` → `stop()`.
  Global equivalents: `startGlobal`, `takeLapGlobal`, `measureGlobal`, `stopGlobal`,
  `isRunningGlobal`, `getLapsGlobal`.
  - Laps are keyed by label; repeating a label accumulates time + CRUD deltas and bumps a lap
    counter (per-label totals → derive averages).
  - `measure($label, $fn)` times ONLY that closure and returns its result (even if it throws) - the
    closure-oriented analog of `exfTools.perf.measure`.
  - Pass meta objects to `start([$obj])` to also count Create/Read/Update/Delete on them. Per-lap
    CRUD values are deltas; grand totals are printed by `stop()`.
- Reminder: the constructor logs a `RuntimeException` on every instantiation. To find leftovers
  before pushing, call `DebugStopWatch::setDetectReferences(true)` once at bootstrap - every
  `*Global()` call then throws with a stack trace pointing at the reference to delete.

## Rules (apply to both tools)

- Measure before and after every optimization; let data drive the change.
- You may use them liberally to time round-trips and execution blocks during optimization.
- ALWAYS remove every reference once you are done. They are DEV-ONLY, temporary instrumentation and
  must never be committed or shipped.
