# A.SEOa Evidence — Rewrite Analysis

**Status:** Investigation complete (planning)  
**ADR:** [0002-prefix-strip-routing.md](../../adr/0002-prefix-strip-routing.md)

---

## 1. Rewrite ownership

| Party | Owns |
|---|---|
| WordPress | Core rewrite rules, flush lifecycle, pretty permalink structures |
| WooCommerce | Product / taxonomy rewrite registration and bases |
| AIML | **None** — ADR-0002 explicitly chooses prefix-strip over per-language rewrite duplication |

## 2. Translated rewrite bases

ADR-0002 consequence (verbatim intent):

> Translated rewrite bases (`/sv/produkt/`) are not possible under this model and would reopen the decision.

**A.SEOa freeze:** the following bases remain **Deferred** (not Supported) unless a future focused ADR reopens ADR-0002:

- `product` (Woo product base)
- `product-category` / category bases
- `product-tag` / tag bases
- `author`
- `feed`
- pagination bases
- any other rewrite base string

Slug **leaf** translation (the `%postname%` / term slug segment) is a separate candidate from rewrite-base translation and is evaluated in the admission matrix under Store/Router constraints — not by translating bases.

## 3. Permalink regeneration / flush

| Event | Owner | AIML |
|---|---|---|
| Permalink settings change | WordPress / Woo | Must not introduce runtime rewrite rebuilding |
| Plugin activation/deactivation | WordPress | AIML must not leave orphaned rewrite state (ADR-0002: none registered) |
| Migration | AIML Migrator | TARGET **6** — no rewrite table |

## 4. Architecture limit

Any candidate that **requires** translated rewrite bases → **Deferred** + ADR-0002 reopen — do not redesign silently.
