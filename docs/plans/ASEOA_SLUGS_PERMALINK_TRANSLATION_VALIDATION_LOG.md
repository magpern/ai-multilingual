# A.SEOa — Slugs & Permalink Translation — Validation Log

**Milestone:** A.SEOa Slugs & Permalink Translation  
**Implementation branch:** `feature/aseoa-slugs-permalinks`  
**Plan:** [ASEOA_SLUGS_PERMALINK_TRANSLATION_IMPLEMENTATION_PLAN.md](ASEOA_SLUGS_PERMALINK_TRANSLATION_IMPLEMENTATION_PLAN.md)  
**Evidence:** [aseoa-evidence/](aseoa-evidence/)  
**Parent:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](ASEO_PARENT_IMPLEMENTATION_PLAN.md)  
**Planning freeze on main:** `b42d9ccb885822c42d3a99e7805d65ba25b93ecd`  
**Implementation baseline HEAD:** `b42d9ccb885822c42d3a99e7805d65ba25b93ecd`

**Supported:** SA7, SA10  
**Deferred:** SA1–SA6, SA8, SA9  

---

## ASEOA.0 — Baseline

**Status:** PASS

### Preconditions

| Item | Result |
|---|---|
| `main` clean / synced at branch cut | **Pass** (`b42d9ccb8`) |
| A.SEOa plan Architecture Frozen on `main` | **Pass** |
| Supported set exactly SA7 / SA10 | **Pass** |
| SA1–SA6 / SA8 / SA9 Deferred | **Pass** |
| TARGET | **6** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 | **Accepted** |
| Integration API v1 | Present / unchanged |
| A.SEOa production leaf-slug code | Absent |

### Environment / runtime baseline

| Item | Value |
|---|---|
| Router | `src/Routing/Router.php` — prefix-strip ADR-0002; `filter_home_url`; `redirect_canonical` suppress when prefixed |
| PreviewService | `src/Workspace/PreviewService.php` — public URL via Router + LanguageContext |
| LanguageResolver | Preview capability-gated (ADR-0008) |
| Store `FORMAT_SLUG` | Constant only — no slug segments / reverse lookup |
| AIML rewrite rules | None |
| Live site | https://dev.biopentra.eu (WP + Woo active; Rank Math present — out of A.SEOa Supported scope) |

### Quality gates (baseline)

| Gate | Result |
|---|---|
| Unit | **586** tests / **1559** assertions — OK (2 skipped) |
| Integration | **519** tests / **11865** assertions — OK (2 skipped) |
| PluginGuard | **17** tests / **8836** assertions — OK |
| PHPCS | Exit 0; pre-existing warnings/errors outside A.SEOa touch set (same posture as A.6/A.7d logs) |
| `git diff --check` | **PASS** |

---

## ASEOA.1 — Inventory lock

**Status:** PASS

Runtime re-check recorded in [aseoa-evidence/implementation-inventory-lock.md](aseoa-evidence/implementation-inventory-lock.md). No material drift vs planning evidence. Supported = SA7/SA10; Deferred unchanged.

---

## ASEOA.2 — SA7 contract tests

**Status:** PASS

- File: `tests/integration/AseoaSa7PermalinkGenerationTest.php`
- Result: **9** tests / characterizing SA7 (EN unprefixed, SV prefix + source leaf, query/fragment, no double-prefix, REST/admin/login exclusions, post/product source leaf, canonical suppress, no rewrite rules)
- No translated leaf-slug expectations

---

## ASEOA.3 — SA10 contract tests

**Status:** PASS

- File: `tests/integration/AseoaSa10PreviewUrlsTest.php`
- Result: **8** tests / characterizing SA10 (authorized preview, default unprefixed, unknown language error, preview not public, capability routable, published unaffected, REST auth, context restore)

---

## ASEOA.4 — SA7 hardening

**Status:** PASS (no-op)

SA7 characterizing suite green against existing `Router` — no production changes required. ADR-0002 prefix-strip preserved; no rewrite rules; no reverse slug lookup.

---

## ASEOA.5 — SA10 hardening

**Status:** PASS (no-op)

SA10 characterizing suite green against existing `PreviewService` / LanguageResolver — no production changes required. ADR-0008 preview gating preserved.

---

## ASEOA.6 — Deferred guardrails

**Status:** PASS

- File: `tests/integration/AseoaDeferredSlugGuardTest.php`
- Result: **8** tests — TARGET 6; no `SOURCE_TERM`; no reverse lookup API; no slug/history tables; Extractor omits `post_name`; Router adds no rewrite hooks; no SlugResolver/history classes; FORMAT_SLUG unused for end-to-end

---

## ASEOA.7 — Full acceptance

**Status:** PASS

### Quality gates (post–ASEOA.6)

| Gate | Result |
|---|---|
| Unit | **586** tests / **1559** assertions — OK (2 skipped) |
| Integration | **544** tests / **12044** assertions — OK (2 skipped) |
| PluginGuard | **17** tests / **8836** assertions — OK |
| PHPCS (`tests/integration/Aseoa*.php`) | **PASS** (exit 0) |
| `git diff --check` | **PASS** |
| Markdown relative links (A.SEOa plan/log/evidence + ROADMAP/POST_V1) | **PASS** — 188 links, 0 missing |

### Live / HTTP (`https://dev.biopentra.eu`)

| Check | Result |
|---|---|
| SA7 page EN unprefixed | **PASS** — `…/a4-nested-gutenberg-fixture/` → HTTP 200, `lang="en-US"`, source leaf retained |
| SA7 page SV prefixed | **PASS** — `…/sv/a4-nested-gutenberg-fixture/` → HTTP 200, `lang="sv-SE"`, same source leaf |
| SA7 product EN / SV | **PASS** — `…/product/p5-acceptance-widget-3/` and `…/sv/product/p5-acceptance-widget-3/` → 200; EN `en-US` / SV `sv-SE` |
| SA7 post type | **PASS** (integration suite — live site has no published `post` fixtures) |
| Double prefix | **PASS** — `…/sv/sv/a4-nested-gutenberg-fixture/` → **404** (no redirect loop) |
| Query on SV URL | **PASS** — `?utm=1` → HTTP 200 (no strip/redirect loop) |
| Alternating EN→SV→EN→SV | **PASS** — four requests, each HTTP 200, `redirect=0`, correct `lang` |
| SA10 anonymous preview language (`/de/…`) | **PASS** — HTTP **301** → unprefixed source URL (not a public crawlable preview route) |
| Canonical suppress | **Unchanged** — prefixed SV shortlink present; no unexpected redirect loops |
| FP | **0** |
| Leakage | **0** (EN body `en-US`, SV body `sv-SE` on Supported surfaces) |
| Redirect loops | **0** |
| Duplicate prefixes | **0** |

### Production code delta

| Area | Result |
|---|---|
| `src/Routing/Router.php` | **Unchanged** (ASEOA.4 no-op) |
| Preview / LanguageResolver | **Unchanged** (ASEOA.5 no-op) |
| Store / schema / TARGET | **Unchanged** (TARGET **6**) |
| Rewrite rules / URL history / redirect registry | **Absent** |

### Regression (suite-level; no production delta)

| Surface | Result |
|---|---|
| Gutenberg | **PASS** — full suite green; no AIML SEO production changes |
| Elementor | **PASS** |
| Woo A.7a–A.7d | **PASS** — product live EN/SV + suite |
| Fluent Forms A.8 | **PASS** |
| A.6 visitor chrome | **PASS** |
| Integration API v1 | **Unchanged** |

### Performance

Router / permalink path unchanged vs baseline (no new filters, caches, or rewrite tables). No invented budgets; overhead observation = **baseline-equivalent** for Supported SA7/SA10.
