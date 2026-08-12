# TSC.3 WooCommerce Extended Translation Surfaces — Planning Freeze Validation Log

**Status:** **TSC.3 Architecture Frozen** (planning) — production implementation **COMPLETE** on `main` (see [TSC3_VALIDATION_LOG.md](TSC3_VALIDATION_LOG.md))
**Authoritative plan:** [TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md](TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_IMPLEMENTATION_PLAN.md)
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md)
**ADR:** **None**

## Freeze record

| Field | Value |
|---|---|
| Planning baseline main HEAD | `02cac23c4b3292a50804d74c892e97ac4a729868` |
| Baseline drift | None; `main` == `origin/main` at materialization; version **1.3.0**; TARGET **7** |
| Materialization path | Docs-only direct to `main` (no planning PR / no docs branch) |
| Plan source | Externally reviewed amended Cursor plan · re-review **TSC.3 EXTERNAL RE-REVIEW: PASS — FREEZE** |
| External review history | Initial proposal → **AMEND** (five amendments) → amended plan → **PASS — FREEZE** |
| Plugin version | **1.3.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema / migration | None (**STATE A**) |
| New ADR | **None** |
| Production implementation | **COMPLETE** — merge `d7a7545d2b64ee188058ada8acfed8fefd5b1dea` |
| TSC.4–TSC.6 | **NOT STARTED** |
| Tag | No new tag; existing `v1.3.0` unchanged |
| Feature branch | Merged / deleted |

## External amendments incorporated

1. **One authoritative global attribute-label translation** — canonical `p:woocommerce:attribute:{attribute_id}:label`; stop emitting taxonomy-backed product P5/P7; legacy taxonomy P5/P7 compatibility read-only; product-local P5/P7 remains authoritative; no permanent dual-write; no catalog-wide migration.
2. **Shop technical host reassignment** — bounded Store `rehost_segments` for attribute-label rows on `woocommerce_shop_page_id` change; temporary old-host read during race; no fake IDs; no catalog scan.
3. **Technical host ≠ semantic authority** — narrow IntegrationSegmentAuthority facts (`manage_product_terms`, attribute exists/public); TI.7 remains publication-policy owner.
4. **Exact global vs local `woocommerce_attribute_label` algorithm** — frozen steps + context table + visitor guards; variation machine identity never translated.
5. **Email subject/heading stale frozen PARTIAL** — allowlisted `woocommerce_{email_id}_settings` dirty covered for standard subject/heading; invoice paid + refunded full/partial variant keys uncovered.

## STATE A reasoning

- Global attribute labels use existing `post` BIGINT shop technical host + ADR-0017 `p:` segment keys — no new `source_type`.
- Bounded `Store::rehost_segments` is a behavioral API extension, not a schema change.
- IntegrationSegmentAuthority supplies facts/mechanics only; does not invent a second Store or policy engine.
- Surfaces that would require non-BIGINT option identity (gateways/shipping) remain Deferred.
- Email stale uses existing checkout technical host; no options source domain.

## TARGET / schema verdict

**STATE A · TARGET 7 · no migration · no new source_type · no second Store.**

## ADR verdict

**No new ADR.** Direct application of ADR-0001 (overlay), ADR-0017 (`p:` / plugin ownership), ADR-0021 (`pa_*` values vs attribute labels), ADR-0018 (transactional email language context unchanged).

## Matrices frozen

| Matrix | Count / range |
|---|---|
| WC | WC1–WC40 |
| AC | AC1–AC38 |
| WP | TSC3.0–TSC3.7 |

## Frozen architecture decisions (non-exhaustive)

| Decision | Freeze |
|---|---|
| Canonical identity | `p:woocommerce:attribute:{attribute_id}:label` |
| Store host | Shop page = technical host only |
| Writable authority | One writable global attribute-label translation |
| Legacy taxonomy P5/P7 | Compatibility-only / read-only |
| Product-local P5/P7 | Authoritative |
| `pa_*` term values | TSC.1 |
| Attribute mutation auth | `manage_product_terms` |
| Publication policy | TI.7 |
| Email subject/heading stale | **PARTIAL** |
| Local attribute values | Deferred |
| Generic options translator | Forbidden |
| Catalog-wide migration | Forbidden |
| Variation machine identity | Never translated |
| TSC.4+ | Not started / out of scope |

## Consistency checks (materialization)

| Check | Result |
|---|---|
| WC numbering contiguous 1–40 | PASS |
| AC numbering contiguous 1–38 | PASS |
| WP ladder TSC3.0–TSC3.7 | PASS |
| STATE A consistent across plan + log | PASS |
| TARGET 7 consistent | PASS |
| Email stale consistently PARTIAL | PASS |
| No claim TSC.3 implemented | PASS |
| No claim TSC.4+ started | PASS |
| No version/tag/release/deployment change | PASS |
| Referenced parent/TSC0–2/ADR paths exist | PASS |
| Docs-only diff | PASS (validated at commit time) |

## Implementation status

**NOT STARTED.** No production code, tests, config, workflows, or dependency changes in this freeze.

## Exact next step

Implement TSC.3 from the frozen `main` baseline using branch `feature/tsc3-woocommerce-extended-translation-surfaces`, followed by independent implementation review, review-fix loop, merge, fresh main CI, and milestone closure.

Do **not** begin implementation in the planning-freeze task.
