# Changelog

## 2.3.0

- Honor the existing `firewall_enabled` setting in every environment. An enabled firewall now checks Postmark IPs and configured Basic Auth credentials in local, testing, staging, and custom environments as well as production. Explicitly set `POSTMARK_WEBHOOK_FIREWALL_ENABLED=false` where a bypass is intended; no new opt-in setting is introduced.
- Preserve production checks, explicit disabling, trusted-proxy handling, supported PHP/Laravel versions, routes, payloads, event dispatch, and acknowledgment behavior.
- Add environment-matrix authentication regressions and verify the existing environment switch. Payload/listener fixtures now use an allowed source IP and exercise the real middleware.

See the [2.3.0 release notes](docs/releases/v2.3.0.md) and [publishing steps](docs/releasing.md).

## 2.2.0 — released September 12, 2026

- Default successful acknowledgment changes from HTTP 202 to HTTP 200; body remains `["success"]`. The optional `POSTMARK_WEBHOOK_SUCCESS_STATUS=202` preserves an exact-202 integration. Invalid explicit settings fail before event dispatch.
- Fix basic-auth handling of string `"0"` and use timing-safe string comparisons. Preserve production-only enforcement, firewall switches, and Postmark's four webhook IPs.
- Declare Laravel 12/13 runtime requirements alongside PHP `^8.3`; retain event API, payload precedence, nullable message IDs, synchronous dispatch, routes, and failure behavior. Invalid non-string route configuration now produces an explicit configuration exception.
- Upgrade development tooling to Pest 5, Larastan 3, PHPStan 2, Pint 1, and supported Testbench 10/11 lanes. Use PHPUnit 12 compatibility harnesses where Pest 5 cannot resolve.
- Add 114 regression tests, isolated consumer/cache checks, 12 dependency matrix lanes, suppression-free PHPStan level 9, focused mutation checks, pinned CI actions, and dependency-update automation.

Historical tags are unchanged. See release notes for [2.0.0](docs/releases/v2.0.0.md) and [2.1.0](docs/releases/v2.1.0.md).
