# PHP 8.3, 8.4 and 8.5 upgrade roadmap

## Purpose and scope

This roadmap separates changes required to run ExFace Core on PHP 8.3, 8.4 and 8.5 from dependency modernization that is desirable but not required by those runtimes. Each PHP version is a separate delivery step. Do not combine a runtime upgrade with the PSR, Monolog or Symfony migrations described later unless Composer or runtime validation proves that coupling necessary.

The dependency findings use two different sources:

- `core/composer.json` describes what Core allows as a reusable package.
- The application-root `composer.lock` describes one resolved installation. A blocker in that lock is not automatically owned by Core; its dependency chain must be checked with `composer why`.

The original inspected installation used PHP 8.2 and resolved `exface/core` from `1.x-dev` at `cd1b387`. Most Symfony components resolved to 5.4. A newer supplied installation resolves Core at `467d675` and already uses Symfony 6.4 for most components, Symfony Mailer 7.4, and Symfony Security 5.4. This mixed but intentional result supports treating the Security migration separately from the other Symfony components.

Core has no package-local lock file, so locked-version and security findings are installation snapshots and must be refreshed before implementation. The newer lock contains 383 production and development packages. Its relevant resolved baseline is:

| Area | Recent resolved versions |
| --- | --- |
| Symfony | Mostly 6.4; Cache and Finder 5.4; Mailer 7.4; Security Core 5.4 |
| HTTP | Guzzle 7.15.5, Guzzle PSR-7 2.13.1, PSR HTTP Message 1.1 |
| Logging | Monolog 1.27.1, PSR Log contracts inherited through the graph |
| Cache/container | PSR Cache 1.0.1, Simple Cache 1.0.1, Container 1.1.2 |
| Documents/media | PhpSpreadsheet 1.30.7, Intervention Image 2.7.2 |
| JSONPath | `softcreatr/jsonpath` 0.10.0 and `galbar/jsonpath` 3.0 |

The recent lock also marks `webmozart/path-util` 2.3.0 as abandoned in favor of `symfony/filesystem`. This is lifecycle work, not a PHP 8.3-8.5 runtime blocker.

## Classification

- **Required**: Composer cannot resolve the target PHP version, PHP reports a target-version deprecation in Core, or a changed PHP API can break a Core code path.
- **Validation**: no change is currently proven necessary, but changed language or extension behavior requires a focused regression test.
- **Lifecycle**: the current package may still run, but its major line is old, unsupported or no longer the preferred integration. This work should have its own compatibility review.
- **Separate refactoring**: a public interface or framework contract changes and widening the Composer constraint alone would make Core implementations incompatible.

## Delivery strategy

1. Establish a repeatable smoke-test suite on the existing PHP 8.2 baseline.
2. Upgrade and certify PHP 8.3 without dependency-major changes.
3. Make Core deprecation-clean on PHP 8.4 and adapt extension API changes.
4. Resolve the PHP 8.5 dependency ceilings and removed/deprecated APIs.
5. Run lifecycle upgrades as independent projects, starting with security-relevant packages.

For every target, test both Core's lowest supported dependency set and the application lock used in production. A successful `composer install` is evidence of installability, not proof that every dependency is maintained or behaviorally compatible.

## PHP 8.3

### Required work

No Core-direct Composer constraint or confirmed Core source incompatibility currently blocks PHP 8.3. Keep the existing `"php": "^8"` constraint until all three target versions are certified; narrowing it for 8.3 would unnecessarily drop PHP 8.0-8.2 consumers.

### Validation work

- Run all available Behat suites and application smoke tests with `E_ALL` enabled and convert deprecations to visible CI failures or collected test failures.
- Exercise code that calls `range()`. PHP 8.3 validates invalid ranges more strictly and can throw `ValueError` where older versions returned unusual arrays or warnings. Verify formula, date, pagination and sequence-generation paths with ascending, descending, fractional and invalid inputs.
- Test date/time parsing, serialization, CSV/XLSX import and export, mail delivery, cache access, authentication, LDAP and all configured database connectors.
- Run Composer platform resolution for PHP 8.3 at the application root and investigate every blocker by dependency chain.

### Acceptance criteria

- Core and the representative application install without ignored platform requirements.
- Existing behavior tests pass on PHP 8.2 and PHP 8.3.
- No new Core-originating warning or deprecation appears under `E_ALL`.
- Composer security audit results are triaged by owning package; unresolved findings have an explicit risk decision and are not silently accepted as part of PHP certification.

## PHP 8.4

### Required source changes

#### Explicit nullable parameter types

PHP 8.4 deprecates parameters whose declared type is implicitly nullable because their default is `null`, for example `TaskInterface $task = null`. Change these to explicit nullable types such as `?TaskInterface $task = null`, or to a union containing `null` where appropriate.

This is a broad API change across Core. The initial text scan found many candidates, but its count is not authoritative because signatures span lines and simple regular expressions also match unrelated declarations. Build the final inventory with a PHP tokenizer or a PHP 8.4 compatibility analyser, then update declarations in ownership groups:

1. Interfaces and abstract classes.
2. Implementations, overrides and traits, preserving variance rules.
3. Public factories, actions, widgets, data connectors and facade APIs.
4. Remaining protected and private methods.

Do not apply a blind textual replacement. For each public or inherited signature, update the complete hierarchy together and verify that generated API/UXON documentation remains correct.

#### ODBC result objects

PHP 8.4 changes ODBC result values from resources to `Odbc\Result` objects. Update `DataConnectors/OdbcSqlConnector.php` so result cleanup accepts both the pre-8.4 resource and the PHP 8.4 object before calling `odbc_free_result()`. Test successful queries, failed queries, empty results and cleanup paths against a real ODBC connection on PHP 8.3 and 8.4.

### Dependency work

No locked dependency ceiling was confirmed specifically for PHP 8.4. Re-run platform resolution because package releases and the application lock may have changed by implementation time.

Symfony 5.4 does not have to be replaced merely to run PHP 8.4. It is in security-fixes-only support, with security support published through February 2029. Upgrading the non-security components to Symfony 6.4 is still recommended lifecycle work, but the Security component requires a separate migration described below.

### Acceptance criteria

- A tokenizer/static-analysis inventory reports no implicit-nullability declaration left in Core.
- Core produces no PHP 8.4 implicit-nullability deprecations under `E_ALL`.
- ODBC integration tests pass with both resource-based and object-based extension behavior.
- The PHP 8.2 and 8.3 jobs remain green, proving the compatibility edits did not raise the runtime floor accidentally.

## PHP 8.5

### Required dependency changes

Composer Semver evaluation of every PHP constraint in the recent lock identifies two PHP 8.5 blockers, both in Core's dependency graph:

| Package | Recent version | Ownership | Required action |
| --- | --- | --- | --- |
| `softcreatr/jsonpath` | 0.10.0 | Direct dependency of Core and `axenox/etl`; requires PHP 8.1-8.4 | Upgrade both owning constraints to a release supporting the complete intended PHP range, with JSONPath behavior tests. |
| `phpoffice/phpspreadsheet` | 1.30.7 | Direct Core dependency; requires PHP below 8.5 | Upgrade to a maintained PHP 8.5-compatible line and adapt Core integrations. |

The older installation additionally exposed `ezyang/htmlpurifier` 4.18.0 and `sabberworm/php-css-parser` 8.7.0 as PHP 8.5 blockers. Those are no longer blockers in the recent graph: HTML Purifier 4.19.1 and CSS Parser 9.5.0 explicitly allow PHP 8.5. This demonstrates why certification must evaluate each installation's complete lock rather than copying a fixed blocker list from this roadmap.

`softcreatr/jsonpath` and `galbar/jsonpath` are both used by Core for different APIs. Do not remove one solely because both implement JSONPath. Core uses `Flow\JSONPath` in formula filtering and `JsonPath\JsonObject` in UXON/mutation handling. Before upgrading `softcreatr/jsonpath`, capture tests for recursive selection, filters, missing paths, scalar/array return shapes and exception behavior. A current compatible major may raise the minimum PHP version and can contain API or semantic changes, so select the exact target release during implementation rather than widening the constraint speculatively.

PhpSpreadsheet is a substantial major-version upgrade, not just a constraint edit. Inventory all readers, writers, cell APIs, styling, formula handling, temporary-file behavior and cache integration. Test CSV, XLSX and any template-based exports with representative large files. The chosen release must support every PHP version Core still claims, or Core must make the runtime-floor decision explicitly.

### Required source changes

PHP 8.5 deprecates `finfo_close()` because `finfo` objects are released automatically. Remove the three Core calls currently present in:

- `DataConnectors/Traits/IValidateFileIntegrityTrait.php`
- `DataTypes/MimeTypeDataType.php`

Retain normal object scoping and verify MIME detection for valid files, missing files, in-memory data and corrupt image data.

Run PHP 8.5 with `E_ALL` over the complete smoke suite to discover extension-specific deprecations not reachable by static search. In particular, exercise database drivers, file handling, image processing, LDAP, mail, XML and archive operations.

### Acceptance criteria

- `composer prohibits php 8.5 --locked` reports no blockers in the representative application.
- Core's own manifest resolves on PHP 8.5 without ignored platform requirements.
- JSONPath and spreadsheet compatibility suites pass on PHP 8.3, 8.4 and 8.5.
- No `finfo_close()` call remains in Core and no Core-originating PHP 8.5 deprecation is emitted.
- Application-owned blockers such as PDF rendering are resolved before the application is certified, even though they are outside the Core change set.

## Lifecycle upgrades that are not runtime prerequisites

### Symfony

Prefer Symfony 6.4 LTS as the first modernization target because Core's existing constraints already permit version 6 for most components and Symfony 6.4 supports PHP 8.1+. Upgrade the components as one tested set rather than allowing an arbitrary mixture of majors in production.

Two components remain on 5.4 in the recent lock for specific reasons:

- **Finder:** the lock contains `kabachello/fileroute` 0.3, whose published metadata permits Finder 4 and 5 only. FileRoute commit `5321c52` already changes its constraint to `^4||^5||^6`, and its PHP source does not directly use Finder APIs. Publish a release newer than 0.3 from that commit, update the platform constraint/lock, and verify installation plus FileRoute middleware behavior. This should unlock Finder 6.4 without a Core source change.
- **Cache:** Core requires `psr/cache:^1`, while Symfony Cache 6.4 requires PSR Cache 2 or 3. Core's `CommonLogic/WorkbenchCache.php` implements cache interfaces, so this is not a safe constraint-only update. Complete the PSR-6 signature migration, validate cache behavior, widen `psr/cache`, and then resolve Symfony Cache 6.4.

Keep `symfony/security-core` separate. Core currently constrains it to Symfony 5 and uses legacy authentication-provider, user-provider and password-encoder APIs. Migrating this integration requires redesign against the newer authenticator and password-hasher contracts, including authentication, remember-me/session behavior, LDAP, authorization and failure-path tests. Only widen the Security constraint after those code paths compile and pass on both supported configurations.

Symfony 7.4 LTS requires PHP 8.2+ and is a possible later baseline after Symfony 6.4 compatibility is stable. It should not be coupled to initial PHP 8.3 support. Avoid targeting a non-LTS Symfony release for a long-lived Core baseline without a specific reason.

### Monolog and PSR-3

Core permits Monolog 1 and the inspected integration relies on Monolog 1-style array records and integer levels. A direct jump to Monolog 3 changes records to `LogRecord` objects and levels to `Level` values. It also couples to newer PSR-3 signatures.

Stage this migration:

1. Make Core's logger extension and custom handlers compatible with Monolog 2 while preserving the existing logging contract.
2. Add tests for context, sender metadata, exception logging, handler propagation and CSV/debug handlers.
3. Design the PSR-3 migration and custom `$sender` behavior explicitly.
4. Move to Monolog 3 only after handlers consume the new record and level types.

### PhpSpreadsheet

PhpSpreadsheet 1.x is both lifecycle debt and a direct PHP 8.5 blocker: the recent 1.30.7 package explicitly requires PHP below 8.5. In the older lock, its HTML Purifier dependency also imposed an upper bound. Upgrade PhpSpreadsheet before PHP 8.5 as an independent project to reduce risk in the final runtime step. Security advisories for resolved spreadsheet versions make the application-root Composer audit a release gate; use the audit's exact fixed-version guidance at implementation time rather than relying on this roadmap's snapshot.

### Intervention Image

Core constrains Intervention Image to 2.x. Version 3 uses a redesigned API and driver model, so treat it as a separate migration with image decode, resize, orientation, format conversion, metadata and failure-path tests. The old major is not a proven PHP 8.3-8.5 blocker by itself.

### Other old packages

Replace the abandoned `webmozart/path-util` with `symfony/filesystem` after mapping path-normalization behavior and platform-specific edge cases. Review `wingu/reflection`, `cebe/markdown`, `justinrainbow/json-schema`, `femtopixel/monolog-csvhandler`, `gajus/dindent`, `robthree/twofactorauth` and `kabachello/fileroute` for maintenance status, advisories and maintained replacements. FileRoute additionally needs the Symfony 6 compatibility commit released as described above. Package age alone is not evidence of incompatibility; classify each using its PHP constraint, release/support status, audit results and exercised Core call sites.

## PSR interface migration project

Do not widen PSR constraints independently. New PSR interface majors add native parameter and return types, and Core implements or extends several of those contracts.

Known refactoring surfaces include:

- PSR-7: `Facades/AbstractHttpFacade/IteratorStream.php` has PSR-7 v1-style untyped stream methods.
- PSR-16 and PSR-6: `CommonLogic/WorkbenchCache.php` implements cache contracts whose newer majors add signatures.
- PSR-11: `Interfaces/AppInterface.php` extends the container interface while exposing broader custom `get()` and `has()` behavior; verify Liskov substitution before adding types.
- PSR-3: `Interfaces/Log/LoggerInterface.php` adds a `$sender` argument to standard logger methods, so compatibility cannot be assumed from Composer resolution alone.

For each PSR family:

1. Inventory every Core implementation, extension, adapter and consumer.
2. Compare old and new interface signatures method by method.
3. Decide whether Core's public interface can remain source-compatible, needs an adapter, or requires a major Core release.
4. Update implementations and tests before changing the Composer constraint.
5. Validate with the lowest and highest allowed interface versions; avoid broad constraints that describe combinations Core has not tested.

PSR-15 handler and middleware interfaces remain on major version 1 in the current ecosystem and do not need a speculative upgrade.

## Security audit handling

The application-root audit snapshot reported 74 advisories affecting 14 packages. That number includes all installed applications and development packages and must not be presented as 74 Core vulnerabilities. Export the machine-readable audit, trace each affected package with `composer why`, and classify it as:

- Core direct or Core transitive: remediate or document in this roadmap's implementation work.
- Owned by another ExFace application: assign to that package and keep it as an application release gate.
- Development-only: assess exposure separately; do not dismiss it automatically.
- False positive or unreachable feature: document evidence and an expiry date for the exception.

Security remediation takes precedence over the optional/lifecycle label. A package can be runtime-compatible with PHP 8.5 and still be unacceptable to ship.

## Validation matrix and commands

Run the following at the application root for PHP 8.2, 8.3, 8.4 and 8.5 using real interpreters or CI containers, not only Composer's emulated platform setting:

```shell
php -v
composer validate
composer install --no-interaction
composer check-platform-reqs
composer audit --locked --no-interaction
composer prohibits php <target-version> --locked --no-interaction
```

Also resolve Core in a minimal fixture project so unrelated application packages cannot hide Core-only dependency problems. Run the available Behat suite and targeted integration tests with `error_reporting=E_ALL`; Core currently does not use package-local unit tests, so add focused cases to the established external test package or reproducible integration fixtures.

Minimum behavioral coverage for every version:

- Workbench bootstrap, configuration and event dispatch.
- Authentication, authorization, sessions and LDAP where configured.
- DataSheet read, create, update and delete against representative SQL connectors.
- HTTP middleware and streaming responses.
- Cache read/write/delete/clear behavior.
- Logging and all shipped handlers.
- File MIME detection, integrity validation, image processing and archives.
- CSV/XLSX import and export.
- JSONPath expressions used by formulas, UXON and mutations.
- Console actions, mail delivery and scheduled/cron execution.

Certification is complete only when the target PHP job is deprecation-clean for Core, the previous supported PHP jobs remain green, Composer has no target-platform blocker, and all security-audit findings have an owner and disposition.
