# v1.6.0 Release Preparation — Independent Review

**Reviewed branch:** `release/v1.6.0-preparation`  
**Reviewed HEAD:** `e7257e3adb8adb814bda68c080fc3df42631098a`  
**Baseline main:** `bdf28f96cc6b74ee655a30286f722b5bd0678822`  
**Train:** P0 Localized URL Operator Completion + P1 G4/Rank Math Model A Characterization + P2 Jobs/Stale Operator Literacy  
**Date:** 2026-08-16

## Falsification target

> “1.6.0 is a backwards-compatible feature release with TARGET 8 and no migration.”

Attempted falsifiers and results:

| Attempt | Result |
|---|---|
| TARGET changed from 8 / step_9 present | **FAIL to falsify** — `Migrator::TARGET = 8`; no `step_9` |
| Public Extension/Integration API expanded | **FAIL to falsify** — no public API surface expansion in prep branch; P0 thin admin REST; P2 reuses existing Job domain |
| New Localized URL routing capability claimed falsely | **FAIL to falsify** — release notes/CHANGELOG/scope explicitly deny new routing |
| P1 described as new sitemap runtime | **FAIL to falsify** — docs characterize Model A only |
| DEV `blog_public=0` EXPECTED OMIT generalized as “xhtml generally absent” | **FAIL to falsify** — wording correctly scopes EXPECTED OMIT to DEV/`blog_public` gate |
| Silent conflict overwrite introduced | **FAIL to falsify** — P2 preserves no-silent-overwrite |
| Schema/API incompatibility invalidating CASE A | **Not found** |
| Secrets / `.admin-credentials` in package or Git | **FAIL to falsify** — ZIP audit clean; credentials remain gitignored |

**Falsification verdict:** claim stands — **PASS**.

## Scope check

| Check | Result |
|---|---|
| Version bump 1.5.1 → 1.6.0 (header, `AIML_VERSION`, Stable tag, PluginGuard) | PASS |
| TARGET remains 8 / migration NONE / no step_9 | PASS |
| Entire P0+P1+P2 train included (not cherry-picked) | PASS |
| CHANGELOG distinguishes Added / Changed / Documentation / Compatibility | PASS |
| Release notes accurate; P1 not exaggerated; G4 wording correct | PASS |
| Release scope documents tag/deploy boundary | PASS |
| Packaging includes `assets/term-slug-admin/` (P0 runtime) | PASS (prep defect remediated) |
| Distinguishes release-prep ZIP vs future GitHub Release asset | PASS |
| No tag / GitHub Release / deploy performed | PASS |
| Production `biopentra.eu` untouched | PASS |

## Local validation snapshot (pre-PR)

| Suite | Result |
|---|---|
| PHPCS (touched PHP) | PASS |
| Unit | PASS (929 tests) |
| JS / Jest admin | PASS (106 tests) |
| Quality validate + baseline verify | PASS |
| `bin/build-zip.sh` + `bin/audit-zip.sh` | PASS — see ZIP table |

Integration / full PluginGuard / PackageGuard: required **GREEN** on PR CI and fresh main CI before release-ready identification.

## ZIP (release-preparation artifact)

| Field | Value |
|---|---|
| Filename | `ai-multilingual-1.6.0.zip` |
| Bytes | 771477 |
| Entries | 480 |
| SHA-256 | `af945dbb3bc4a9dd76ce30037cd5546542a6ef3795ca4de63ac1de3d5754ac4a` |
| Audit | PASS |
| `AIML_VERSION` / header | 1.6.0 |
| Term slug asset present | Yes |
| Credentials / secrets | None found |

**Not** the published GitHub Release asset (tag/release separately authorized).

## In-scope remediation

1. **Missing ZIP packaging of `assets/term-slug-admin/`** — fixed in `bin/build-zip.sh` + required path in `bin/audit-zip.sh`; re-audited PASS.

## Defects remaining (release-prep)

None in-scope after remediation.

## Material product / architecture STOP conditions

None discovered.

## Verdict

**V1.6.0 RELEASE PREPARATION REVIEW: PASS**

Post-merge confirmation:

- PR CI GREEN — run `31937380082`
- Fresh main CI GREEN — run `31937457007`
- Release-ready commit (future tag target): `417df7a5b8df3121aedd5fff0b03ae79cc728290`
- Tag / GitHub Release / deployment: **NOT CREATED / NOT PERFORMED**
