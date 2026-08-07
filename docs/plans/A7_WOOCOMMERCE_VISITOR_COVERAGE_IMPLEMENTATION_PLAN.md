# A.7 — WooCommerce Visitor Coverage — Implementation Plan

**Status:** **Architecture Frozen (planning)** — ready for architecture review / freeze; implementation not started
**Milestone family:** Program A — **A.7** WooCommerce visitor-facing coverage
**Plan freeze:** Visitor/customer-facing WooCommerce only; admit surfaces via deterministic ownership + identity; reuse Store / Workspace / Review / TM / Glossary / Jobs / Integration API v1; no WooCommerce persistence mutation
**ADR assessment:** **No new ADR required at plan freeze** if waves stay within ADR-0001 / ADR-0007 / ADR-0013 / ADR-0016 / ADR-0017 + Integration API v1. A wave that needs a new identity family or Store redesign must open a focused ADR **before** coding — not silently.
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — Program A — §6.2 (wave boundaries refined by this plan)
**Planning branch:** `feature/a7-woocommerce-visitor-coverage-plan`
**Implementation branch:** create **per wave** after this family plan freezes on `main` (first: `feature/a7a-woocommerce-product-catalog` after A.7a plan/admission freeze)
**Baseline (plan authoring):** `main` @ `b4932a0aeedc8d0304c7a0d8de941358f3fa1f82`
**Depends on:** P1; A.R1/A.2/A.3; A.R2/A.4; A.1; A.0; A.8 — all complete/tagged; ADR-0013 / ADR-0016 / ADR-0017 **Accepted**; schema TARGET **6**
**Related:** [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md); [adr/0017-plugin-integration-framework-ownership-and-identity.md](../adr/0017-plugin-integration-framework-ownership-and-identity.md); [A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md](A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md)

**Operational success:** Merchants can translate visitor-facing WooCommerce catalog, archive, customer-workflow, and Woo-owned customer-email surfaces through the existing AIML platform, with FP=0 / leakage=0 on admitted surfaces, without rewriting WooCommerce data or inventing a second translation pipeline.

**This plan is the family implementation contract for A.7 (waves A.7a–A.7d).** Do not implement production code on the planning branch. First coding starts only after architecture freeze on `main` and a dedicated **A.7a** implementation branch.

---

## 1. Purpose

Plan **complete visitor-facing WooCommerce coverage** as a **family of bounded waves**, not one monolithic release.

A.7 does **not**:

- redesign WooCommerce
- translate wp-admin / merchant tooling
- own WooCommerce persistence
- scrape HTML
- reopen frozen ADRs for convenience

A.7 **does**:

- inventory visitor surfaces
- admit them one allowlist at a time
- reuse Gutenberg (`b:`), Elementor (`e:`), and Integration API (`p:`) where ownership fits
- preserve overlay-not-duplication

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| P1 Platform Stabilization complete | **Pass** (`p1-platform-stabilization-complete`) |
| A.R1 / A.2 / A.3 Elementor path complete | **Pass** |
| A.R2 / A.4 Nested Gutenberg complete | **Pass** |
| A.1 Integration API v1 complete | **Pass** (`a1-plugin-integration-framework-complete`) |
| A.0 Gutenberg Leaf Expansion complete | **Pass** (`a0-gutenberg-leaf-expansion-complete`) |
| A.8 Fluent Forms first bridge complete | **Pass** (`a8-fluentforms-contact-integration-complete`) |
| ADR-0013 **Accepted** | **Pass** |
| ADR-0016 **Accepted** | **Pass** |
| ADR-0017 **Accepted** | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| Integration API v1 unchanged | **Pass** |
| No existing `docs/plans/A7*` plan | **Pass** |
| `main` clean @ `b4932a0ae…` | **Pass** |

If any precondition regresses before a wave starts coding: **STOP**.

---

## 3. Goals

1. Freeze the A.7 family as four implementation waves (**A.7a–A.7d**) with clear visitor boundaries.
2. Require per-surface admission (ownership, identity, extraction, overlay, lifecycle, diagnostics, platform path, browser evidence).
3. Keep WooCommerce as foreign canonical owner of products, carts, checkout, accounts, and emails.
4. Reuse existing AIML subsystems unchanged (Store, Workspace, Review, TM, Glossary, Jobs, Gutenberg, Elementor, Integration API v1).
5. Fail closed: missing/unsupported/incompatible → source fallback; no fatals; no fuzzy rematch.
6. Deliver Tier 0 + wave-targeted browser acceptance with **rendered FP = 0** and **language leakage = 0** on admitted surfaces.
7. Prevent scope sprawl into extensions (subscriptions, BTCPay, multicurrency, inventory admin, etc.).

---

## 4. Frozen contracts (carry forward — do not reopen)

| Contract | Role |
|---|---|
| ADR-0001 | Overlay architecture / canonical ownership boundaries |
| ADR-0007 | Platform principles relevant to Store/identity discipline |
| ADR-0013 | Gutenberg segment identity (`b:`) |
| ADR-0016 | Elementor identity and ownership (`e:`) |
| ADR-0017 | Plugin Integration Framework ownership + `p:` identity |
| Integration API v1 | Typed plugin integrations; `PluginIdentity` |
| Schema TARGET | **6** — no bump for A.7 family plan |

Preserve subsystems: Store, Workspace, Review, TM, Glossary, Jobs, Gutenberg extract/render, Elementor extract/overlay, Fluent Forms A.8 bridge.

**Forbidden family-wide:**

- second translation pipeline
- Store / schema redesign
- HTML scraping / unscoped output buffering / DOM rewrite as primary strategy
- fuzzy identity
- mutating `wp_posts` / `wp_postmeta` / Woo tables as a translation mechanism
- wp-admin translation
- ownership of WooCommerce business logic

---

## 5. Ownership model (frozen)

| Party | Owns |
|---|---|
| **WooCommerce** | Persistence, lifecycle, rendering, business logic, product/order/cart data |
| **AIML** | Translation overlays, Store rows, Review axis, TM write-back policy, Glossary, Jobs orchestration, diagnostics |
| **Theme / Elementor / Gutenberg** | Their own document surfaces when they host product chrome (admit via existing `e:` / `b:` paths — not as Woo ownership) |

**Rules:**

- Never write translated strings back into WooCommerce product/order/email templates as the translation store.
- Never treat AIML Store as a shadow catalog.
- Copy/delete/rename of products follow Woo lifecycle; AIML keys must not fuzzy-rematch after owner identity change.
- Local failure → source value + continue.

---

## 6. Visitor boundary (frozen)

### In scope (family)

Visitor/customer-facing storefront output only:

- product and catalog presentation
- archives / search results presentation (visitor labels)
- cart / checkout / account / order-received presentation
- WooCommerce-core customer emails

### Explicitly out (family)

- `wp-admin` and REST merchant tooling
- merchant settings, reports, analytics
- inventory management UI
- order management (merchant)
- coupons / shipping / tax **admin**
- payment gateway admin
- third-party commerce plugins (BTCPay, WOOCS, inventory overview, promotions, …) — later milestones
- subscriptions, memberships, bookings, composites, bundles, product add-ons

---

## 7. Implementation waves (frozen)

This plan **refines** the earlier illustrative §6.2 wave table. Authoritative wave boundaries for A.7 are below.

### A.7a — Product & catalog content

**Focus:** Single-product visitor surfaces and catalog field content owned by Woo / product documents.

Illustrative candidates (admission required; not automatic support):

- product title
- short description
- long description / content
- product tabs (visitor-visible)
- attributes (visitor-visible labels/values only)
- product notices
- breadcrumbs where Woo (not theme) owns the string
- variation visitor labels where deterministic

**Also reuse:** Gutenberg/Elementor content already on the product page via existing `b:` / `e:` paths — do not re-admit as Woo-specific identities.

**Deferred from A.7a:** cart/checkout, archives-as-listing chrome, emails, account.

### A.7b — Archives

**Focus:** Shop / taxonomy / search listing chrome with deterministic owners.

Illustrative candidates:

- shop archive titles/descriptions where Woo owns them
- category / tag archive titles and descriptions
- search results labels (visitor)
- archive notices
- ordering / sorting labels
- pagination text
- layered navigation / filter labels **only** when ownership + identity are deterministic

**Stop / defer** any archive filter widget whose labels are runtime-dynamic without a stable ID.

### A.7c — Customer workflow

**Focus:** Session-bound customer journey UI owned by Woo templates/hooks.

Illustrative candidates:

- cart / mini-cart display strings
- checkout display strings (not payment-processor chrome)
- customer account pages
- order received / thank-you display
- customer-visible notices in these flows

**Hard constraints:** no payment-gateway HTML scraping; no capturing card fields; source fallback on dynamic totals formatting where identity is unsafe.

### A.7d — WooCommerce-owned customer emails only

**Focus:** Core WooCommerce customer email templates AIML can overlay safely.

Illustrative candidates:

- customer processing order
- customer completed order
- customer invoice / order details (customer-facing)

**Explicitly exclude:** emails owned by third-party plugins, merchant admin emails, SMS, marketing automation.

---

## 8. Admission philosophy (frozen)

**No blanket WooCommerce support.**

Every admitted surface must record:

| Gate | Evidence |
|---|---|
| Ownership | Foreign owner + ownership class (`record` / `document` / `shared-definition` / unsupported) |
| Identity | Deterministic key via existing serializer families only |
| Extraction | Official Woo/WP/Elementor/Gutenberg APIs — allowlisted |
| Overlay | Official hooks/filters/template points — no scrape |
| Lifecycle | missing / inactive / version / disabled / delete / rename |
| Diagnostics | Bounded counters; no bodies/secrets |
| Workspace | Visible/editable via existing `plugin_integration` or document surfaces |
| Review / TM / Glossary / Jobs | Existing pipelines only |
| Browser | EN/SV; FP=0; leakage=0; source fallback |

Disposition per surface: **Supported** / **Experimental** / **Deferred**.

Wave closure requires all **Supported** surfaces in that wave’s frozen allowlist to pass gates — not “Woo works in general.”

---

## 9. Identity strategy (research freeze — do not invent keys yet)

**A70–A72 must research and freeze preferred identity mapping before extraction coding.**

### Preferred reuse order (planning hypothesis — confirm with evidence)

| Surface class | Preferred identity path | Notes |
|---|---|---|
| Product Gutenberg blocks | ADR-0013 `b:` | Product is a WP post |
| Product Elementor widgets | ADR-0016 `e:` | Document-owned Elementor on product |
| Product post fields (title, excerpt, content) where already covered by post pipeline | Existing post/Store path | Do not duplicate |
| Woo-specific visitor fields (attributes, notices, cart labels, email subject/body fragments) | Integration API v1 `p:` via `PluginIdentity` | Likely `integration_id = woocommerce` (confirm at A72) |
| Shared storefront definition strings | `shared-definition` only if Store resolution is proven safe | Apply A.8 lesson: post-scoped Store lookup cannot fake site-global shared strings without a framework decision |

### Hard identity rules

- Deterministic only
- No source text in identity
- Source hash = freshness only
- No fuzzy rematch after rename/delete
- No new identity family without ADR
- Keys ≤ Store limit (191) via framework serializers

**A72 deliverable:** identity matrix table mapping each A.7a candidate → family + components + ownership class + stop/defer reasons.

---

## 10. Extraction / overlay strategy (family)

1. Prefer official WooCommerce / WordPress filters and template hooks.
2. Reuse Gutenberg/Elementor extractors for document content already supported.
3. Register Woo-specific units through Integration API v1 when they are plugin-owned visitor strings.
4. Overlay only when compatibility allows; miss/stale/error → source.
5. One field failure must not break the product/cart/checkout page.
6. No generic HTML scraping; no unrestricted product JSON/meta walkers.

---

## 11. Lifecycle / compatibility (family)

| State | Behavior |
|---|---|
| WooCommerce missing / inactive | Integration unavailable — source |
| Unsupported Woo version | `unsupported_version` — source |
| Required hook missing | `missing_required_hook` / degrade — source |
| Integration/wave disabled | Store retained; no overlay |
| Product deleted | No extract; overlay source fallback; Store history retained |
| Attribute renamed | New identity; **no** fuzzy remap to old key |
| Reactivation | Resume only after compatibility PASS |

Supported version floor: freeze per wave from live evidence (dev currently WooCommerce **10.9.4** at plan authoring — re-verify at A70). Do not claim broader compatibility than evidence.

---

## 12. Platform reuse (frozen)

Must use unchanged:

- Store
- Workspace
- Suggestions
- Review
- TM (approval-gated)
- Glossary
- Background Jobs
- Integration diagnostics conventions

No Woo-specific Store, Review queue, TM, or Jobs pipeline.

---

## 13. Work packages (family sequence A70–A78)

Wave implementations may repeat a tightened subset of A71–A77 after family A70–A72. Commit boundaries stay per WP / per wave.

### A70 — Baseline

| | |
|---|---|
| **Objective** | Open validation log; confirm Woo active/version; schema TARGET=6; no A.7 code yet |
| **Scope** | Docs + inventory scaffolding |
| **Deps** | This plan frozen on `main` |
| **Validation** | Unit/integration/PluginGuard/PHPCS baseline; `git diff --check` |
| **Rollback** | Revert docs commit |
| **Stop** | Woo inactive; TARGET ≠ 6; ADR regression |
| **Commit** | `docs(woocommerce): establish A.7 baseline` |

### A71 — Inventory

| | |
|---|---|
| **Objective** | Live visitor surface inventory for A.7a–A.7d candidates |
| **Scope** | Docs: surface table, owner, render path, risk |
| **Deps** | A70 |
| **Expected files** | `docs/plans/A7_*_INVENTORY.md` or wave annexes |
| **Validation** | Evidence from WP-CLI / storefront URLs only |
| **Stop** | Cannot determine owner → defer surface |
| **Commit** | `docs(woocommerce): inventory A.7 visitor surfaces` |

### A72 — Identity

| | |
|---|---|
| **Objective** | Freeze identity matrix for first wave (A.7a) and family rules |
| **Scope** | Docs + failing-first identity tests if needed |
| **Deps** | A71 |
| **Validation** | Serializer limits; no new family without ADR |
| **Stop** | Requires Store redesign / fuzzy ID / shared-definition hack |
| **Commit** | `docs(woocommerce): freeze A.7 identity matrix` |

### A73 — Extraction

| | |
|---|---|
| **Objective** | Allowlisted extractors for the current wave’s Supported surfaces |
| **Scope** | `src/` Woo / Integration modules as justified |
| **Deps** | A72 |
| **Validation** | Unit + integration extract; exact unit counts |
| **Stop** | JSON/meta universal walker; scrape |
| **Commit** | `feat(woocommerce): extract A.7{wave} translation units` |

### A74 — Overlay

| | |
|---|---|
| **Objective** | Official-hook overlays for admitted surfaces |
| **Scope** | Frontend bridges/filters |
| **Deps** | A73 |
| **Validation** | Overlay tests; EN untouched; SV applied; no Woo DB mutation |
| **Stop** | HTML scrape / DOM rewrite required |
| **Commit** | `feat(woocommerce): overlay A.7{wave} translations` |

### A75 — Workspace / platform

| | |
|---|---|
| **Objective** | Units visible in Workspace; Review/TM/Glossary/Jobs smoke |
| **Deps** | A74 |
| **Stop** | Woo-specific workflow introduced |
| **Commit** | `feat(woocommerce): connect A.7{wave} units to platform workflow` |

### A76 — Lifecycle / security / diagnostics

| | |
|---|---|
| **Objective** | Compatibility matrix; delete/rename; sanitization; bounded diagnostics |
| **Deps** | A75 |
| **Stop** | Foreign persistence mutation |
| **Commit** | `feat(woocommerce): harden A.7{wave} lifecycle diagnostics` |

### A77 — Acceptance

| | |
|---|---|
| **Objective** | Full Tier 0 + live EN/SV browser matrix for wave allowlist |
| **Deps** | A76 |
| **Validation** | FP=0; leakage=0; Gutenberg/Elementor/A.8 regressions PASS |
| **Commit** | `test(woocommerce): complete A.7{wave} acceptance` |

### A78 — Closure

| | |
|---|---|
| **Objective** | Wave admission disposition; roadmap; tag prep |
| **Deps** | A77 PASS |
| **Commit** | `docs(woocommerce): close A.7{wave}` |

**First coding wave after family freeze:** **A.7a** (product & catalog). Do not start A.7b–A.7d coding until A.7a is independently validated/merged/closed unless roadmap explicitly parallelizes later.

---

## 14. Acceptance criteria (~48)

### Architecture / platform

1. Schema TARGET remains **6**.
2. No Store redesign.
3. No Integration API v1 contract change unless a pre-approved ADR lands first.
4. ADR-0013 / 0016 / 0017 remain Accepted and unreopened for convenience.
5. No second translation pipeline.
6. No WooCommerce persistence mutation for translations.
7. No HTML scraping as primary overlay strategy.
8. No fuzzy identity.
9. No wp-admin translation.
10. PluginGuard PASS.
11. PHPCS PASS on touched paths.
12. Unit suite PASS.
13. Integration suite PASS.
14. Gutenberg regressions PASS.
15. Elementor regressions PASS.
16. A.8 Fluent Forms regressions PASS.
17. Integration API reference fixture remains contained (tests only).

### Family / process

18. Surfaces admitted only via written admission records.
19. Each Supported surface has ownership + identity + extraction + overlay evidence.
20. Deferred surfaces listed with reasons (no silent skip).
21. Wave allowlists are exclusive (anything not listed is out).
22. Diagnostics are bounded (no source/translation bodies).

### Identity / extraction / overlay

23. Identities use only frozen families (`b:`, `e:`, `p:`, existing post paths).
24. Keys built via framework serializers only (no hand concatenation).
25. Source hash is freshness only.
26. Extract emits only allowlisted units.
27. Overlay uses official hooks/filters.
28. Store miss → source.
29. Stale policy safe (no wrong-language publish).
30. One-field failure isolated.
31. Default language unchanged.
32. Renamed owner field does not fuzzy-rematch.

### Platform path

33. Workspace lists admitted units.
34. Manual edit/save works.
35. Review approve works.
36. Review reject/resubmit works.
37. TM approval write-back respects policy.
38. Glossary/Suggestions path works.
39. Jobs compatibility for materialized units.

### Live / quality

40. Live EN source correct on admitted surfaces.
41. Live SV overlays correct on admitted surfaces.
42. Rendered FP = 0 on admitted surfaces.
43. Language leakage = 0 on admitted surfaces.
44. Browser matrix recorded in validation log.
45. Foreign Woo source audit PASS (before/after).
46. Disabled integration → source; Store retained.
47. Reactivation recovers overlays after compatibility PASS.
48. Performance notes recorded; no invented budgets; no global catalog crawl.

---

## 15. Stop conditions

**Stop the wave / family immediately if implementation would require:**

- Store redesign
- schema redesign (TARGET bump without ADR)
- WooCommerce persistence ownership / shadow catalog
- HTML scraping / unscoped buffering / DOM rewrite as architecture
- fuzzy identity
- second pipeline / renderer replacement / walker replacement
- new public architecture without ADR
- reopening ADR-0001 / 0007 / 0013 / 0016 / 0017 for convenience
- translating merchant admin surfaces
- pulling third-party commerce plugins into A.7

Candidate-local failure → **defer that surface**. Do not weaken the family contract.

---

## 16. Out of scope (explicit)

- Subscriptions / memberships / bookings
- Composite products / bundles / product add-ons
- BTCPay / multicurrency / geo-pricing plugins
- Inventory workflows / merchant dashboards
- Rank Math / SEO (A.SEO)
- Additional third-party bridges (later A.8 waves)
- Age Gate / CookieYes
- Theme redesign / storefront redesign projects

---

## 17. Documentation / roadmap (this planning task)

Create this plan. Update editorial pointers only in:

- [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — link A.7 plan; align §6.2 wave names with this freeze
- [../ROADMAP.md](../ROADMAP.md) — pointer only

No ADR. No production code. No implementation branch.

---

## 18. Sequencing after plan freeze

1. Architecture review / fast-track freeze (no new contracts expected).
2. Merge planning branch to `main` (authoritative copy).
3. Open **A.7a** implementation branch only.
4. Execute A70→A78 for **A.7a**.
5. Tag A.7a closure independently.
6. Plan/implement A.7b → A.7c → A.7d sequentially unless roadmap explicitly says otherwise.

---

## 19. Fast-track freeze

This plan introduces **no new architectural contract** beyond consuming existing ADRs + Integration API v1.

Expected freeze verdict after review:

- **Status: Architecture Frozen**
- Family plan authorized on `main`
- **A.7a planning/implementation** may begin next
- **No further A.7 family planning cycle** unless a stop condition forces ADR work

---

## 20. Risks

| Risk | Mitigation |
|---|---|
| Woo surface sprawl | Hard wave allowlists; admission records |
| Shared storefront strings vs post-scoped Store | Defer or ADR; apply A.8 lesson |
| Cart/checkout dynamic strings | Deterministic IDs only; else defer |
| Email HTML complexity | Plain/allowlisted fragments first; sanitize carefully |
| Theme vs Woo ownership ambiguity | Inventory owner evidence; defer ambiguous |
| Extension bleed (BTCPay, etc.) | Explicit out-of-scope list |

---

## 21. ADR assessment

**Verdict at plan authoring:** No new ADR required to **plan** A.7.

**Possible later ADR triggers (wave-local):**

- site-scoped shared-definition Store resolution for global Woo chrome
- a new identity family beyond `b:` / `e:` / `p:` / existing post paths
- email overlay model that cannot use Integration API v1 safely

If triggered: **STOP coding**, write ADR, accept, then resume.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/A7_WOOCOMMERCE_VISITOR_COVERAGE_IMPLEMENTATION_PLAN.md` |
| Planning branch | `feature/a7-woocommerce-visitor-coverage-plan` |
| Implementation | Per-wave branches after freeze (first A.7a) |
| Baseline | `main` @ `b4932a0aeedc8d0304c7a0d8de941358f3fa1f82` |
| Roadmap §6.2 | Wave boundaries refined by this plan (authoritative) |
