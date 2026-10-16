---
description: "Use when implementing or fixing PHP behavior in Core or ExFace apps, adding PHPUnit tests, or deciding between unit tests and application-level tests."
name: "PHPUnit testing"
applyTo: "**/*.php"
---

# PHPUnit Testing

We need to improve the quality and coverage of our PHP code. The idea is to provide both human 
developers and AI-agents with a clear understanding of the expected behavior, and to ensure that 
future changes do not break existing functionality. This should help focus our development efforts
on established solution spaces, and avoid wasting time on debugging regressions.

This document explains how to add or update PHPUnit tests when implementing or fixing behavior in Core or ExFace apps, 
and how to decide between unit tests and application-level tests. Whenever you are tasked with implementing or fixing 
PHP behavior, you must add or update focused PHPUnit tests to cover the behavior, provided your scenario matches 
the conditions outlined below.

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


## Cover Changes Without Mocking Workbench

When implementing or fixing PHP behavior, add or update focused PHPUnit tests
where the behavior can be exercised without mocking Workbench. For bug fixes,
include a regression scenario that fails before the fix and passes afterward.

Why not mock Workbench? A mock encodes your assumptions about a collaborator and
then verifies the code against those assumptions instead of against reality. Even a
mock that builds successfully produces a test that is (1) tautological: scripting
`Workbench`'s call chain means the test passes because the mock returns what you told
it to, proving internal consistency with your script rather than a correct result;
(2) brittle: it freezes the internal call structure, so a refactor that reaches the
same correct result differently breaks the test on a non-bug, inverting our goal of
avoiding false regressions; and (3) blind at the seam that matters: it bakes in your
guess about how Workbench actually behaves, so if the guess is wrong the mock is wrong
in the same direction as the code and the test stays green while production fails.
Mocking a narrow boundary such as an HTTP transport or a subprocess is acceptable
because that boundary has a small, stable, deliberately substituted contract.
Workbench is a wide, evolving god-object; mocking it means scripting a large slice of
assumed internal behavior, which is the source of all three problems.

For a concrete, annotated illustration of exactly this mistake, see the deliberately
skipped anti-pattern at
`Tests/PHPUnit/Unit/Actions/SendToWidgetHandleAntiPatternTest.php`. It mocks
`WorkbenchInterface` to "test" a final `handle()` method and flags inline where each of
the three failures above (tautological, brittle, blind-at-the-seam) occurs. Do not
imitate it; it exists only as a teaching example.

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
compatible with its supported PHP versions and the existing BDT dependency.
PHPUnit may already be installed through BDT; do not reinstall or update unrelated
dependencies. Composer does not install dependency packages' `require-dev` entries
in a host project, so the host must provide PHPUnit separately.

Exclude test support from production classmap generation. Never load tests through
production bootstrap code or leave custom standalone assertion scripts alongside
their migrated PHPUnit replacements.

## Ensure The App's Test Infrastructure

Before you write a test for an app, confirm that app can actually run PHPUnit. Core
already ships the reference setup (`exface/core`): a dual-mode `Tests/PHPUnit/bootstrap.php`,
a `phpunit.xml.dist` with `unit`/`integration` suites and a `Tests/PHPUnit/` tree. An app
inherits almost everything from Core's conventions, so the per-app footprint is small.

Follow these steps in order, and do not skip ahead:

1. **Verify completeness.** Check the owning app against the checklist below. If every
   item is present, just add your test and run it.
2. **If something is missing, update the repo first.** The infrastructure may already
   exist upstream and simply be absent from the local checkout. Confirm the app and the
   installation are up to date, then request/pull an update before changing anything.
   Re-check the checklist.
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

Mirror Core's existing files; do not design new ones.

- **Test tree:** create `Tests/PHPUnit/Unit/` (and `Integration/` if required). Keep Behat,
  Bruno and other frameworks in their own sibling folders, out of PHPUnit discovery.
- **Bootstrap:** copy Core's `Tests/PHPUnit/bootstrap.php` unchanged for apps at
  `vendor/<vendor>/<app>`. It tries the standalone autoloader first, then the installation
  autoloader, and throws a clear error if neither is found.
- **Config:** copy Core's `phpunit.xml.dist` to the app root. Paths are relative to the app
  root, so no edits are needed beyond keeping the suite directories under `Tests/PHPUnit/`.
- **Namespace & classmap:** name test classes under `<Vendor>\<App>\Tests\PHPUnit\...` and add
  `/Tests/` to `autoload.exclude-from-classmap`. The app's whole-namespace PSR-4 prefix
  already autoloads the tests on demand while keeping them out of the production classmap.
- **Dependency:** add `phpunit/phpunit` to the app's `require-dev`. Do not reinstall or
  update unrelated dependencies; PHPUnit may already be provided through BDT or the host.

### Providing the PHPUnit binary

Declaring `phpunit/phpunit` in `require-dev` documents the dependency but does **not** by
itself install a runnable binary. The binary must be provided by the host installation, and
in this installation it is **not** guaranteed to be present: although `axenox/BDT` requires
PHPUnit, the `vendor/` tree here is assembled by ExFace's PackageManager rather than a plain
Composer install, so `vendor/bin/phpunit` and `vendor/phpunit/` may be absent even when the
declaration exists.

Because of this, a missing binary is a **provisioning gap, not a test-code gap**, and the
"update the repo" and "fill the gap" steps above will not create it. When no binary resolves:

- Do not add a per-app PHPUnit copy, vendor a PHAR, or run broad dependency installs/updates
  to work around it. Those exceed this guide's setup and the installation's assembly model.
- Stop and ask the user how PHPUnit is provisioned in this installation, then use that
  sanctioned mechanism. Report the binary you resolved and where it came from.

This keeps the per-app setup (test tree, bootstrap, config, namespace, `require-dev`) separate
from the one-time, host-level decision of how the runner is made available.

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

**Important:** Developers may be surprised when you write unit tests. 
Since the tests show up as added/changed files in the pull request, the developer might reject or question them.
To avoid confusion notify your user in the chat that you are adding these tests in accordance with your instructions.
Similarly, if they want you to skip writing tests inform them about these instructions, and that you need explicit approval 
to skip writing tests.