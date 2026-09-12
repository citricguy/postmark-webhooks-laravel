# Safe-update verification

Local verification completed September 12, 2026, on `codex/postmark-safe-update`, based on master/v2.1.0 (`22f0e8c`). These results describe the locally prepared 2.2.0 release, not a published release or completed GitHub CI run.

## Results

- All 12 lanes passed: PHP 8.3.33, 8.4.25, and 8.5.10 × Laravel 12 and 13 × latest and lowest secure stable resolution.
- Each lane ran 114 tests with 368 assertions, strict Composer validation, platform checks, a clean security audit, and a real generated consumer check (automatic discovery, legacy published config, exact event data, custom named route, uncached and cached configuration/routes).
- Laravel versions resolved to 12.69.2 / 12.61.1 and 13.31.0 / 13.23.0 for latest / lowest respectively. PHP 8.5/Laravel 13 consumers also resolved Symfony HTTP Client and Postmark Mailer 8.1.
- Root development set: Pest 5.1.4, PHPUnit 13.3.2, Testbench 11.2.0, Larastan 3.12.0, PHPStan 2.2.13, Pint 1.32.1. Root Composer validation and audit passed.
- `composer analyse`: zero PHPStan level-9 errors across all 15 source/test/PHP-tool files, with no exclusions, suppressions, or baseline. The transient SimpleXML array/null errors in the IP mutation helper are fixed, not ignored.
- Pint checks and `git diff --check` passed. Reported source line coverage is 100%; no arbitrary coverage threshold is imposed.
- Focused Pest mutation run detected all 66 covered mutations in controller/middleware. Four separate allowlist-removal mutations were also detected by the intended HTTP assertion, not incidental crashes. This is focused mutation evidence, not a claim that every possible mutation of every file was tested.

All Laravel 12 lanes use PHPUnit 12 because Testbench 10's Symfony Process 7 requirement conflicts with Pest 5's Process 8 requirement. PHP 8.3 also uses PHPUnit 12. Laravel 13 on PHP 8.4/8.5 uses Pest 5; all lanes run the same test classes.

Local PHP 8.3/8.5 missing extensions were loaded from isolated temporary extracted packages via `PHP_INI_SCAN_DIR`; system PHP configuration was not changed. Contributors should provide their own matching extensions as documented in `tools/README.md`.

## Remaining release/rollout gates

- Push master and require the new GitHub workflow to pass on the final commit before pushing the release tag. Local results are not remote CI evidence.
- The supplied application review found one exact-202 test assertion and no production code depending on that status. Update that test and add the recommended real route-to-tenant-listener/queued-listener regression checks in the application.
- The application review did not execute tests because its Sail environment was not running. Neither the application's focused suite nor its full suite has been verified here. Follow its authorization/testing policy.
- Review real ingress/proxy trust, Postmark credentials/settings, WAF, and external monitoring for exact-202 expectations. Package tests cannot verify those systems.
- The path-repository fixture proves package installation compatibility, not the entire supplied application's dependency tree or infrastructure. Rehearse the application update in isolation and inspect its actual lock-file diff.
- Local release preparation includes commits, a merge to master, and an annotated v2.2.0 tag at the user's request. No pushes, GitHub release records, Packagist updates, or consumer deployments are included. Publication remains a separate operator action after the gates above.
