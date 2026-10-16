---
description: "Use when implementing or fixing PHP behavior in Core or ExFace apps, adding PHPUnit tests, or deciding between unit tests and application-level tests."
name: "PHPUnit testing"
applyTo: "**/*.php"
---

# PHPUnit Testing

> **STOP — mandatory testing gate.** Before ending any turn in which you added, fixed, refactored
> or deleted PHP behavior (a behavior-preserving refactor counts), you **MUST** output one explicit
> line:
>
> `Testing decision: <added/updated tests ... | not added because ...>`
>
> A turn that changed a `.php` file but ends without this line is **incomplete** — do not conclude
> it. "The change is low-risk / obvious / a pure refactor" is **not** a valid reason to skip the
> decision; it is only ever a reason to justify *not adding* a test on that explicit line. Lint or
> build success never substitutes for this decision. When in doubt, default to writing the test
> (Rule 1 allows this without approval).


Good PHPUnit coverage documents expected behavior for both human developers and AI-agents and
guards against regressions. This document explains how to add or update tests when implementing or
fixing behavior in Core or ExFace apps, and how to decide between unit and application-level tests.
Whenever you implement or fix PHP behavior, add or update focused tests for it, provided the
scenario matches the conditions below.

## Scope

This document governs **writing and executing PHP unit tests only**. Every rule here applies to
test code and test execution, never to production code. For example, the approval requirement for
editing an existing test applies to the test script alone — it does not require approval to edit a
Widget, an Action or any other production class.

## Core Rules

1. **Writing new tests is strongly encouraged and never requires user approval.** Add focused
   coverage freely whenever you implement or fix PHP behavior.
2. **A unit test must be writable without mocking Workbench.** If a scenario cannot be covered
   without mocking `Workbench`, `WorkbenchInterface` or a chain of Workbench services, do not write
   it as a unit test. See "Cover Changes Without Mocking Workbench" for the reasoning and the
   application-level alternatives.
3. **Editing an existing test always requires explicit user approval.** Before changing a test's
   assertions, expected exceptions or any other expression of its intent, request approval and
   provide a meaningful analysis of the test and the desired change. A test codifies a developer
   expectation, so touching that expectation is a review event by definition. Mechanical relocation
   (paths, namespaces, imports) under "Follow The Owning App's Layout" is not a change of intent and
   is handled there.
4. **A failing test is always a stop-and-report event.** If any change — test-related or not —
   causes one or more unit tests to fail, notify the user with a detailed analysis of the failure
   before proceeding. (If resolving it requires editing a test, rule 3 then applies.)
5. **Analyze, do not advocate.** When reporting failing tests or requesting approval, give the user
   the information they need to make an informed decision. Do not try to convince them to accept
   your changes.
6. **Every PHP behavior change triggers an explicit testing decision, stated in chat.** Adding,
   fixing, refactoring or deleting PHP behavior all count — a behavior-preserving refactor is not
   exempt. Either add focused coverage (Rule 1, no approval needed) or state why not (e.g. the
   changed path needs a persisted metamodel/DB or the full application lifecycle, so it belongs in
   application-level/Behat tests per "Cover Changes Without Mocking Workbench"). Never conclude a PHP
   change with only lint/build validation and no testing statement.


## Cover Changes Without Mocking Workbench

When implementing or fixing PHP behavior, add or update focused PHPUnit tests
where the behavior can be exercised without mocking Workbench. For bug fixes,
include a regression scenario that fails before the fix and passes afterward.

Why not mock Workbench? A mock encodes your assumptions about a collaborator, so the test then
verifies the code against those assumptions, not against reality. Even a mock that builds cleanly
yields a test that is (1) **tautological** — it passes because the mock returns what you scripted,
proving internal consistency rather than correctness; (2) **brittle** — it freezes the internal
call structure, so a behavior-preserving refactor breaks it on a non-bug; and (3) **blind at the
seam that matters** — it bakes in your guess about how Workbench behaves, so a wrong guess makes
mock and code wrong together while the test stays green. Mocking a narrow, stable boundary (an HTTP
transport, a subprocess) is fine; Workbench is a wide, evolving god-object, and mocking it means
scripting a large slice of assumed behavior — the source of all three problems.

For an annotated illustration, see the deliberately skipped anti-pattern at
`Tests/PHPUnit/Unit/Actions/SendToWidgetHandleAntiPatternTest.php`, which mocks
`WorkbenchInterface` to "test" a final `handle()` and flags each failure inline. It is a teaching
example only — do not imitate it.

- Test pure logic directly: parsing, normalization, calculations, validation,
  aggregation, identity generation and formatting are typical candidates.
- When a component needs Workbench, use a real instance if the scenario can run
  without database access, a persisted metamodel or the full application lifecycle.
- Small fixtures or doubles for external boundaries, such as HTTP transports and
  command execution, are acceptable. Keep the behavior under test real.
- Do not redesign production APIs or add test-only hooks solely to avoid this rule.
- If meaningful coverage requires a persisted metamodel, authorization, database
  operations or a complete UI workflow, use existing Behat/application-level tests
  in `axenox/bdt` or the owning app instead. Explain any coverage you could not run;
  do not substitute a mocked Workbench test or silently omit the testing decision.

## Follow The Owning App's Layout

Keep tests in the app that owns the tested code. Reuse its existing PHPUnit
configuration, bootstrap, fixtures and test helpers before adding new ones.
Keep all PHPUnit tests and support files under `Tests/PHPUnit/`. Other frameworks
coexist in sibling folders such as `Tests/Behat/` and `Tests/Bruno/`; do not mix
their files or include them in PHPUnit test discovery. Use this layout:

- `Tests/PHPUnit/Unit/`: independent `*Test.php` classes for local behavior. These tests
  must not require a database, live HTTP services, installed scanner tools or
  spawned subprocesses. Temporary local files are acceptable when needed.
- `Tests/PHPUnit/Integration/`: checks using real local subprocesses or other explicitly
  provisioned dependencies. Keep them separate from the fast unit suite.
- `Tests/PHPUnit/Support/`: shared fixtures and helpers only where reuse is worthwhile.
- `Tests/PHPUnit/bootstrap.php`: Composer bootstrap supporting both a standalone app's
  `vendor/autoload.php` and the containing installation's Composer autoloader.
- `phpunit.xml.dist` at the app root: separate `unit` and, where needed, `integration`
  suites restricted to directories below `Tests/PHPUnit/`.

When moving existing tests, update suite paths, bootstrap root calculations,
test namespaces and imports, subprocess bootstrap paths, and documentation links.
This mechanical relocation does not change a test's intent and therefore does not
require the approval described in Core Rule 3.

Declare `phpunit/phpunit` in the owning app's `require-dev` using a constraint
compatible with its supported PHP versions and the existing BDT dependency. This
declaration documents the dependency only; see "Providing the PHPUnit binary" for how
a runnable binary is actually supplied.

Exclude test support from production classmap generation. Never load tests through
production bootstrap code or leave custom standalone assertion scripts alongside
their migrated PHPUnit replacements.

## Ensure The App's Test Infrastructure

Before writing a test for an app, confirm it can actually run PHPUnit. Core (`exface/core`) ships
the reference setup — a dual-mode `Tests/PHPUnit/bootstrap.php`, a `phpunit.xml.dist` with
`unit`/`integration` suites and a `Tests/PHPUnit/` tree — and apps inherit its conventions, so the
per-app footprint is small. Follow these steps in order:

1. **Verify completeness.** Check the owning app against the checklist below. If every
   item is present, just add your test and run it.
2. **If something is missing, check whether the checkout is current — do not update
   blindly.** The infrastructure may already exist upstream and simply be absent locally.
   But remember you are usually working inside an installation's `vendor/`, where "updating"
   a package is a Composer/PackageManager operation that can touch other packages, not a safe
   local `git pull`. Ask the user how updates are performed in this installation, and let them
   (or their sanctioned process) perform it. Then re-check the checklist.
3. **If the gap remains, fill it per this guide.** Add only the missing pieces, exactly as
   described in "Setup guide" below. Do not invent alternative layouts, bootstraps or config.
4. **Do not go beyond this setup without explicit user approval.** The shared harness
   described conceptually elsewhere (a base `WorkbenchTestCase`, shared fixtures, a
   `Support/` or `Testing/` namespace) **does not exist yet and is out of scope.** Do not
   create base test cases, shared fixtures, CI pipelines, new packages or autoload changes
   beyond the checklist. If a scenario seems to need them, stop and ask.

### Per-app infrastructure checklist

Verify each item in the owning app before writing tests:

- [ ] `Tests/PHPUnit/` exists with `Unit/` and, if needed, `Integration/` subfolders.
- [ ] `Tests/PHPUnit/bootstrap.php` is present and resolves autoload in both modes:
      the app's own `vendor/autoload.php` (standalone) and the installation's
      `vendor/autoload.php` (installed). For an app at `vendor/<vendor>/<app>` this is
      Core's bootstrap verbatim; adjust the `dirname` depth only if the app sits elsewhere.
- [ ] `phpunit.xml.dist` exists at the app root, referencing `Tests/PHPUnit/bootstrap.php`
      and declaring the `unit` (and optional `integration`) suite under `Tests/PHPUnit/`.
- [ ] Test classes use the app's own namespace prefix, e.g.
      `<Vendor>\<App>\Tests\PHPUnit\Unit\...`, so the app's existing whole-namespace PSR-4
      mapping resolves them. No `autoload-dev` entry is required.
- [ ] The app's `composer.json` lists `/Tests/` under `autoload.exclude-from-classmap`.
- [ ] The app's `composer.json` declares `phpunit/phpunit` in `require-dev` with a
      constraint compatible with its supported PHP versions and the existing BDT dependency.
- [ ] A runnable PHPUnit binary resolves from the host installation. PHPUnit is provided
      once by the host (do not install a per-app copy). If no binary resolves, treat it as a
      provisioning gap and follow "Providing the PHPUnit binary" below rather than looping
      through the steps above.

### Setup guide (only for genuinely missing items)

Mirror Core's files; do not design new ones. For each missing item: copy Core's
`Tests/PHPUnit/bootstrap.php` (verbatim for apps at `vendor/<vendor>/<app>`) and its
`phpunit.xml.dist` to the app root; create `Tests/PHPUnit/Unit/` (and `Integration/` if needed),
keeping Behat/Bruno in their own sibling folders; name test classes
`<Vendor>\<App>\Tests\PHPUnit\...`; and in `composer.json` add `/Tests/` to
`autoload.exclude-from-classmap` and `phpunit/phpunit` to `require-dev`. Do not reinstall or
update unrelated dependencies.

### Providing the PHPUnit binary

A `require-dev` entry documents the dependency but does **not** install a runnable binary — the
host installation must provide it, and here it may be absent: although `axenox/BDT` requires
PHPUnit, this `vendor/` tree is assembled by ExFace's PackageManager rather than a plain Composer
install, so `vendor/bin/phpunit` and `vendor/phpunit/` can be missing despite the declaration.

A missing binary is therefore a **provisioning gap, not a test-code gap** — the steps above will
not create it. When none resolves, do not work around it (no per-app copy, vendored PHAR or broad
installs); stop and ask the user how PHPUnit is provisioned here, then use that mechanism and report
what you resolved. Note the two modes mirror the dual-mode bootstrap: run **standalone**, the app's
own `require-dev` PHPUnit is the binary; run **inside an installation**, the host supplies it. So
"provided by the host" is about the installed mode and does not forbid an app carrying its own copy
when genuinely run on its own.

Run the narrowest suite from the app root to confirm (`--testsuite unit` first), then report
the command and result per "Validate And Report".

## Keep Scenarios Independent

- Give each behavior or failure path a clearly named `test*` method and use
  PHPUnit assertions and expected-exception handling, not custom assertion helpers.
- Cover relevant success, boundary and failure cases without duplicating unrelated
  coverage. Prefer the public contract; use existing local test seams only when needed.
- Construct fresh state for each scenario. Tests must not depend on execution order,
  findings produced by another test, or optional PHP `assert()` settings.
- Register cleanup before exercising behavior and remove temporary resources even
  after failures. Never modify the installation's real project files or dependencies.
- Use PHPUnit process isolation for immutable global changes such as defining
  constants; disable global-state preservation when the scenario requires it.
- Treat protected-method or constructor-bypassing fixtures as local checks, not proof
  that public authorization, action lifecycle or persistence works.

## Validate And Report

Run the narrowest relevant PHPUnit test after each behavioral change, then the
owning app's affected suite. Use `--filter` or `--testsuite` for focused execution.
When reorganizing tests or changing shared fixtures, also verify randomized order.

Report the command, test result and any unverified integration requirements. Do not
claim tests passed unless they were executed. Update documented test commands when
changing the test layout or runner.

**Important:** Developers may be surprised to see added/changed test files in a pull request, so
notify your user in chat that you are adding tests in accordance with these instructions. Likewise,
if they ask you to skip tests, tell them these instructions require explicit approval to do so.