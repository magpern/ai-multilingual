# ADR-0018 — WooCommerce order transactional language context

## Status

**Accepted** (2026-08-08) — AIML-owned WooCommerce order transactional-language snapshot contract frozen to unblock A.7d.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-08  
**Decision:** ADR-0018 **Accepted**  
**Reason:** Independent consistency review against A.7d language-provenance evidence, ADR-0017 foreign-persistence ownership, `LanguageContext` switch/restore semantics, WooCommerce HPOS order-meta APIs, ADR-0004 retention, and Migrator TARGET **6** found no architectural contradiction. Sync checkout language is not sufficient for async/status-change/retry/resend paths; active request/admin locale at send time is forbidden as customer language. An AIML-owned, immutable order snapshot via Woo CRUD meta satisfies the A.7d hard provenance gate without a schema bump, custom transaction table, or mutation of Woo translation source content. CE7/CE8 remain outside this contract.

**Scope:** Deterministic capture, persistence, resolution, and bounded locale switching for **WooCommerce order-backed transactional language context**, primarily for WooCommerce customer emails (A.7d). Does **not** redefine visitor routing, Store identity, session cookies, user profiles, or a generic multi-producer TransactionContext API.

**Residual risks accepted:**

- Capture must occur before the first email that needs the snapshot; late/mis-ordered hooks are an implementation stop condition
- Historical orders created before snapshot support fall back to source/default language (no heuristic backfill)
- Order duplication/copy semantics must be proven in implementation tests; uncertain copy must not invent language for an unrelated order
- Uninstall with `remove_data_on_uninstall` on does not automatically scrub Woo order meta unless a future explicit cleanup policy is added; default retention (ADR-0004) leaves operational meta in place with Woo orders
- Non-order emails (CE7/CE8) remain Deferred under a separate evidence bar

**Implementation gate:** **Open for A.7d implementation** once the A.7d plan is updated to **Architecture Frozen** on `main`. This ADR does **not** itself ship email overlays, Store units, or production hooks — those belong to A.7d coding on `feature/a7d-woocommerce-customer-emails`.

**Evidence / plan base:**

- [A7D_WOOCOMMERCE_CUSTOMER_EMAILS_IMPLEMENTATION_PLAN.md](../plans/A7D_WOOCOMMERCE_CUSTOMER_EMAILS_IMPLEMENTATION_PLAN.md)
- [a7d-evidence/language-provenance.md](../plans/a7d-evidence/language-provenance.md)
- [a7d-evidence/admission-matrix.md](../plans/a7d-evidence/admission-matrix.md)
- [LanguageContext.php](../../src/Language/LanguageContext.php) (`switch_to` / `with` / `restore`)
- [LanguageResolver.php](../../src/Language/LanguageResolver.php) (URL-prefix authority; no cookie)

**Related:** ADR-0001 (overlay-not-duplication); ADR-0004 (lifecycle/retention); ADR-0008 (LanguageResolver vs LanguageContext); ADR-0017 (foreign persistence ownership; AIML may attach its own operational state through supported extension mechanisms).

**Revalidation triggers:** Proposal to use active locale at send time; proposal to infer language from country/currency/admin locale; proposal to mass-backfill historical orders heuristically; proposal to solve CE7/CE8 via this order meta; proposal to introduce a custom AIML transaction table for this snapshot alone; proposal to intercept `wp_mail` globally; evidence that Woo order meta cannot be read reliably under HPOS for admitted send paths.

---

## Context

AI Multilingual resolves visitor language from the URL prefix into request-scoped `LanguageContext` (ADR-0008). That is sufficient for storefront render, but **transactional emails** for a WooCommerce order often execute later:

- synchronous checkout notification
- asynchronous / Action Scheduler / deferred send
- cron retry
- order status transition
- admin action / manual resend
- invoice resend
- customer-note email associated with the order

A.7d planning proved ([language-provenance.md](../plans/a7d-evidence/language-provenance.md)):

- no AIML order/customer language marker exists today;
- recent HPOS orders carry no language-like meta;
- `LanguageResolver` has no cookie / Accept-Language fallback;
- active WordPress, admin, or request locale at send time is **not** the customer’s transactional language.

Wrong-language customer email is a hard failure. Source-language fallback is safer than guessing.

This ADR answers one narrow question:

> How does AIML deterministically remember and later recover the customer’s language for transactional outputs whose execution may occur outside the original visitor request?

Primary immediate consumer: **WooCommerce customer emails (A.7d)**.

---

## Decision

### 1. Snapshot ownership

For **order-backed** transactional workflows, AIML captures a **transactional language snapshot** at the checkout / order-creation boundary and persists it as **AIML-owned metadata** on the canonical WooCommerce order.

| Owns | Does not own |
|---|---|
| Semantic meaning of the language marker | The WooCommerce order record |
| Capture / immutability / resolution rules | Billing, shipping, payment, line items |
| Bounded `LanguageContext` switch during email generation | Email templates / Woo email settings as translation storage |

The snapshot represents:

> the customer’s selected transactional language at order creation / checkout

It is **not**:

- translated Woo content;
- an order business field;
- a replacement for WooCommerce’s locale APIs;
- mutable because a later admin or worker request uses a different locale.

This is consistent with **ADR-0017**: AIML must not annex foreign persistence, but **may** attach its own operational state through the foreign system’s supported extension mechanism (here: WooCommerce order metadata CRUD). AIML does **not** rewrite order content, product data, payment data, or email source templates to establish identity or language.

### 2. Storage mechanism

| Requirement | Decision |
|---|---|
| API | WooCommerce order meta via official CRUD (`WC_Order::update_meta_data` / `get_meta` / `save`, or equivalent supported helpers) |
| HPOS | Required — no frozen dependency on classic `wp_postmeta` storage layout |
| Schema | **No** Migrator TARGET bump; **no** custom AIML table for this snapshot |
| Writes | **No** direct SQL |
| Value | Bounded scalar: canonical AIML language **code** string only |
| PII | **None** |

#### Frozen metadata key

```
_aiml_transactional_language
```

Leading underscore follows Woo/AIML private-meta convention. Value = canonical AIML language code (e.g. `en`, `sv`) after validation against configured/supported languages.

Do not freeze alternate keys. Do not store locale objects, JSON blobs, or request payloads.

### 3. Capture rule

**When:** Once, when a WooCommerce order is created from a visitor checkout (or equivalent visitor order-creation path) and AIML has a **deterministic** current language in `LanguageContext` (including the site default language when that is the resolved request language).

**How:**

1. Read current AIML language from `LanguageContext`.
2. Normalize to the canonical AIML language code.
3. Validate against configured/supported languages.
4. If valid and meta is absent → write `_aiml_transactional_language`.
5. If language cannot be resolved deterministically → **do not write**.
6. Write **before** transactional email paths that need the snapshot (implementation must choose a Woo hook ordering that satisfies this; failure is a stop condition).

**Idempotency:** If the meta already holds a valid code, **do not overwrite** because the current request locale differs (checkout retries, duplicate triggers, later requests).

**Backfill:** An explicitly missing snapshot **must not** be backfilled from admin locale, current request locale on resend, user `locale` meta, country, or currency. Optional future backfill is allowed only with a separate evidence-backed migration that proves deterministic language — out of scope for this ADR’s default behavior.

### 4. Immutability / update policy

**Once captured for an order, the transactional language is immutable by default.**

Reasons: retries/resends must stay consistent; later admin activity must not change customer language; auditability.

Manual correction, if ever needed, is a separately governed operator action — **not** implicit recalculation. **Do not** build that UI under this ADR or under A.7d’s first implementation wave unless separately authorized.

### 5. Resolution ladder (order-backed emails)

Deterministic resolution for order-backed transactional email language:

1. **Valid** `_aiml_transactional_language` on the order (supported AIML language code).
2. *(Reserved)* Another explicitly approved deterministic source — **none approved in this ADR**.
3. AIML **source / default** language.

**Forbidden** fallback sources:

- current admin locale
- current request language on resend / status change
- current WordPress locale
- WP user `locale` (unless a future ADR proves it represents transactional shopping language — **not** proven)
- country / currency inference
- browser / session state that no longer belongs to the order

**Never guess.** Missing / invalid / unsupported / corrupt snapshot → step 3 + bounded diagnostics. Do not fatal. Do not send chrome in an arbitrary current locale.

### 6. Email execution / locale switch

Reuse existing `LanguageContext` semantics:

1. Resolve language via the ladder above.
2. Enter that language **only** for the bounded email-generation scope (`LanguageContext::with()` preferred; `switch_to` / `restore` only with guaranteed `finally`).
3. Restore prior language on all exits, including exceptions.
4. Nested / reentrant sends must not corrupt the stack.
5. One email must not leak language into the next.
6. No global permanent locale mutation.

### 7. Async / retry / resend contract (hard)

The persisted snapshot exists so these paths are deterministic:

- asynchronous email
- status-change email
- cron / retry
- admin resend
- invoice resend
- customer-note email **associated with the order**

Each **must** resolve from the order snapshot (or source fallback), **not** from current execution context. This is a hard acceptance requirement for A.7d.

### 8. Non-order emails (CE7 / CE8)

This ADR **does not** solve New Account or Reset Password.

- There is no order owner for the snapshot.
- CE7/CE8 remain **independently Deferred** unless a deterministic existing user/account language source is proven.
- Future user-level language persistence requires separate evidence/decision.

**This must not block** order-backed CE1–CE6 (and other order-backed admissions) when those are otherwise sound.

### 9. Privacy / security

Snapshot contains **only** a validated language identifier.

Must **not** contain: customer name, email, address, IP, geolocation, payment data, order contents, raw locale/request payload.

Classification: **low-sensitivity operational metadata**.

Retention: follow ADR-0004 (deactivation inert; uninstall default retains AIML data; canonical Woo content never destroyed by AIML). Snapshot lifetime follows the canonical order; do not orphan language markers onto unrelated orders.

### 10. Order lifecycle

| Event | Behavior |
|---|---|
| Order created (visitor checkout) | Capture once if deterministic language available |
| Checkout retry / duplicate trigger | Idempotent — no overwrite of valid snapshot |
| Status changed / refund / cancellation | Snapshot unchanged |
| Resend / note / invoice | Resolve from snapshot; switch/restore around send |
| Order duplication | Must not copy language to an unrelated order without explicit proof; if Woo copies meta, implementation tests must assert correct ownership semantics |
| Order deletion | Meta removed with order by Woo lifecycle |
| HPOS migration | Meta readable/writable via Woo CRUD on both storage backends |
| Plugin disable / reactivate | Snapshot remains on order (inert deactivation); overlay inactive while plugin off |

### 11. Historical orders

**Conservative freeze:** Do **not** mass-infer language for historical orders.

Orders created before snapshot support → **source/default language fallback** unless a future migration proves language deterministically. **No heuristic backfill.**

### 12. Diagnostics (bounded)

Allowed status/counter names (examples):

- `snapshot_captured`
- `snapshot_present`
- `snapshot_missing`
- `snapshot_invalid`
- `transactional_language_resolved`
- `source_language_fallback`
- `context_restored`

No order/customer content. Avoid persistent high-cardinality order IDs in metrics.

### 13. Generalization boundary

The pattern **may** later support other transactional producers, but this Accepted contract initially governs:

> **WooCommerce order-backed transactional language context**

A broader generic TransactionContext API is future work unless already required by existing architecture (it is not).

### 14. Test contract (required of future implementation)

**Capture:** EN checkout; SV checkout; idempotent capture; invalid language rejection; no accidental overwrite.

**Resolution:** sync; async; status-change; retry; admin resend; invoice resend; customer-note email.

**Isolation:** EN → SV → EN → SV; locale restored after every send; exception restores locale; no admin/frontend leakage.

**Storage:** HPOS; Woo CRUD; no direct SQL; no TARGET/schema bump.

**Security:** no PII; no raw body diagnostics.

---

## Alternatives considered (rejected)

| ID | Alternative | Rejection |
|---|---|---|
| A | Active locale at send time | Nondeterministic for delayed/admin/worker sends |
| B | Browser/session language only | Unavailable for async/resend |
| C | Infer from country/currency | Not equivalent to selected language |
| D | Administrator/user locale on resend | Sender context ≠ customer preference |
| E | Duplicate language into Store segment identity | Transaction context ≠ translation identity |
| F | New custom AIML transaction table | Excessive if Woo-supported meta safely carries the scalar |
| G | Generic `wp_mail` language interception | Wrong ownership; producer ambiguity |

---

## Consequences

### Benefits

- Deterministic customer transactional language for order-backed emails
- Async / status-change / retry / resend safety
- No request-context dependency at send time
- No schema TARGET bump
- HPOS-compatible extension via Woo CRUD
- Clear A.7d provenance gate pass for order-backed admissions
- CE7/CE8 can defer without blocking CE1–CE6

### Costs / obligations

- One AIML metadata value per admitted order
- Lifecycle and HPOS compatibility responsibility on the Woo integration
- Capture-hook reliability becomes critical
- Historical orders without snapshot fall back to source/default language

---

## Consistency review (acceptance gate)

| Check | Result |
|---|---|
| Matches A.7d provenance blocker | **Pass** — supplies the missing deterministic persisted source |
| ADR-0017 foreign persistence | **Pass** — AIML operational meta via Woo extension API; no annexation of order business fields |
| LanguageContext switch/restore | **Pass** — reuses existing stack semantics |
| HPOS / Woo order APIs | **Pass** — CRUD meta; no `wp_postmeta` layout freeze |
| Privacy / uninstall (ADR-0004) | **Pass** — language code only; default retention coherent |
| TARGET = 6 | **Pass** — no schema bump |
| Non-order CE7/CE8 | **Pass** — explicitly out of scope; Deferred independently |

**No contradiction remains.** Status set to **Accepted**.
