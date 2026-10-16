# Publishing v2.3.0

The previous v2.2.0 release is already published. These steps prepare and publish the new v2.3.0 release; do not move or recreate historical tags. Version numbers come from Git tags, so do not add a Composer `version` field.

Tagging, pushing, GitHub release creation, and any Packagist update are maintainer-run steps. Preparing the local source and notes does not publish the package or deploy a consuming application.

## 1. Verify and commit locally

Work in this package checkout on `master`. Review the diff and the upgrade action in `docs/releases/v2.3.0.md`: an enabled firewall now applies outside production. No new setting, migrations, or event/route/payload changes are introduced.

```bash
git branch --show-current
git status --short --branch
git diff --check
git diff
composer validate --strict
composer test:analyse
composer test:lint
composer test
```

Use the existing compatibility matrix for PHP 8.3/8.4/8.5, Laravel 12/13, and latest/lowest dependencies. See `tools/README.md` for runtime requirements. Require the existing CI matrix, coverage/mutation checks, and dependency audit to pass before publishing. Keep generated vendors, lock files, caches, and `.build/` output ignored.

Stage only this release's intended files, inspect the staged diff, and commit:

```bash
git add -- src/Http/Middleware/VerifyPostmarkWebhookAuth.php src/config/postmark-webhooks.php tests/Feature/AuthenticationTest.php tests/Feature/ConfigurationTest.php tests/Feature/ListenerTest.php tests/Feature/PayloadTest.php tools/prepare-matrix.php tools/README.md README.md CHANGELOG.md docs/releasing.md docs/verification.md docs/releases/v2.3.0.md
git diff --cached --check
git diff --cached --stat
git diff --cached
git commit -m "Honor Postmark firewall configuration in every environment"
```

## 2. Push master and check its CI

```bash
git push origin master
gh run list --repo citricguy/postmark-webhooks-laravel --workflow tests.yml --branch master --commit "$(git rev-parse HEAD)"
```

Wait for the `Tests` workflow on that exact commit to finish successfully. `gh run watch --repo citricguy/postmark-webhooks-laravel --exit-status` can watch the selected run. Do not publish the tag while checks are pending or failing. If branch protection requires a PR, follow that process without force-pushing; verify the final merged commit before tagging.

## 3. Tag the reviewed commit and create its release

Recheck the branch, clean tree, exact approved commit, and that the version is unused locally and remotely:

```bash
git branch --show-current
git status --short --branch
git log -1 --format='%H %s'
git tag --list v2.3.0
git ls-remote --tags origin refs/tags/v2.3.0
```

If a local or remote v2.3.0 tag already exists, inspect it and stop instead of replacing it. With a clean tree on the approved commit and passing CI:

```bash
git tag -a v2.3.0 -m "Postmark Webhooks v2.3.0: consistent firewall enforcement"
git push origin refs/tags/v2.3.0
gh release create v2.3.0 --repo citricguy/postmark-webhooks-laravel --verify-tag --latest --title "v2.3.0" --notes-file docs/releases/v2.3.0.md
```

Pushing the tag can expose the version through Packagist before the GitHub release record is created. Never overwrite a published tag; fix a published defect in a later release.

## 4. Confirm Packagist and update consumers separately

Check the existing Packagist package for v2.3.0 at the intended commit. If automatic updating is configured, the GitHub update should import it. Otherwise use the existing package's maintainer Update action; do not submit a duplicate package. Check available versions with:

```bash
composer show citricguy/postmark-webhooks-laravel --all
```

Consumers using `^2.0` already allow v2.3.0. Review their firewall setting first: keep authentication enabled for real callbacks, or explicitly disable it in isolated development/test environments that require a bypass. Existing published configuration keys work without republishing. Review trusted proxies and configured Basic Auth for each environment; successful package tests cannot establish live ingress configuration.

In an authorized development checkout, inspect and then perform only the targeted dependency update:

```bash
composer update citricguy/postmark-webhooks-laravel:2.3.0 --dry-run
composer update citricguy/postmark-webhooks-laravel:2.3.0
git diff -- composer.json composer.lock
```

For FSRevs, run Composer through `vendor/bin/sail` and follow its testing and environment-safety rules. Its ordinary-API callback-throttling changes remain separate application work for issue #255. No consuming application update or deployment is part of this package release preparation.

Application owners should release the reviewed lock file through their normal process, rebuild their configuration/route caches as appropriate, and verify their callback ingress. Never run dependency updates on a production server. Roll back using the previous application artifact and lock file, not by rewriting the package tag.

References: [Composer library tags](https://getcomposer.org/doc/02-libraries.md#tags), [Packagist package updates](https://packagist.org/about#how-to-update-packages), and [GitHub release creation](https://cli.github.com/manual/gh_release_create).
