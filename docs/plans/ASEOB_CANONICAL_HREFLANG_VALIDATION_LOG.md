# A.SEOb — Canonical & Hreflang — Validation Log

**Milestone:** A.SEOb Canonical URLs, hreflang & Language Relationships  
**Implementation branch:** `feature/aseob-canonical-hreflang`  
**Plan:** [ASEOB_CANONICAL_HREFLANG_IMPLEMENTATION_PLAN.md](ASEOB_CANONICAL_HREFLANG_IMPLEMENTATION_PLAN.md)  
**Evidence:** [aseob-evidence/](aseob-evidence/)  
**Planning freeze on main:** merge `a99c8a55a` + closure `c3ec21d85`  
**Implementation baseline HEAD:** `c3ec21d856a1b267145546a620e1e2264dd5fbf0`

**Supported:** SB1–SB11  
**Deferred / out of scope:** translated leaf URLs; URL-history tables; A.SEOc–A.SEOf emission; scrape

---

## ASEOB.0 — Baseline

**Status:** PASS

| Item | Result |
|---|---|
| Plan Architecture Frozen on main | **Pass** |
| Supported SB1–SB11 | **Pass** |
| TARGET | **6** |
| A.SEOa Complete | **Pass** (`a-seoa-slugs-permalinks-complete`) |
| Live env | https://dev.biopentra.eu — WP + Woo + Rank Math + AIML |

---

## ASEOB.1 — Admission lock

**Status:** PASS

Live/code re-check matches planning evidence: Rank Math owns canonical emission when active; AIML emits document hreflang; Switcher consumes SB11; preview excluded from public graph; SA7 URL identity unchanged.

---

## ASEOB.2–ASEOB.4 — Implementation

**Status:** PASS

| Component | Path |
|---|---|
| SB11 | `src/Seo/LanguageRelationshipService.php` |
| Canonical + hreflang | `src/Seo/DocumentSeoHead.php` |
| redirect_canonical policy | `src/Routing/Router.php` |
| Switcher aligned to SB11 | `src/Frontend/Switcher.php` |

---

## ASEOB.5 — Deferred guardrails

**Status:** PASS — `tests/integration/AseobDeferredGuardTest.php`

---

## ASEOB.6 — Platform reuse

**Status:** PASS — no new Workspace/TM/Jobs/schema; TARGET 6 unchanged.

---

## ASEOB.7 — Full acceptance

**Status:** PASS

| Gate | Result |
|---|---|
| Unit | **586** / **1559** (2 skipped) |
| Integration | **555** / **12240** (2 skipped) |
| PluginGuard | **17** / **8938** |
| PHPCS (touched) | **PASS** |
| `git diff --check` | **PASS** |

### Live (`dev.biopentra.eu`)

| Check | Result |
|---|---|
| Page EN/SV hreflang reciprocity | **PASS** — en-US, sv-SE, x-default present and reciprocal |
| Product EN/SV hreflang | **PASS** |
| x-default | **PASS** — default language absolute URL |
| Alternating EN↔SV | **PASS** — 200, redirect=0, correct `lang` |
| Preview `/de/` | **PASS** — 301 to unprefixed (not public) |
| Canonical tag in HTML | Omitted under sitewide `noindex` (Rank Math); filters covered by suite |
| FP / leakage | **0** / **0** |
| Duplicate hreflang language tags | **0** |
| Missing reciprocal (Supported contexts) | **0** |

### Regressions

Full suite green; A.SEOa / Gutenberg / Elementor / Woo / Fluent / A.6 covered by suite + live Woo product.

---

## ASEOB.8 — Closure

**Status:** PASS (implementation complete on feature branch; **not** merged / **not** tagged)

| Item | Value |
|---|---|
| Recommended later tag | `a-seob-canonical-hreflang-complete` |
| Next | Independent review → merge → tag; then A.SEOc only after closure |
