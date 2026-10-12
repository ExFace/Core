---
description: "Use when implementing or fixing PHP behavior in Core or ExFace apps, adding PHPUnit tests, or deciding between unit tests and application-level tests."
name: "PHPUnit testing"
applyTo: "**/*.php"
---
# PHPUnit Testing

## Cover Changes Without Mocking Workbench

When implementing or fixing PHP behavior, add or update focused PHPUnit tests
where the behavior can be exercised without mocking Workbench. For bug fixes,
include a regression scenario that fails before the fix and passes afterward.

- Test pure logic directly: parsing, normalization, calculations, validation,
  aggregation, identity generation and formatting are typical candidates.
- When a component needs Workbench, use a real instance if the scenario can run
  without database access, a persisted metamodel or the full application lifecycle.
- Never mock `Workbench`, `WorkbenchInterface` or a chain of Workbench services
  just to make a unit test possible.
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