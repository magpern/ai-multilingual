# Product Priorities — AI Multilingual

**Status:** Canonical product-direction document
**Date:** 2026-08-09
**Scope:** Implementation priority and product strategy only
**Does not change:** Architecture, ADRs, schemas, APIs, or historical roadmap milestone IDs

Milestone IDs and long-term program catalogs remain defined in the frozen long-term roadmap: [`plans/POST_V1_PLATFORM_ROADMAP.md`](plans/POST_V1_PLATFORM_ROADMAP.md) (Roadmap **v1.0**). **Post-v1.1 Translation Intelligence & Quality (TQ.0–TI.7)** is governed by [`plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md`](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md). This document records **which** program to pursue next when priorities conflict. It is not an implementation plan.

**Current next program:** Translation Intelligence & Quality (TIQ) — parent **Architecture Frozen** on `main`. **TQ.0 Complete**; **TI.1 Complete**; **TI.2 Complete**; **TI.3 Complete**; **TI.4 Architecture Frozen on `main`** — [TI4 plan](plans/TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md); **TI.4 implementation not started**. Exact next step: create `feature/ti4-deterministic-qa-hardening` and implement TI4.0–TI4.8. **TI.5–TI.7** implementation not started.

---

## 1. Product strategy

### Principle 1 — Multilingual webshop first

The primary objective is a **completely multilingual Biopentra webshop**.

Platform breadth is secondary.

Whenever priorities conflict, prefer completing visitor-facing translation over adding new platform capabilities.

### Principle 2 — Finish the customer experience before ecosystem expansion

Remaining priority order (highest first):

1. WooCommerce visitor experience
2. WordPress visitor chrome
3. SEO
4. Production integrations required by Biopentra
5. Translator UX
6. Operational tooling
7. Translation intelligence
8. Public SDK / ecosystem

---

## 2. Current implementation priority

### Active next program (post-v1.1.0)

| Order | Program / milestone | Focus |
|---|---|---|
| 1 | **TIQ** / **TI.4** implementation next | [parent](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md); **TQ.0**/**TI.1**/**TI.2**/**TI.3 Complete**; [TI4 plan](plans/TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md) Architecture Frozen on `main`; TI.4 implementation not started; TI.5–TI.7 not started |

**Released:** AI Multilingual **v1.1.0** (tag `v1.1.0`). **A.SEO** (A.SEOa–A.SEOf) is **Complete**. CI/release baseline is green. `Migrator::TARGET` remains **6**.

Visitor-facing Program A waves below remain listed for historical priority context and are **not** reopened by TIQ. Coverage-Deferred surfaces stay Deferred unless a separate product decision reopens them.

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

Only implement integrations that Biopentra actually requires. All other integrations need a concrete business justification.

| Priority | Integration | Notes |
|---|---|---|
| High | Fluent Forms | Complete (A.8 first production bridge) |
| High | Age Gate | Required for Biopentra |
| Later (possible) | CookieYes | Only if justified |

---

## 4. Platform maturity (after visitor-facing translation)

Visitor-facing Program A completion for the Biopentra webshop baseline (A.7 / A.6 / A.SEO Supported sets) is done as of **v1.1.0**. Post-v1.1 sequencing for intelligence and quality:

### Translation Intelligence & Quality (TIQ) — active

Authoritative parent: [TIQ_PARENT_IMPLEMENTATION_PLAN.md](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md).

Ladder: **TQ.0 → TI.1 → TI.2 → TI.3 → TI.4 → TI.5 → TI.6 → TI.7**.

This supersedes the earlier product-direction preference that Program C and Program D automatically precede Program B after visitor work. Historical Program B milestone IDs (B.1–B.8) remain in the long-term roadmap catalog; **post-v1.1 work follows TIQ**, not early B.1 (additional providers).

### Later (after TIQ gates / separate product decisions)

### Program C — Translator experience

Examples: Workspace UX, filtering, keyboard shortcuts, better review workflow.

These reduce translation cost and improve long-term maintainability. They do **not** replace TIQ measurement and intelligence work.

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
| **TIQ program (TQ.0–TI.7) architecture** | [`plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md`](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md) |
| **TQ.0 milestone plan** | [`plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md`](plans/TQ0_TRANSLATION_QUALITY_BASELINE_IMPLEMENTATION_PLAN.md) |
| **TI.1 milestone plan** | [`plans/TI1_PERSIST_PATH_STRUCTURAL_SAFETY_IMPLEMENTATION_PLAN.md`](plans/TI1_PERSIST_PATH_STRUCTURAL_SAFETY_IMPLEMENTATION_PLAN.md) |
| **TI.2 milestone plan** | [`plans/TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md`](plans/TI2_BOUNDED_TRANSLATION_CONTEXT_IMPLEMENTATION_PLAN.md) |
| **TI.3 milestone plan** | [`plans/TI3_TRANSLATION_MEMORY_INTELLIGENCE_IMPLEMENTATION_PLAN.md`](plans/TI3_TRANSLATION_MEMORY_INTELLIGENCE_IMPLEMENTATION_PLAN.md) |
| **TI.4 milestone plan** | [`plans/TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md`](plans/TI4_DETERMINISTIC_QA_HARDENING_IMPLEMENTATION_PLAN.md) |
| **Implementation priority / product direction** | **This file** |
| Classic M0–M7 / Strategy F status (historical) | [`ROADMAP.md`](ROADMAP.md) |
| Historical v1 platform-track archive | [`plans/POST_V1_PRODUCT_ROADMAP.md`](plans/POST_V1_PRODUCT_ROADMAP.md) |

**Rules:**

- This document may evolve when product strategy changes.
- Changes here must **not** renumber historical milestones, rewrite ADRs, or alter frozen platform principles.
- TIQ child plans must name a TIQ milestone (for example `TQ.0`) and obey [TIQ_PARENT_IMPLEMENTATION_PLAN.md](plans/TIQ_PARENT_IMPLEMENTATION_PLAN.md).

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/PRODUCT_PRIORITIES.md` |
| Kind | Product-direction / implementation-priority guidance |
| Companion roadmap | `docs/plans/POST_V1_PLATFORM_ROADMAP.md` (v1.0, frozen) |
