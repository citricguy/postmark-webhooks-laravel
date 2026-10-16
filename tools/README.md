# Maintainer verification tools

Commit these scripts: they are shared, portable checks used by CI and contributors, not disposable local experiments. They contain no machine-specific paths, credentials, or application data. Consumers do not need to run them; they are not Composer install/update hooks and are not part of the runtime autoload mapping.

Generated output belongs in the ignored `.build/` directory. Root `vendor/`, `composer.lock`, coverage output, and test/style caches are also ignored. Do not commit generated dependencies or fixtures. Unlike this library, consuming applications should commit their own `composer.lock` files.

## Scripts

| Script | Purpose |
| --- | --- |
| `check-matrix.sh` | Resolve, validate, audit, and test one isolated PHP/Laravel dependency lane, then check a consumer installation. |
| `prepare-matrix.php` | Generate the lane's Composer manifest and link the shared tests under `.build/matrix/`. Called by the shell wrapper. |
| `consumer-check.php` | Boot the generated Laravel app with real package discovery, old published config, a synchronous listener, and route/config caches. Reject paths outside the generated matrix directory. |
| `check-ip-mutations.php` | Remove each allowed IP in a generated middleware copy and require the specific regression-test failure. Complements Pest mutations because property initializers are not executable coverage lines. |

Run from a package checkout using Bash (Linux, macOS, or WSL), Composer 2 with advisory blocking enabled, and a supported PHP binary with its normal Laravel/Testbench extensions, including mbstring, curl, XML/DOM/SimpleXML, and SQLite. Symlink support is required. The matrix wrapper can run before installing root development dependencies.

```bash
PHP_BINARY=php8.5 bash tools/check-matrix.sh 13 latest
PHP_BINARY=php8.5 bash tools/check-matrix.sh 13 lowest
PHP_BINARY=php8.3 bash tools/check-matrix.sh 12 latest
```

Repeat with PHP 8.3/8.4/8.5, Laravel 12/13, and latest/lowest to reproduce the 12 CI lanes. Optionally set `COMPOSER_BINARY` to your Composer PHP executable/PHAR. The selected PHP must have all required extensions; this does not install or alter system PHP. Resolution downloads public dependencies and writes Composer's normal cache. Each lane has independent vendor and lock files and leaves the root manifest/lock unchanged.

PHP 8.5/Laravel 13 additionally checks Symfony HTTP Client and Postmark Mailer `^8.1`. The generated package version `2.3.0` is a fixture-only path-repository alias to test a consumer's `^2.0` constraint; it publishes nothing. Latest/lowest means the latest/lowest currently resolvable secure stable set within the harness constraints, not a promise to install historical vulnerable versions.

For root quality checks, install development dependencies on PHP 8.4/8.5 first (PHP 8.5 recommended). Xdebug coverage is needed for mutation testing:

```bash
composer update --prefer-stable
composer analyse
composer test:lint
XDEBUG_MODE=coverage composer test:mutate
```

When running mutation tests with a non-default PHP, also select that PHP through `PATH` so child processes use the same runtime and extensions. Do not run concurrent matrix checks for the same lane: they share that lane's generated files. These tools do not contact Postmark, send email, or operate on production databases; their synthetic consumer checks do not replace your application's integration tests.
