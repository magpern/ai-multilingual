# Product Priorities — AI Multilingual

**Status:** Canonical product-direction document
**Date:** 2026-08-12
**Scope:** Implementation priority and product strategy only
**Does not change:** Architecture, ADRs, schemas, APIs, or historical roadmap milestone IDs

Milestone IDs and long-term program catalogs remain defined in the frozen long-term roadmap: [`plans/POST_V1_PLATFORM_ROADMAP.md`](plans/POST_V1_PLATFORM_ROADMAP.md) (Roadmap **v1.0**). **Post-v1.1 Translation Intelligence & Quality (TQ.0–TI.7)** is governed by [`plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md`](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md) (**Complete**). **Operator Translation Lifecycle (OTL.0–OTL.6)** is governed by [`plans/OTL_PARENT_IMPLEMENTATION_PLAN.md`](plans/OTL_PARENT_IMPLEMENTATION_PLAN.md) (**Complete**). **Translation Surface Coverage (TSC.0–TSC.6)** is governed by [`plans/TSC_PARENT_IMPLEMENTATION_PLAN.md`](plans/TSC_PARENT_IMPLEMENTATION_PLAN.md) (**Architecture Frozen** on `main`; **TSC.0–TSC.3 Complete**). This document records **which** program to pursue next when priorities conflict. It is not an implementation plan.

**Current next decision:** Plan/implement **TSC.4** only when separately authorized. **Do not** start TSC.4 until authorized. **TSC.0–TSC.3 Complete.** **TIQ Complete.** **OTL Complete.** **v1.3.0 released** (tag `v1.3.0`). Runtime `Migrator::TARGET` **7**. TSC production architecture is **site-neutral**.

---

## 1. Product strategy

### Principle 1 — Visitor-facing multilingual commerce first

The primary objective is a **completely multilingual WordPress/WooCommerce storefront experience** for a generic, publicly releasable / SaaS-capable product.

Platform breadth is secondary to visitor-facing coverage quality.

Whenever priorities conflict, prefer completing visitor-facing translation over adding new platform capabilities.

Historical note: early product sequencing used the Biopentra webshop as the motivating test site. That does **not** authorize Biopentra-specific production architecture. **TSC is site-neutral.**

### Principle 2 — Finish the customer experience before ecosystem expansion

Remaining priority order (highest first):

1. WooCommerce visitor experience
2. WordPress visitor chrome
3. SEO
4. Production integrations required by real deployments (generic adapters only)
5. Translator UX
6. Operational tooling
7. Translation intelligence
8. Public SDK / ecosystem

---

## 2. Current implementation priority

### Active next program (post-v1.3.0)

| Order | Program | Focus |
|---|---|---|
| 1 | **TSC — Translation Surface Coverage** | Parent: [`plans/TSC_PARENT_IMPLEMENTATION_PLAN.md`](plans/TSC_PARENT_IMPLEMENTATION_PLAN.md). **TSC.0–TSC.3 Complete** — [`TSC0`](plans/TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md), [`TSC1`](plans/TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md), [`TSC2`](plans/TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md), [`TSC3`](plans/TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md). Ladder **TSC.4–TSC.6** not started. |
| — | **OTL — Operator Translation Lifecycle** | **COMPLETE** (OTL.0–OTL.6). Parent: [`plans/OTL_PARENT_IMPLEMENTATION_PLAN.md`](plans/OTL_PARENT_IMPLEMENTATION_PLAN.md). |

**Released:** AI Multilingual **v1.3.0** (tag `v1.3.0`). Prior: **v1.2.0**, **v1.1.0**. **A.SEO** Complete. **TIQ (TQ.0–TI.7)** Complete. **OTL** Complete. `Migrator::TARGET` is **7**.

Visitor-facing Program A waves below remain listed for historical priority context. Coverage-Deferred surfaces stay Deferred unless admitted through the frozen TSC matrix / milestone plans. TSC must remain generic and site-neutral — Biopentra may be used as a test site, never as the production-domain model.

### Historical Program A priority (complete)

| Order | Milestone | Focus | Status |
|---|---|---|---|
| 1 | **A.7b** | WooCommerce Archive Chrome | Complete |
| 2 | **A.7c** | WooCommerce Customer Journey | Complete |
| 3 | **A.7d** | WooCommerce Customer Emails | Complete |
| 4 | **A.6** | Remaining WordPress visitor chrome | Complete |
| 5 | **A.SEO** | Visitor SEO (A.SEOa–A.SEOf) | **Complete** |

**A.7a** / **A.7b** / **A.7c** / **A.7d** are complete on `main` (A.7d tag `a7d-woocommerce-customer-emails-complete`; ADR-0018 implemented; Supported CE1–CE6/CE9–CE10 subject+heading; CE7/CE8 Deferred). **A.6** is **Complete** (tag `a6-wordpress-visitor-chrome-complete`; Supported N1). **A.SEO** is **Complete** (family closed; tag `a-seof-seo-diagnostics-complete`).

#### A.7c — WooCommerce Customer Journey

Examples:

- Cart
- Mini cart
- Checkout
- My Account
- Customer notices
- Order received

**Goal:** A customer should be able to shop entirely in their selected language.

#### A.7d — WooCommerce Customer Emails

Examples:

- Processing Order
- Completed Order
- Customer Invoice
- Customer Notes

**Goal:** Customer communication should remain in the customer's chosen language.

#### A.6 — WordPress visitor chrome

Examples:

- Navigation
- Widgets
- Theme visitor UI
- Visitor-facing gettext
- Remaining supported shortcode bridges

#### A.SEO — Complete

A.SEO was completed as waves A.SEOa–A.SEOf under [ASEO_PARENT_IMPLEMENTATION_PLAN.md](plans/ASEO_PARENT_IMPLEMENTATION_PLAN.md). Remaining SEO Deferred items (for example translated leaf slugs, some social/sitemap surfaces) stay Deferred and are **not** absorbed into TIQ.

---

## 3. Production integrations

The goal is **not** to support every plugin.

Only implement integrations with a concrete product justification and a deterministic overlay/identity seam. Prefer generic Integration API adapters over site-specific hardcoding.

| Priority | Integration | Notes |
|---|---|---|
| High | Fluent Forms | Complete (A.8); **TSC.0 remediated hardcoded form/page IDs** (host-local discovery; stale remains Unsupported) |
| High | Age Gate | Candidate for a later generic options/shared-definition adapter (Deferred under TSC until ADR/host model) |
| Later (possible) | CookieYes | Only if justified with an overlay-safe seam |

---

## 4. Platform maturity (after visitor-facing translation)

Visitor-facing Program A completion for the Biopentra webshop baseline (A.7 / A.6 / A.SEO Supported sets) is done as of **v1.1.0**. Post-v1.1 sequencing for intelligence and quality:

### Translation Intelligence & Quality (TIQ) — Complete

Authoritative parent: [TIQ_PARENT_IMPLEMENTATION_PLAN.md](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md).

Ladder: **TQ.0 → TI.1 → TI.2 → TI.3 → TI.4 → TI.5 → TI.6 → TI.7** — all **Complete** on `main`.

Frozen architecture: measurement → structural safety → bounded context → TM intelligence → deterministic QA → explainable risk assessment → operational Jobs hardening → controlled publication.

This superseded the earlier product-direction preference that Program C and Program D automatically precede Program B after visitor work. Historical Program B milestone IDs (B.1–B.8) remain in the long-term roadmap catalog; **post-v1.1 work followed TIQ**, not early B.1 (additional providers).

### Later (separate product decisions)

### Operator Translation Lifecycle (OTL) — COMPLETE

Authoritative parent: [OTL_PARENT_IMPLEMENTATION_PLAN.md](plans/OTL_PARENT_IMPLEMENTATION_PLAN.md).

Ladder: **OTL.0 → OTL.1–OTL.6** (all Complete). Orchestration/presentation over frozen TIQ services. Public/SaaS neutrality is a hard invariant.

### Translation Surface Coverage (TSC) — TSC.0–TSC.3 Complete

Authoritative parent: [TSC_PARENT_IMPLEMENTATION_PLAN.md](plans/TSC_PARENT_IMPLEMENTATION_PLAN.md).

Ladder: **TSC.0 → TSC.1 → TSC.2 → TSC.3 → TSC.4 → TSC.5 → TSC.6**. Site-neutral surface coverage around the existing Store. **STATE A / TARGET 7.** **TSC.0–TSC.3 Complete** on `main`. **TSC.4–TSC.6** not started. Next: **TSC.4** only when separately authorized.

### Historical Program C — Translator experience

Examples retained in the long-term catalog: Workspace UX, filtering, keyboard shortcuts, better review workflow.

**Historical Program C remains preserved for roadmap history. Where Program C items overlap operator translation lifecycle concerns, OTL supersedes them as the active authoritative program. Program C must not be independently resumed in parallel with OTL.**

### Program D — Operational maturity

Examples: Diagnostics, monitoring, performance tooling, maintenance, backup/export.

### Historical Program B catalog

Examples retained in [POST_V1_PLATFORM_ROADMAP.md](plans/POST_V1_PLATFORM_ROADMAP.md): providers, prompts, terminology depth, confidence, benchmarking, retranslation, optimisation. TIQ reshapes post-v1.1 execution order (measure first; providers Deferred).

---

## 5. Program E — intentionally deferred

Program E (Platform Ecosystem) remains intentionally deferred.

Existing architecture — Integration API v1, ADR-0017, provider contracts, and public interfaces — is designed so Program E can resume later without architectural redesign.

Do **not** expand SDKs, marketplaces, certification, or ecosystem tooling unless there is a concrete commercial requirement.

---

## 6. Governance

| Concern | Canonical document |
|---|---|
| Long-term programs, milestone IDs, freezes, architecture boundaries | [`plans/POST_V1_PLATFORM_ROADMAP.md`](plans/POST_V1_PLATFORM_ROADMAP.md) |
| **TIQ program (TQ.0–TI.7) architecture** | [`plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md`](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md) (**Complete**) |
| **OTL program (OTL.0–OTL.6) architecture** | [`plans/OTL_PARENT_IMPLEMENTATION_PLAN.md`](plans/OTL_PARENT_IMPLEMENTATION_PLAN.md) (**Complete**) |
| **TSC program (TSC.0–TSC.6) architecture** | [`plans/TSC_PARENT_IMPLEMENTATION_PLAN.md`](plans/TSC_PARENT_IMPLEMENTATION_PLAN.md) (**Architecture Frozen** on `main`) |
| **TSC.0 Internal Surface Capability Foundation** | [`plans/TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md`](plans/TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md) (**Complete** on `main`) |
| **TSC.1 First-Class Taxonomy Terms** | [`plans/TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md`](plans/TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md) (**Complete** on `main`) |
| **TSC.2 Registered Meta Translation Surfaces** | [`plans/TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md`](plans/TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md) (**Complete** on `main`) |
| **TSC.3 WooCommerce Extended Translation Surfaces** | [`plans/TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md`](plans/TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md) (**COMPLETE** on `main`; [validation log](plans/TSC3_VALIDATION_LOG.md)) |
| **OTL.0 Foundations milestone plan** | [`plans/OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md`](plans/OTL0_FOUNDATIONS_IMPLEMENTATION_PLAN.md) |
| **TQ.0 milestone plan** | [`plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md`](plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md) |
| **TI.1 milestone plan** | [`plans/TI1_PERSIST_PATH_STRUCTURAL_SAFETY_IMPLEMENTATION_PLAN.md`](plans/TI1_PERSIST_PATH_STRUCTURAL_SAFETY_IMPLEMENTATION_PLAN.md) |
| **TI.2 milestone plan** | [`plans/TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md`](plans/TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md) |
| **TI.3 milestone plan** | [`plans/TI3_TRANSLATION_MEMORY_INTELLIGENCE_IMPLEMENTATION_PLAN.md`](plans/TI3_TRANSLATION_MEMORY_INTELLIGENCE_IMPLEMENTATION_PLAN.md) |
| **TI.4 milestone plan** | [`plans/TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md`](plans/TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md) |
| **TI.5 milestone plan** | [`plans/TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md`](plans/TI5_EVIDENCE_BASED_REVIEW_RISK_SIGNALS_IMPLEMENTATION_PLAN.md) |
| **TI.6 milestone plan** | [`plans/TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md`](plans/TI6_JOBS_SCALE_SAFETY_POLISH_IMPLEMENTATION_PLAN.md) |
| **TI.7 milestone plan** | [`plans/TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md`](plans/TI7_CONTROLLED_AUTO_PUBLICATION_POLICY_IMPLEMENTATION_PLAN.md) (**Complete** on `main`) |
| **ADR-0020 (TI.7 publication)** | [`adr/0020-controlled-auto-publication-and-frontend-gate.md`](adr/0020-controlled-auto-publication-and-frontend-gate.md) (**Accepted**; implemented) |
| **Implementation priority / product direction** | **This file** |
| Classic M0–M7 / Strategy F status (historical) | [`ROADMAP.md`](ROADMAP.md) |
| Historical v1 platform-track archive | [`plans/POST_V1_PRODUCT_ROADMAP.md`](plans/POST_V1_PRODUCT_ROADMAP.md) |

**Rules:**

- This document may evolve when product strategy changes.
- Changes here must **not** renumber historical milestones, rewrite ADRs, or alter frozen platform principles.
- TIQ child plans must name a TIQ milestone (for example `TQ.0`) and obey [TIQ_PARENT_IMPLEMENTATION_PLAN.md](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md).
- OTL child plans must name an OTL milestone (for example `OTL.0`) and obey [OTL_PARENT_IMPLEMENTATION_PLAN.md](plans/OTL_PARENT_IMPLEMENTATION_PLAN.md).

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/PRODUCT_PRIORITIES.md` |
| Kind | Product-direction / implementation-priority guidance |
| Companion roadmap | `docs/plans/POST_V1_PLATFORM_ROADMAP.md` (v1.0, frozen) |
