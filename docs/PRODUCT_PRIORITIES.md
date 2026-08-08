# Product Priorities — AI Multilingual

**Status:** Canonical product-direction document
**Date:** 2026-08-08
**Scope:** Implementation priority and product strategy only
**Does not change:** Architecture, ADRs, schemas, APIs, milestone definitions, or roadmap program structure

Milestone IDs and program boundaries remain defined in the frozen long-term roadmap: [`plans/POST_V1_PLATFORM_ROADMAP.md`](plans/POST_V1_PLATFORM_ROADMAP.md) (Roadmap **v1.0**). This document records **which** of those milestones to pursue next when priorities conflict. It is not an implementation plan.

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

Freeze the following order for active Program A work. Milestone definitions are unchanged; only sequencing guidance is recorded here.

Work the **first incomplete** milestone in this list. Completed waves remain listed for historical priority context but are not reopened.

### Highest priority

| Order | Milestone | Focus |
|---|---|---|
| 1 | **A.7b** | WooCommerce Archive Chrome — complete Woo-owned archive chrome |
| 2 | **A.7c** | WooCommerce Customer Journey |
| 3 | **A.7d** | WooCommerce Customer Emails |
| 4 | **A.6** | Remaining WordPress visitor chrome |
| 5 | **A.SEO** | Visitor SEO as a first-class completion milestone |

**A.7a** / **A.7b** / **A.7c** / **A.7d** are complete on `main` (A.7d tag `a7d-woocommerce-customer-emails-complete`; ADR-0018 implemented; Supported CE1–CE6/CE9–CE10 subject+heading; CE7/CE8 Deferred). **A.6** (Remaining WordPress visitor chrome) is **Complete** on `main` (tag `a6-wordpress-visitor-chrome-complete`; Supported N1). Next target: **A.SEO** (unless priorities change).

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

#### A.SEO — First-class completion

SEO is a first-class completion milestone for the multilingual webshop.

Future planning will likely split it into multiple implementation milestones covering:

- Translated slugs
- Canonical URLs
- hreflang
- Rank Math integration
- OpenGraph / Twitter metadata
- Sitemap / indexability
- SEO validation

The exact decomposition can be decided later. Do not treat that decomposition as a roadmap renumbering until it is explicitly planned.

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

After visitor-facing translation is complete for the Biopentra webshop, continue in this program order:

### Program C — Translator experience

Examples: Workspace UX, filtering, keyboard shortcuts, better review workflow.

These reduce translation cost and improve long-term maintainability.

### Program D — Operational maturity

Examples: Diagnostics, monitoring, performance tooling, maintenance, backup/export.

### Program B — Translation intelligence

Examples: Better prompting, provider improvements, quality scoring, retranslation policies.

These improve translation quality but are **not** prerequisites for a multilingual webshop.

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
| **Implementation priority / product direction** | **This file** |
| Classic M0–M7 / Strategy F status (historical) | [`ROADMAP.md`](ROADMAP.md) |
| Historical v1 platform-track archive | [`plans/POST_V1_PRODUCT_ROADMAP.md`](plans/POST_V1_PRODUCT_ROADMAP.md) |

**Rules:**

- This document may evolve when product strategy changes.
- Changes here must **not** renumber milestones, rewrite ADRs, or alter frozen platform principles.
- Implementation plans should still name a roadmap milestone (for example `A.7c`) and follow this priority order when choosing what to plan next.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/PRODUCT_PRIORITIES.md` |
| Kind | Product-direction / implementation-priority guidance |
| Companion roadmap | `docs/plans/POST_V1_PLATFORM_ROADMAP.md` (v1.0, frozen) |
