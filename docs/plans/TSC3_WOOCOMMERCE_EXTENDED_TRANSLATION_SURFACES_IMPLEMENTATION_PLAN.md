# TSC.3 — WooCommerce Extended Translation Surfaces Implementation Plan

**Status:** **Architecture Frozen** on `main` — production implementation **NOT STARTED**
**Milestone:** TSC.3 WooCommerce Extended Translation Surfaces
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md) (Architecture Frozen on `main`) §20
**External review:** **FREEZE** (five amendments incorporated; re-review **PASS**) · **STATE A** · **TARGET 7**
**Independent planning review:** **PASS** — [TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_PLANNING_VALIDATION_LOG.md](TSC3_WOOCOMMERCE_EXTENDED_TRANSLATION_SURFACES_PLANNING_VALIDATION_LOG.md)
**ADR:** **None** (application of ADR-0001 overlay + ADR-0017 `p:` technical-host pattern + ADR-0021 `pa_*` value boundary; IntegrationSegmentAuthority is facts/mechanics only)
**Depends on:** AI Multilingual **v1.3.0**; TIQ Complete; OTL Complete; TSC Parent Frozen; **TSC.0–TSC.2 COMPLETE**; `Migrator::TARGET` **7**
**Related:** [TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md](TSC0_INTERNAL_SURFACE_CAPABILITY_FOUNDATION_IMPLEMENTATION_PLAN.md); [TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md](TSC1_FIRST_CLASS_TAXONOMY_TERMS_IMPLEMENTATION_PLAN.md); [TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md](TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md); ADR-0021; ADR-0017; ADR-0018; ADR-0001
**Planning baseline:** `main` @ `02cac23c4b3292a50804d74c892e97ac4a729868`
**Email subject/heading stale:** **PARTIAL** (frozen)
**Schema:** **STATE A** / TARGET **7** — no migration

**This document is the authoritative implementation specification for TSC.3.** Work packages TSC3.0–TSC3.7 are **NOT STARTED**.

**Production implementation status:** **NOT STARTED.**
**TSC.4–TSC.6 implementation status:** **NOT STARTED.**

**Exact next step:** Implement TSC.3 from this frozen plan on branch `feature/tsc3-woocommerce-extended-translation-surfaces`, then independent implementation review, review-fix loop, merge, fresh main CI, and milestone closure. Do **not** bump version/TARGET, tag, release, or deploy as part of planning freeze.

**Prior review history:** `TSC.3 EXTERNAL REVIEW: AMEND` → five amendments → `TSC.3 EXTERNAL RE-REVIEW: PASS — FREEZE`

---

## Amendment response summary (external re-review packet)

| # | Topic | Decision |
|---|---|---|
| 1 | Global attribute-label authority | **One writable canonical** shop-hosted `p:woocommerce:attribute:{attribute_id}:label`; stop emitting taxonomy P5/P7; legacy taxonomy P5/P7 = **read-only compatibility** |
| 2 | Shop host reassignment | **Bounded Store rehost** of attribute-label rows on `woocommerce_shop_page_id` change; temporary old-host read during race; no catalog scan |
| 3 | Technical host ≠ semantic authority | Narrow **IntegrationSegmentAuthority** facts (`manage_product_terms`, attribute exists/public); TI.7 still owns publication policy |
| 4 | Overlay algorithm | Exact global vs local algorithm + context table + visitor guards |
| 5 | Email stale | **PARTIAL** — standard subject/heading option dirty covered; invoice paid + refunded full/partial variant keys uncovered |

---

## 1. Baseline audit (unchanged facts)

| Fact | Evidence |
|---|---|
| HEAD | `02cac23c4b3292a50804d74c892e97ac4a729868` |
| Version / TARGET | 1.3.0 / 7 |
| P5/P7 extract | Both taxonomy and local attrs emitted today; `is_taxonomy()` only changes slug selection ([`WooCommerceIntegration::read_product_attributes`](../../src/Integration/WooCommerce/WooCommerceIntegration.php)) |
| OTL/Jobs/TI.7 auth today | `edit_post` / `is_visitor_public` on Store `source_id` — **ignores** `OWNERSHIP_*` and `p:` owner tokens |
| Store rehost API | **None** (only term `adopt_row_to_identity`) |
| Shop option | `woocommerce_shop_page_id` via `wc_get_page_id('shop')`; **no** AIML observer today |
| Woo attribute admin cap | `manage_product_terms` (Attributes submenu) |
| Email options | `woocommerce_{email_id}_settings`; `updated_option` fires when value changes; AIML does not observe (S12) |
| ADR-0017 | Canonical plugin ownership; **no** “technical host” term — host pattern is plan/code practice |

---

## 2. Current Woo coverage inventory

Unchanged from prior audit for Supported product/term/journey/email chrome extract+overlay. TSC.3 deltas:

- Global taxonomy **label** → new Supported with single-writer contract
- Taxonomy P5/P7 → demoted to compatibility-only
- Local P5/P7 → remain authoritative
- Local values → Deferred
- Email stale → PARTIAL
- S10/S11 stale → Deferred honesty

---

## 3. Coverage disposition matrix (amended)

| Family | Disposition |
|---|---|
| Product title/excerpt/content | SUPPORTED (existing) |
| `pa_*` term values | SUPPORTED (TSC.1) — do not re-own |
| Global attribute taxonomy label | **SUPPORTED** — canonical `attribute_id` segment |
| Product P5/P7 for **taxonomy** attrs | **COMPATIBILITY read-only** (not independently writable) |
| Product P5/P7 for **local** attrs | SUPPORTED (authoritative) |
| Local attribute values | DEFERRED |
| Variation machine values | NOT TRANSLATABLE DATA |
| CE subject/heading extract+overlay | SUPPORTED (A.7d) |
| CE subject/heading **stale** | **PARTIAL** |
| CE invoice paid / refunded full\|partial keys | PARTIAL / out of extract (pre-existing) |
| CE body/footer/CE7/CE8 | DEFERRED |
| B1/B2 + CJ chrome stale | Deferred honesty |
| Gettext | UNSUPPORTED |
| Economic / order / customer data | NOT TRANSLATABLE DATA |
| Gateway/shipping settings | DEFERRED → TSC.6 |
| Gutenberg / Elementor / public API | OUT |

---

## 4. Milestone objective

1. **Single authoritative** translation for each Woo global attribute label, with rename invalidation and shop-page rehost safety, without catalog fan-out.
2. **Correct facts** so shop-page post semantics do not falsely govern attribute-definition translations.
3. **Exact** global vs local `woocommerce_attribute_label` behavior with variation safety.
4. **Honest PARTIAL** email subject/heading stale for allowlisted settings mutations; no pretend completeness for invoice/refunded variant keys.

Local attribute values remain Deferred.

---

## 5–6. Global attribute-label identity + `pa_*` boundary

**Canonical identity:**

```text
p:woocommerce:attribute:{attribute_id}:label
```

`PluginIdentity::build( 'woocommerce', 'attribute', (string) $attribute_id, 'label' )`

**Logical ID:** Woo `attribute_id` (stable across label rename; new ID on delete/recreate).
**Store technical host:** `(post, current shop_page_id)` — host only, not semantic owner.
**Not** `SOURCE_TERM`. **Not** slug/label-as-identity.

**`pa_*` values:** TSC.1 only.

---

## Amendment 1 — Single-writer compatibility contract (frozen)

### A. Global taxonomy attributes (`taxonomy_is_product_attribute` / `pa_*`)

| Concern | Freeze |
|---|---|
| Canonical writable identity | Shop-hosted `p:woocommerce:attribute:{attribute_id}:label` |
| Product extract | **Stop emitting** P5/P7 when `is_taxonomy()` is true |
| Permanent dual-write | **Prohibited** |
| Existing product taxonomy P5/P7 rows | **Compatibility-only** |
| Compatibility readable? | **Yes** — frontend fallback only |
| Compatibility visible in OTL? | **Yes**, marked non-authoritative / read-only |
| Compatibility editable/reviewed/published/retranslated as authority? | **No** — all writers denied for those keys |
| Frontend order | (1) canonical global (2) compatibility product P5/P7 if permitted (3) source label |
| Catalog-wide migration | **Not required** |
| Product sync orphan storm | **Prevented**: retain taxonomy P5/P7 keys on product `sync_source` as compatibility retain-set (mechanics akin to TSC.2 retain), **or** equivalent writer-deny + retain so rows are not wiped while still non-writable |

**Writer prevention (required):** any OTL mutate / bulk / Jobs item apply / publication targeting a product-hosted `attribute_name` / `variation_attribute_name` key whose nested slug resolves to a global product attribute taxonomy is rejected (reason: compatibility / non-authoritative). Canonical global key is the only writable authority for that label.

**No automatic promote/dual-write** from compatibility → canonical (operator translates canonical; compat remains fallback until unused).

### B. Product-local/custom attributes

| Concern | Freeze |
|---|---|
| Authoritative identity | Existing `p:woocommerce:product:{id}:attribute_name\|variation_attribute_name:{slug}` |
| Extract | Unchanged |
| Writers | Unchanged |
| Overlay | Existing product resolve when product context exists |

---

## Amendment 2 — Shop technical host reassignment (frozen)

**Problem:** Store identity includes `source_id = shop_page_id`. Changing `woocommerce_shop_page_id` (old → new) would otherwise orphan translations on the old host even though `attribute_id` is unchanged. Parent already notes remap utility **None today**.

**Chosen path: A — bounded transactional rehost (+ B temporary read compat).**

| Step | Behavior |
|---|---|
| Hook | `update_option_woocommerce_shop_page_id` / `updated_option` when option is `woocommerce_shop_page_id` and value changes |
| Inputs | old_id, new_id (both > 0, old ≠ new) |
| Action | New Store mechanics API (no schema): e.g. `Store::rehost_segments( 'post', $old, 'post', $new, $predicate )` moving **only** rows whose `segment_key` matches `p:woocommerce:attribute:{digits}:label` |
| Preserve | translation text, language_id, status/review/publish axes, hashes where still valid; same `segment_key` |
| Conflict | If new host already has same `(segment_hash, language_id)`, prefer keeping destination row; retire source duplicate as ignored/compat — no dual writers |
| After | `mark_dirty(post, new_id)` → shutdown sync extracts current attribute labels onto new host |
| Race read | Resolver: current shop host first; if miss and rehost pending/failed, read old host once (compat) |
| Failure | Log/diagnostic; leave old rows; do not invent IDs; do not scan products |
| Missing new page | Skip rehost; keep old host readable until valid new_id exists |

**Not chosen:** fake numeric IDs; catalog scan; permanent dual hosts as writers; new `source_type`.

**Historical debt (out of TSC.3 Supported core):** B1/B2 and other shop-hosted chrome lack rehost — unchanged Deferred/Partial honesty. Checkout-hosted email chrome reassignment remains pre-existing debt (not expanded here).

**Tests:** race (read during rehost), failure (new_id invalid), conflict on destination, no product queries.

---

## Amendment 3 — Technical host ≠ semantic authority (frozen)

**Evidence:** OTL `AllowedActionsResolver` uses `edit_post(shop)` for all post-hosted rows; `PublicationService::is_source_public` uses shop `post_status === publish`. That wrongly couples attribute-definition translations to shop page edit/publish semantics.

**Mechanism:** narrow **IntegrationSegmentAuthority** (facts/mechanics only):

```text
applies( row ) -> bool  // p:woocommerce:attribute:{id}:label
exists( row ) -> bool   // wc_get_attribute(id) present
is_visitor_public( row ) -> bool  // attribute exists (definitions are not draftable)
user_can_edit( user, row ) -> bool  // user_can( user, 'manage_product_terms' )
edit_link( row ) -> Woo attribute admin URL when possible
```

**Wiring (facts into existing owners):**

- OTL `capability_flags` / mutate gates: if applies → use `user_can_edit`, **not** merely `edit_post(shop)`
- PublicationService publicness input: row-aware fact when applies → attribute exists; **shop draft must not** yield `SOURCE_NOT_PUBLIC` for these rows
- Jobs item admission: skip/deny attribute-label items unless `manage_product_terms`
- Host `edit_post(shop)` alone **insufficient** for attribute-label mutation
- TI.7 **unchanged** as policy owner — only consumes corrected facts

**Not:** moving publication policy into Woo; second policy engine; changing SurfaceCapability for all posts.

**Capability evidence:** Woo registers Attributes UI with `manage_product_terms`.

---

## Amendment 4 — Exact `woocommerce_attribute_label` algorithm (frozen)

On every filter invocation `( $label, $name, $product )`:

```text
0. Context guard: if non-visitor (admin, REST, AJAX, CRON, feed, embed) OR LanguageContext default
   → return $label unchanged.

1. Normalize $name. If taxonomy_is_product_attribute( $name )
   → GLOBAL path:
   a. attribute_id = wc_attribute_taxonomy_id_by_name( slug )
   b. if attribute_id <= 0 → return $label
   c. Resolve canonical Store translation for
      p:woocommerce:attribute:{attribute_id}:label
      on current shop technical host (direct Store get; not bridge product source_id)
   d. if hit → sanitize_plain → return
   e. else if product context yields product_id > 0:
        try compatibility P5 then P7 keys on that product (read-only)
        if hit → return
   f. else → return $label (source)

2. Else LOCAL / non-taxonomy path:
   a. if product_id resolvable (>0 from $product or get_the_ID when appropriate):
        existing P7-prefer-then-P5 product overlay
   b. else → return $label unchanged

3. Never modify: taxonomy slug, HTML option values, variation request values,
   add-to-cart matching identity, or canonical Woo attribute data.
```

### Overlay context table

| Context | Overlay? | Notes |
|---|---|---|
| Product frontend / additional info table | Yes (visitor) | Global + local algorithms |
| Variation UI labels | Yes (visitor) | Label only; values untouched |
| Layered nav / widgets (often null product) | Yes for **global** | Local without product → source |
| Customer emails calling filter | No if non-visitor guards; email path rarely needs attr labels | Prefer no overlay unless visitor storefront render |
| wp-admin | **No** | Guard |
| REST | **No** | Guard |
| Cron / internal | **No** | Guard |
| AJAX storefront fragments | **Yes** if not admin AJAX misclassified — treat storefront AJAX as visitor when `!is_admin()`; admin-ajax from dashboard blocked by is_admin | Document + test |

Reuse/extend visitor-guard style already used for term_description in WooCommerceIntegration.

---

## 8–10. Local attrs / values / variation safety

- Local **names:** product P5/P7 authoritative (Amendment 1B).
- Local **values:** **Deferred** (A.7a P6/P8 identity failure unchanged).
- **Variation safety:** Supported invariant — translated labels never alter matching identity; AC + tests for variable add-to-cart.

---

## 11–14. Email config / stale (Amendment 5 — frozen PARTIAL)

### Definitive email option audit (EMAIL_ID_ALLOWLIST)

| email_id | option_name | subject keys | heading keys | Admin mutation | Stale coverage in TSC.3 |
|---|---|---|---|---|---|
| customer_processing_order | `woocommerce_customer_processing_order_settings` | `subject` | `heading` | `woocommerce_update_options_email_{id}` → `process_admin_options` → `update_option`; REST `update_option` per field | **Covered** via `updated_option` on option name → dirty checkout |
| customer_completed_order | `woocommerce_customer_completed_order_settings` | `subject` | `heading` | same | **Covered** |
| customer_on_hold_order | `woocommerce_customer_on_hold_order_settings` | `subject` | `heading` | same | **Covered** |
| customer_invoice | `woocommerce_customer_invoice_settings` | `subject`, **`subject_paid`** | `heading`, **`heading_paid`** | same | **Partial** — only unpaid `subject`/`heading` extracted today; paid keys **uncovered** |
| customer_note | `woocommerce_customer_note_settings` | `subject` | `heading` | same | **Covered** |
| customer_refunded_order | `woocommerce_customer_refunded_order_settings` | **`subject_full`**, **`subject_partial`** | **`heading_full`**, **`heading_partial`** | same (partial-refund shares option) | **Partial** — `read_email_chrome` never reads these keys |
| customer_failed_order | `woocommerce_customer_failed_order_settings` | `subject` | `heading` | same | **Covered** |
| customer_cancelled_order | `woocommerce_customer_cancelled_order_settings` | `subject` | `heading` | same | **Covered** |

**Reset/default:** clearing field stores `''`; `read_email_chrome` keeps Woo `get_default_*()` when option empty — final-state observable on next checkout-host sync after dirty.

**Programmatic `update_option`:** covered when serialized value changes (`updated_option`). Identical no-op save: no hook (WP inherent).

**Freeze:**

> **EMAIL SUBJECT/HEADING STALE: PARTIAL**

**Exact uncovered paths:**

1. Invoice **paid** subject/heading keys (`subject_paid` / `heading_paid`) — not in extract; not independently stale-tracked.
2. Refunded **full/partial** subject/heading keys — not in extract; not independently stale-tracked.

**Covered:** `updated_option` / equivalent for the eight `woocommerce_{email_id}_settings` option names → `mark_dirty(post, checkout_page_id)` → shutdown `sync_source` → existing `read_email_chrome` final-state for keys it already understands.

TSC.3 does **not** expand extract to paid/full/partial keys (keeps milestone narrow). WC/AC must say PARTIAL, not Supported.

### Default-template semantics (revalidated)

- TSC.3 **adds stale correctness only** for existing CE chrome.
- Does **not** broaden into gettext translation.
- Effective string remains: merchant non-empty option **or** Woo default template when blank (A.7d unchanged).
- Placeholders stay templates; runtime order/customer data never provider input.

---

## 15–16. Payment/shipping + catalog/gettext boundaries

Unchanged: third-party settings → TSC.6; gettext Unsupported; S10/S11 Deferred honesty.

---

## 17. Source/target persistence

ADR-0001 overlay-only. No canonical Woo writes. Rehost moves AIML Store rows only.

---

## 18. Architecture ownership (amended)

```text
WooCommerceIntegration
  extract (global labels on shop; local P5/P7 on product; no taxonomy P5/P7 emit)
  overlay algorithm (Amendment 4)
  invalidation mappers (attribute CRUD → shop dirty; email options → checkout dirty)
  shop page option → rehost trigger
  IntegrationSegmentAuthority registration for attribute-label keys

Store
  rehost_segments (bounded predicate; no schema)

RequestLocalInvalidationCoordinator
  unchanged contract

OTL / Jobs / PublicationService
  consume IntegrationSegmentAuthority facts where applies
  TI.7 policy unchanged

AdmittedTaxonomies / TermSurfaceAdapter
  pa_* VALUES only

RegisteredMetaRegistry
  no Woo attribute definitions
```

---

## 19–21. OTL / Jobs / TI.7 implications

**OTL:** Canonical attribute-label segments editable only with `manage_product_terms`. Compatibility taxonomy P5/P7 visible read-only. Shop `edit_post` alone insufficient for attribute-label mutate.

**Jobs:** Attribute-label items require `manage_product_terms`. Taxonomy product P5/P7 items not admitted as writable Jobs targets. Snapshots/conflicts reuse post host path.

**TI.7:** Policy unchanged. Publicness fact for attribute-label rows = attribute exists, not shop publish state. Publication still gated by existing eligibility/review rules on the row.

---

## 22. Overlay seam matrix (amended)

| Surface | Authority | Seam | Disposition |
|---|---|---|---|
| Global attr label | canonical shop `attribute:{id}:label` | `woocommerce_attribute_label` | Supported |
| Legacy taxonomy P5/P7 | compatibility read | same filter fallback | Compat only |
| Local attr name | product P5/P7 | same | Supported |
| Email subject/heading | checkout host | email filters | Supported overlay; **stale PARTIAL** |

---

## 23–27. Deletion / defaults / provider / privacy / performance

- Attribute deleted → shop sync drops canonical segment → orphan/ignored.
- Shop reassigned → rehost then sync.
- Provider: labels + existing CE templates only; deny economic/order/PII.
- Performance: O(attrs) extract; O(1) dirty; rehost bounded to matching rows only; no product catalog scan.

---

## 28. PluginGuard

Locks: single writable authority; no taxonomy P5/P7 emit; writer-deny compat; rehost predicate bounded; no fake IDs; `manage_product_terms` fact; no variation mutation; no generic options translator; email observer allowlist only; TARGET 7; no TSC.4/5/6 leakage.

---

## 29–30. Schema / ADR / STATE A re-proof

| Proof | Result |
|---|---|
| `attribute_id` logical identity | Yes |
| Technical host change safe | Yes via bounded rehost |
| Second Store / new source_type | No |
| Catalog fan-out migration | No |
| One writable global label authority | Yes |
| Local names product-owned | Yes |
| `pa_*` values TSC.1 | Yes |
| No variation machine mutation | Yes |
| Email stale bounded options | Yes (PARTIAL) |
| No generic options translator | Yes |
| TARGET 7 | Yes |

**ADR:** None required. Optional note in evidence that IntegrationSegmentAuthority is ADR-0017 ownership facts applied at segment granularity — not a new identity family.

---

## 31. WC matrix (WC1–WC40)

| ID | Requirement | Disposition |
|---|---|---|
| WC1 | Baseline inventory post-TSC.2 | Supported |
| WC2 | Canonical global label identity `attribute_id` | Supported |
| WC3 | `pa_*` values remain TSC.1 | Supported |
| WC4 | No SOURCE_TERM for attribute definitions | Supported |
| WC5 | Stop emitting taxonomy P5/P7 | Supported |
| WC6 | No permanent dual-write | Supported |
| WC7 | Legacy taxonomy P5/P7 compatibility read-only | Supported |
| WC8 | Compat not OTL/Jobs/TI.7 writable authority | Supported |
| WC9 | Frontend canonical → compat → source | Supported |
| WC10 | Local P5/P7 authoritative unchanged | Supported |
| WC11 | Local values Deferred | Deferred |
| WC12 | Attribute CRUD → shop dirty only | Supported |
| WC13 | Shop page ID change → bounded rehost | Supported |
| WC14 | Rehost preserves lifecycle axes | Supported |
| WC15 | Temporary old-host read on race | Supported |
| WC16 | IntegrationSegmentAuthority facts | Supported |
| WC17 | Edit requires `manage_product_terms` | Supported |
| WC18 | Shop draft ≠ attribute not-public | Supported |
| WC19 | Shop edit_post alone insufficient | Supported |
| WC20 | Exact global/local overlay algorithm | Supported |
| WC21 | Overlay context guards | Supported |
| WC22 | Variation matching identity unchanged | Supported |
| WC23 | Email stale observer allowlist | Partial |
| WC24 | Invoice paid / refunded variant keys | Partial / uncovered |
| WC25 | Default template semantics unchanged | Supported |
| WC26 | No gettext engine | Unsupported/out |
| WC27 | No gateway/shipping settings translator | Deferred |
| WC28 | OTL single-writer rules | Supported |
| WC29 | Jobs single-writer rules | Supported |
| WC30 | TI.7 policy owner unchanged | Supported |
| WC31 | Overlay-only persistence | Supported |
| WC32 | Provider deny economic/PII/order | Supported |
| WC33 | Performance no catalog scan | Supported |
| WC34 | PluginGuard locks | Supported |
| WC35 | STATE A / TARGET 7 | Supported |
| WC36 | No new ADR | Supported |
| WC37 | Site-neutrality | Supported |
| WC38 | No TSC.4/5/6 leakage | Supported |
| WC39 | S10/S11 stale | Deferred honesty |
| WC40 | Meaningful merchant value | Supported |

**Count: 40**

---

## 32. Work package ladder (amended)

| WP | Focus |
|---|---|
| **TSC3.0** | Freeze matrices, identities, authority/compat contract, email PARTIAL table, seam/context tables |
| **TSC3.1** | Canonical extract on shop; stop taxonomy P5/P7 emit; retain/compat rules |
| **TSC3.2** | Overlay algorithm + context guards + variation safety tests |
| **TSC3.3** | Attribute CRUD invalidation; shop-page rehost API + race/failure tests |
| **TSC3.4** | IntegrationSegmentAuthority wiring into OTL/Jobs/Publication facts |
| **TSC3.5** | Email allowlisted option dirty → checkout sync (PARTIAL evidence) |
| **TSC3.6** | Writer-deny for compat P5/P7; PluginGuard; provider/privacy |
| **TSC3.7** | OTL/Jobs/TI.7 regressions; docs/evidence/closure |

---

## 33. Acceptance criteria (AC1–AC38)

1. Baseline SHA/version/TARGET match.
2. Canonical key uses `attribute_id` via PluginIdentity.
3. Taxonomy attributes not emitted as new product P5/P7.
4. Existing taxonomy P5/P7 not writable via OTL mutate.
5. Existing taxonomy P5/P7 not Jobs-authoritative / not publish-as-authority.
6. Frontend prefers canonical over compat over source.
7. Local attribute P5/P7 still extract and overlay.
8. Rename attribute label dirties shop only (no product loop).
9. Delete attribute retires canonical segment on sync.
10. Change `woocommerce_shop_page_id` rehosts attribute-label rows old→new.
11. Rehost preserves translation lifecycle axes (evidence).
12. Mid-rehost read does not permanently lose translation (race test).
13. Failed rehost does not invent IDs or scan catalog.
14. Operator without `manage_product_terms` cannot mutate attribute-label rows.
15. Operator with only `edit_post(shop)` cannot mutate attribute-label rows.
16. Shop page set to draft does not mark attribute-label rows source-not-public.
17. Attribute missing ⇒ exists fact false / extract absent.
18. Overlay algorithm matches Amendment 4 for global+product.
19. Overlay algorithm for global+no product.
20. Overlay algorithm for local+product.
21. Overlay algorithm for local+no product leaves source.
22. Admin/REST contexts do not overlay labels.
23. Variable product add-to-cart identity unchanged under translated labels.
24. Email settings option change dirties checkout for allowlisted IDs.
25. Evidence records email stale as PARTIAL with invoice paid + refunded variant gaps.
26. `read_email_chrome` effective-value semantics unchanged (no gettext expansion).
27. No order/customer data in provider payloads.
28. No economic meta registration.
29. No generic options translator.
30. TARGET remains 7; no migration.
31. No Gutenberg/Elementor/public API leakage.
32. Site-neutral production code.
33. TSC.0–2 / A.7 regressions green.
34. Compat retain prevents taxonomy P5 orphan wipe on product sync.
35. Publication policy codepaths remain in TI.7.
36. edit_link for attribute-label prefers Woo attribute admin when available.
37. Performance: rehost and rename paths issue no product catalog query.
38. Evidence pack maps WC/AC results.

**Count: 38**

---

## 34–35. Test strategy + browser

**Unit:** identity; emit/suppress taxonomy P5; overlay algorithm matrix; authority facts; rehost predicate; email option allowlist.

**Integration:** attribute CRUD stale; shop ID reassignment rehost + race/failure; OTL writer-deny compat; publication publicness with draft shop; Jobs item deny; email option dirty PARTIAL; variation add-to-cart.

**Security / architecture / performance:** as WC32–35.

**Browser (bounded):** product attribute label; variation label + purchase; optional layered nav global label.

---

## 36. Production value

- One authoritative translation per global attribute label, correct after rename and shop page change.
- Safer OTL/Jobs auth for Woo attribute definitions.
- Partial but real email settings stale for standard subject/heading options.

---

## 37. Risks / debt

- Compatibility taxonomy P5/P7 retained until natural retirement — list noise mitigated by read-only marking.
- No auto-adoption compat→canonical (operator cost).
- Other shop-hosted chrome (B1/B2) still lack rehost.
- Checkout email host reassignment still historical debt.
- Email PARTIAL leaves invoice paid / refunded variants for a later milestone.
- IntegrationSegmentAuthority wiring touches OTL/Jobs/Publication fact call sites — must stay facts-only.

---

## 38. Deferred / Unsupported

Local values; CE body/footer/CE7/CE8; paid/full/partial email keys; gateways/shipping; S10/S11 stale; gettext; public API; Gutenberg; Elementor.

---

## 39. STOP audit

| Trigger | Outcome |
|---|---|
| Need non-BIGINT source for labels | Avoided via shop host + rehost |
| Dual writable authorities | Forbidden by Amendment 1 |
| Catalog migration | Forbidden |
| Schema / TARGET bump | Not required |
| Variation machine translation | Forbidden |
| Claim email stale Supported | Rejected — frozen PARTIAL |

No REDESIGN REQUIRED for Supported core scope.

---

## 40. External self-review (falsification)

1. Single authority — **pass** (stop emit + writer-deny + canonical key)
2. P5/P7 compat — **pass** (read-only; retain; frontend fallback)
3. Shop host stability — **pass** (bounded rehost + race read)
4. Semantic leakage — **pass** (IntegrationSegmentAuthority)
5. Woo capability — **pass** (`manage_product_terms`)
6. Global/local overlay — **pass** (exact algorithm)
7. Variation safety — **pass**
8. Email stale completeness — **honest PARTIAL**
9. Default templates — **pass** (unchanged A.7d)
10–12. OTL/Jobs/TI.7 — **pass** with fact overrides
13. Provider privacy — **pass**
14. Performance — **pass**
15. STATE A — **pass**

---

## 41. Final external re-review packet

1. **Global attribute authority:** canonical shop-hosted `p:woocommerce:attribute:{attribute_id}:label` only writable authority.
2. **Compatibility P5/P7:** taxonomy product rows read-only fallback; not emitted going forward; not writable.
3. **Shop-host reassignment:** bounded Store rehost of attribute-label rows; temporary old-host read; STATE A.
4. **Technical-host semantic authority:** IntegrationSegmentAuthority facts; shop is host only.
5. **Woo capability/auth:** `manage_product_terms` for attribute-definition translation mutation.
6. **Overlay algorithm:** Amendment 4 exact steps.
7. **Context table:** visitor yes; admin/REST/cron/feed/embed no; local needs product context.
8. **Variation safety:** labels only; matching identity untouched.
9. **Email option audit:** table in §11–14.
10. **Email stale disposition:** **PARTIAL**.
11. **Default-template verdict:** stale only; A.7d effective-string unchanged; not gettext.
12. **OTL:** single-writer + authority facts; compat read-only.
13. **Jobs:** deny compat authority; require `manage_product_terms` for attribute-label items.
14. **TI.7:** policy owner unchanged; consumes corrected publicness/edit facts.
15. **Schema/TARGET:** STATE A / 7.
16. **ADR:** none.
17. **WC count:** **40**
18. **AC count:** **38**
19. **WP ladder:** TSC3.0–TSC3.7
20. **Risks/debt:** §37
21. **STOP audit:** §39 — clear
22. **Final verdict:**

# TSC.3 Architecture Frozen

Implementation authorized from this plan only. Do **not** start TSC.4+. Do **not** bump version/TARGET, create a migration, create an ADR, tag, release, or deploy as part of TSC.3 planning freeze.
