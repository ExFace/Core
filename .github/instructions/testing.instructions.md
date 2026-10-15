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