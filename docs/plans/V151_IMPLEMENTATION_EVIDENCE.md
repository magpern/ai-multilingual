# v1.5.1 Localized URL Correctness / SEO Stabilization — Implementation Evidence

**Branch:** `feature/v151-localized-url-correctness-stabilization`  
**Authoritative plan:** [V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_IMPLEMENTATION_PLAN.md](V151_LOCALIZED_URL_CORRECTNESS_STABILIZATION_IMPLEMENTATION_PLAN.md)  
**Baseline:** [V151_IMPLEMENTATION_BASELINE.md](V151_IMPLEMENTATION_BASELINE.md)  
**Version:** 1.5.0 (unchanged) · **TARGET:** 8 · **STATE:** A · **Migration:** NONE

## WP0–WP9 disposition

| WP | Result |
|---|---|
| WP0 Preflight | PASS — baseline `7a43b0ad6`, version 1.5.0, TARGET 8, Gate B evidence |
| WP1 D1 characterization | PASS — bounded term_link re-entry proven (`V151D1TermLinkRecursionTest`) |
| WP2 D1 fix | PASS — `Router::filter_term_link` re-entrancy guard (`filtering_term_link`) |
| WP3 Model A characterize | PASS — per-consumer table below |
| WP4 DIVERGES-only fixes | PASS — shared SB11 `url_to_postid_unfiltered_home` |
| WP5 D3b | PASS — disposition **A** (same term_link root as D1); Woo regression `V151D3bWooRenderHealthTest` |
| WP6 Regression | PASS — V151 + existing Mseo/Aseo suites (CI) |
| WP7 PluginGuard | PASS — `test_v151_corrective_boundaries` |
| WP8 Impl validation | See CI / local matrix (version remains 1.5.0; not release ZIP) |
| WP9 Evidence | This document |

## D1 root cause and correction

**Established chain:** `term_link` → `Router::filter_term_link` → `source_path_for_object` → `HierarchyPathBuilder::source_path_for_term` → `get_term_link` → `term_link` (unbounded when no stored term `source_path`).

**Rank Math role:** trigger only (breadcrumbs call `get_term_link`).

**Correction:** request-local re-entrancy guard in `Router::filter_term_link` — nested calls return the WordPress-generated source `$url` unchanged so source-path resolution completes.

Gate B GET timeout remains runtime evidence. Automated tests use capped call-count (no uncontrolled timeout). **V151AC3** (real GET completion) remains **DEV published-artifact acceptance**.

## Model A per-consumer classification

| Consumer | Classification | Notes |
|---|---|---|
| hreflang | **DIVERGES** → corrected | SB11 identity failed under filtered `home_url` |
| canonical | **ALREADY CORRECT** (regression) | Forced `use_localized_path=true`; still benefits from identity fix |
| og:url (`current_public`) | **DIVERGES** → corrected | Same SB11 public set as hreflang |
| switcher | **DIVERGES** → corrected | Same identity gate when discoverable |
| sitemap xhtml (`for_path` on source loc) | **ALREADY CORRECT** (regression) | Default-language locs; EffectiveUrl alternates |

**Shared fix:** `LanguageRelationshipService::url_to_postid_unfiltered_home` — neutralize `home_url` filters during `url_to_postid` only.

## D3b disposition

**A — SAME ROOT CAUSE AS D1.** Localized Woo truncation under Gate B is explained by the same unbounded `term_link` cycle (e.g. product category breadcrumbs). No distinct Woo AIML fix; D1 guard + `V151D3bWooRenderHealthTest` regression.

## Production files changed

- `src/Routing/Router.php` — D1 re-entrancy guard
- `src/Seo/LanguageRelationshipService.php` — D2/D3a unfiltered `url_to_postid`

## Tests added/updated

- `tests/integration/V151D1TermLinkRecursionTest.php`
- `tests/integration/V151ModelAConsumerTest.php`
- `tests/integration/V151D3bWooRenderHealthTest.php`
- `tests/integration/PluginGuardTest.php` — `test_v151_corrective_boundaries`

## V151AC1–22 disposition

| AC | Status |
|---|---|
| V151AC1–2,4–6 | PASS (automated) |
| V151AC3 | **DEFERRED** to published-artifact DEV acceptance (by design) |
| V151AC7–12 | PASS (automated) |
| V151AC13–14 | PASS (D3b = A; D1 fix covers) |
| V151AC15–19 | PASS (TARGET 8 / no migration / OFF inert / matrices) |
| V151AC20 | PASS on green feature CI (impl package audit at 1.5.0) |
| V151AC21 | **DEFERRED TO RELEASE PREPARATION BY DESIGN** |
| V151AC22 | **DEFERRED TO PUBLISHED-ARTIFACT DEV ACCEPTANCE BY DESIGN** |

## Exclusions / STOP audit

No TARGET change · no migration · no new URL/SEO authority · no Model A sitemap redesign · no Program B · no production/DEV deploy · no version bump.

## Distinction

This evidence covers **automated implementation acceptance** only.  
**Future published-artifact DEV acceptance** is a separate authorized phase after v1.5.1 release.
