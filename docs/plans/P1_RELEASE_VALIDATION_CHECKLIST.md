# P1 — Release validation checklist

Use before tagging a Platform **v1.0.x** (or later v1.x) release. Aligns with
`.github/workflows/ci.yml` and `release.yml`.

## Tier 0 (required — CI)

- [ ] PHPCS green
- [ ] PHPUnit unit green
- [ ] PHPUnit integration green (includes PluginGuard)
- [ ] `git diff --check` clean on the release commit set

## Build / package

- [ ] `composer install --no-dev` (or CI equivalent) before zip
- [ ] `bin/build-zip.sh` succeeds
- [ ] Zip contains no `node_modules`, `tests/`, `acceptance/` secrets, or `.env`
- [ ] Plugin header `Version:` matches intended tag (`vX.Y.Z` ↔ `X.Y.Z`)

## Operational verification (P1 harnesses)

- [ ] `acceptance/p1/deploy-verify.php` PASS on designated environment
- [ ] `acceptance/p1/schema-verify.php` PASS (simulate upgrade on staging when practical)
- [ ] `acceptance/p1/diagnostics-smoke.php` PASS (or documented equivalent)

## Provider validation gate (when AI behaviour changes)

**Canonical baseline (frozen):** `acceptance/rc/v1-openai-rc.php` +
[V1_RC_OPENAI_VALIDATION.md](V1_RC_OPENAI_VALIDATION.md).

- [ ] OpenAI RC PASS, **or** recorded waiver with owner + reason (scoped re-run)
- [ ] No secrets in RC / diagnostics outputs

If AI behaviour did **not** change in this release, cite the last green RC evidence
and mark this gate N/A with link.

## Do not require in every CI PR

- Live OpenAI calls
- Full Playwright Tier 3 matrix

## Dry-run record (P1 S6)

| Field | Value |
|---|---|
| Date | 2026-08-06 |
| Operator | engineering (P1 implementation) |
| Result | Checklist exercised against existing CI/release workflows + P1 harness PASS evidence on `feature/p1-platform-stabilization`; no new production tag published |
| Notes | Release.yml still verifies tag↔header version and builds zip on `v*` push |
