# Publishing and deploying the 2.x update

Release version: `v2.2.0`. At the user's request, local preparation includes committing the update, merging it into master, and creating the annotated tag without pushing. Do not repeat the commit/merge/tag creation steps below for this prepared release; they remain a reference for future releases.

### Next commands for this prepared release

First push only master so GitHub CI can verify the final commit:

```bash
git push origin master
```

After CI and the application/ingress review gates pass, publish the already-created tag and its release notes:

```bash
git push origin v2.2.0
gh release create v2.2.0 --verify-tag --latest --title "v2.2.0" --notes-file docs/releases/v2.2.0.md
```

These push/release commands have not been executed. If branch protection rejects a direct master push, push `codex/postmark-safe-update` and use a PR instead; do not force-push. If the merge process changes commit identity, stop and reconcile the still-unpublished local tag with the final reviewed commit before publication. Never move an already published tag.

## 1. Review and merge the package

Review the runtime diff separately from tests/tooling and release documentation. Keep `tools/` tracked; leave `.build/`, vendors, generated caches, and this library's lock file ignored. No Composer `version` field is necessary: versions come from Git tags.

```bash
git switch codex/postmark-safe-update
git status --short
git diff --check
git diff
composer validate --strict
composer analyse
composer test:lint
composer test
composer audit --locked
```

Also run the coverage/mutation and compatibility commands in the README, or require all new CI jobs to pass. Review new/untracked files too. After approving the complete diff:

```bash
git add .github .gitignore composer.json phpstan.neon phpunit.xml.dist src tests tools README.md CHANGELOG.md docs
git diff --cached --stat
git commit -m "Prepare safe Postmark webhooks 2.2 update"
git push -u origin codex/postmark-safe-update
gh pr create --base master --head codex/postmark-safe-update --title "Safe Postmark webhooks 2.2 update" --body "Preserve the 2.x event contract, default to HTTP 200, harden authentication, and expand compatibility and quality checks. See docs/verification.md and docs/releasing.md."
```

Review and merge the PR only after CI passes and an isolated application rehearsal succeeds. Use a temporary path-repository override or dedicated checkout for that rehearsal; do not commit a local path override to the consumer. The application team's report still calls for real route-to-listener and queue regression tests. Production ingress and external monitoring must also be checked.

## 2. Tag and publish the approved commit

Before tagging, finalize the candidate wording in CHANGELOG/release notes in the reviewed commit. Confirm the version is unused locally and remotely; if it exists, stop and inspect it rather than overwriting it.

```bash
git switch master
git pull --ff-only origin master
git status --short
git log -1 --oneline
git tag --list v2.2.0
git ls-remote --tags origin refs/tags/v2.2.0
```

Proceed only with a clean tree, the exact approved commit, passing CI, and no existing tag:

```bash
git tag -a v2.2.0 -m "Postmark Webhooks 2.2.0"
git push origin v2.2.0
gh release create v2.2.0 --verify-tag --latest --title "v2.2.0" --notes-file docs/releases/v2.2.0.md
```

Pushing the tag can make it installable through Packagist even before the GitHub release record is created. Check the existing Packagist package page for `v2.2.0` and the intended commit; if synchronization fails, inspect its GitHub hook and use the maintainer's Packagist Update action. Do not submit a second package. Confirm visibility with:

```bash
composer show citricguy/postmark-webhooks-laravel --all
```

A prerelease such as `v2.2.0-rc.1` is optional if desired before stable: publish it as a GitHub prerelease, not Latest, and explicitly opt only the isolated test application into that prerelease. Existing stable `^2.0` consumers should not be broadly switched to development stability.

## 3. Repair historical GitHub metadata

There is no required `2.0` branch: `v2.0.0` and `v2.1.0` are tags in the 2.x version series. They already exist in Git/Packagist. Missing GitHub release objects and the stale Latest badge are separate presentation issues.

First inspect `gh release list` and `git ls-remote --tags origin`; only create records that are still missing, verifying the commits listed in their prepared notes:

```bash
gh release create v2.0.0 --verify-tag --latest=false --title "v2.0.0" --notes-file docs/releases/v2.0.0.md
gh release create v2.1.0 --verify-tag --latest=false --title "v2.1.0" --notes-file docs/releases/v2.1.0.md
```

The new stable 2.2 release should be Latest. If repairing records before releasing 2.2, set the existing stable 2.1 record as Latest with `gh release edit v2.1.0 --latest`. Never move, delete, or force-push historical tags to repair the release page.

## 4. Update a consuming application

Existing `"citricguy/postmark-webhooks-laravel": "^2.0"` needs no composer.json change. In a development checkout with the application's proper PHP/container environment, first inspect the proposed targeted update:

```bash
composer update citricguy/postmark-webhooks-laravel:2.2.0 --dry-run
composer update citricguy/postmark-webhooks-laravel:2.2.0
git diff -- composer.json composer.lock
composer audit --locked
```

Do not start with a broad `composer update` or `-W`. If resolution fails, inspect `composer why-not citricguy/postmark-webhooks-laravel 2.2.0` and approve any additional dependency changes deliberately. Never disable advisory blocking to force installation.

Change the known application assertion from `assertStatus(202)` to `assertOk()` (or `assertStatus(200)`), preserving the body assertion and adding the complete payload assertion. Run the team's proposed integration tests and focused command in its authorized test environment:

```bash
./bin/atest --compact tests/Feature/Central/Listeners/PostmarkWebhookReceived/RoutePostmarkWebhooksTest.php
```

Run the application's full suite according to its testing policy, then commit its updated lock file and tests. No production application source/config edit was identified by the supplied review. If an external system requires exact 202, use the compatibility setting temporarily and test it explicitly.

Deploy the reviewed application artifact through its normal pipeline, using its committed lock file:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Do not run dependency updates on production. Rebuild the application's normal config/route caches and restart long-lived queue workers through the existing deployment process. This package introduces no database migrations. Confirm Postmark verification receives 200, normal events reach the intended tenant/listeners, downstream jobs are processed, and authentication failures/errors/retries are observable. Staging with a non-production APP_ENV does not exercise the production-only firewall.

Rollback by redeploying the previous application artifact/committed lock file and rebuilding normal caches/workers; do not rewrite a published package tag. The 202 setting addresses an exact-status dependency only, not arbitrary listener or infrastructure failures.

References: [Composer library tags](https://getcomposer.org/doc/02-libraries.md#tags), [targeted updates and locked installs](https://getcomposer.org/doc/03-cli.md), and [GitHub release creation](https://cli.github.com/manual/gh_release_create).
