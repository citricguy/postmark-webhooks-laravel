# Release verification

## v2.3.0 — local verification, October 16, 2026

The runtime change removes the automatic non-production firewall bypass. The existing configuration switch, IP checks, optional Basic Auth, event and payload contracts, routes, and supported PHP/Laravel ranges are retained.

Completed checks:

- `php vendor/bin/pest --filter=test_enabled_firewall_checks_identity_in_every_environment --colors=never` before the runtime fix: 12 failures and 8 passes, demonstrating that invalid identities bypassed enabled authentication outside production.
- `php vendor/bin/pest --colors=never`: 136 tests / 475 assertions passed.
- `XDEBUG_MODE=coverage php vendor/bin/pest --coverage --colors=never`: the same 136 tests passed, with 100% reported source line coverage.
- `php vendor/bin/phpstan analyse --memory-limit=2G --no-progress`: level 9 passed across source, tests, and PHP tools, without baselines or suppressions.
- `php vendor/bin/pint src tests tools` and `php vendor/bin/pint --test src tests tools`: passed.
- `composer validate --strict` and final `composer audit --locked`: passed, with no reported security advisories.
- `XDEBUG_MODE=coverage composer test:mutate`: all 64 covered controller/middleware mutations were detected; all four explicit allowlist-removal mutations were also detected by their intended assertions.
- `git diff --check`: passed.

Root checks used PHP 8.4.26, Laravel 13.31.0, Testbench 11.2.0, Pest 5.1.4, and PHPUnit 13.3.2. The initial audit found three advisories in the ignored development lock file. A targeted update changed only `league/commonmark` (2.10.1 to 2.10.3) and `symfony/http-foundation` (8.1.6 to 8.1.9); the final tests, coverage, analysis, and audit passed afterward. The package manifest and consumer lock files were not changed.

### Consumer compatibility

Eight existing isolated matrix installations were exercised sequentially: PHP 8.3.35 and 8.4.26, each with Laravel 12/13 and their cached latest/lowest dependency sets. Every lane passed 136 tests / 475 assertions, followed by the real consumer check for automatic discovery, previously published configuration, synchronous listener dispatch, and config/route caches.

These runs reused installed dependency sets; they do not claim fresh resolution or a clean audit of each cached lane. The matrix generator's fixture-only version is now 2.3.0 so future fresh checks verify installation through a consumer's existing `^2.0` requirement.

The local PHP 8.5 runtime lacks required extensions, including mbstring, curl, and SQLite. Its four matrix lanes were not run locally. The existing GitHub workflow must perform fresh resolution, audit, tests, and consumer checks for all 12 lanes, plus the PHP 8.5 quality job, on the reviewed commit before the release tag is published.

### Scope and release gates

No live Postmark calls, email delivery, application database operations, consuming-application updates, tags, pushes, GitHub releases, Packagist updates, or deployments were performed during this preparation. Source IPs and credentials in tests are synthetic request fixtures, never outbound destinations or real secrets.

Consumers must review the existing firewall setting before upgrading non-production environments. Package verification cannot establish their live proxy, IP, credential, or callback-processing configuration. FSRevs issue #255's application throttling work remains separate.

Follow [the release runbook](releasing.md) for the maintainer-run commit, CI, tag, and publication sequence.

## Historical v2.2.0 verification

The September 12, 2026 preparation verified 114 tests / 368 assertions per lane across 12 PHP/Laravel/latest-lowest combinations, root static analysis/style/audit, consumer discovery and caches, reported 100% source line coverage, 66 covered mutations, and four allowlist-removal mutations. That release was subsequently published on September 12, 2026. Its historical behavior and compatibility notes remain in [the v2.2.0 release notes](releases/v2.2.0.md); those results are not substituted for the v2.3.0 checks above.
