# A.7d — Customer-language provenance (hard gate)

**Verdict (planning discovery):** Blocked — no existing deterministic source.
**Verdict (architecture gate):** **Pass** — satisfied by Accepted [ADR-0018](../../adr/0018-woocommerce-order-transactional-language-context.md) (`_aiml_transactional_language` on the Woo order).

Wrong-language leakage is a hard failure. Planning **must not** freeze: “use whatever locale is active when the email sends.”

## Executive finding (planning-time)

At A.7d planning time there was **no existing deterministic persisted customer-language source** on orders or customers that covered the admitted email send paths.

| Source examined | Result |
|---|---|
| Order meta containing `lang` / `locale` / `aiml` / `wpml` / `polylang` on recent HPOS orders | **Empty** on live samples |
| AIML code persisting order/customer language at checkout | **None found** |
| `LanguageResolver` | URL prefix only — **no cookie**, **no Accept-Language** ([`LanguageResolver.php`](../../../src/Language/LanguageResolver.php)) |
| `LanguageContext::switch_to()` / `with()` | Exists for transactional email use-cases in comments — **not wired** to order language ([`LanguageContext.php`](../../../src/Language/LanguageContext.php)) |
| WP user `locale` meta | Admin UI locale — **not** shopping language; often empty for customers |
| Woo billing country | Geography ≠ language |
| Current request locale at send time | Available only for some sync paths; **untrustworthy** for admin/cron/resend |

Therefore meaningful A.7d for CE1–CE6 required a **new AIML-owned persistent language-context contract**. That contract is now frozen in **ADR-0018** (order meta snapshot). **Do not implement the snapshot in planning docs alone** — implementation belongs to A.7d coding.

CE7/CE8 remain **Deferred** (ADR-0018 does not cover non-order emails). They **do not block** CE1–CE6 / CE9–CE10 order-backed admissions.

---

## Path matrix

Legend: **Y** = available and usable; **N** = not available; **?** = sometimes / untrustworthy; **—** = N/A.

### 1. Synchronous checkout email (e.g. CE1 right after place-order)

| Question | Finding |
|---|---|
| Canonical object | Order (just created) |
| Order exists | Y |
| Current request language | **?** — only if checkout URL carried AIML prefix (`/sv/...`); default-language checkout has no prefix |
| Request locale trustworthy as customer intent | **?** — yes only while still in that storefront request; not persisted |
| Woo/customer locale | N / weak |
| AIML language/context | Request `LanguageContext` only |
| Persisted language markers | **N** |
| Survives later sends | **N** |
| Safe fallback today | Source language only — **cannot claim customer language** |

### 2. Async / deferred email (queue / Action Scheduler / delayed notification)

| Question | Finding |
|---|---|
| Canonical object | Order |
| Current request language | **N** — worker/cron context |
| Request locale trustworthy | **N** — often default or site locale |
| Persisted markers | **N** |
| Survives | **N** |
| Safe fallback | Source only |

### 3. Retry (failed send retry)

| Question | Finding |
|---|---|
| Same as async | **N** deterministic customer language |
| Safe fallback | Source only |

### 4. Status-change email (admin or system changes order status → CE2/CE3/CE6/CE9/CE10)

| Question | Finding |
|---|---|
| Canonical object | Order |
| Request language | **?** — admin locale or CLI/cron — **not** customer shopping language |
| Trustworthy | **N** |
| Persisted markers | **N** |
| Safe fallback | Source only |

### 5. Admin resend (Woo order actions → resend invoice / processing / …)

| Question | Finding |
|---|---|
| Canonical object | Order |
| Request language | Admin request — **untrustworthy** |
| Persisted markers | **N** |
| Safe fallback | Source only |

### 6. Customer-note email (CE5)

| Question | Finding |
|---|---|
| Canonical object | Order + note content |
| Note body | **Runtime / PII — never translate as unit** |
| Chrome language | Same gap as status-change / admin |
| Persisted markers | **N** |

### 7. Invoice resend (CE4)

| Question | Finding |
|---|---|
| Same as admin resend | **N** without order language snapshot |

### 8. New-account email (CE7)

| Question | Finding |
|---|---|
| Canonical object | User (may lack order) |
| Request language at registration | **?** if registered via prefixed storefront |
| Persisted on user | **N** (AIML) |
| Survives later related mail | **N** |
| Independent deferral | **Allowed** if weaker than order path after ADR |

### 9. Reset-password email (CE8)

| Question | Finding |
|---|---|
| Canonical object | User |
| Request language | Login/lost-password form — often unprefixed / default |
| Reset key | **PII/secret — never Store/diagnostics** |
| Persisted markers | **N** |
| Independent deferral | **Allowed** |

---

## Persistence assessment (planning only — no implementation)

### Does an existing deterministic source cover admitted paths?

**At planning time: No.**
**After ADR-0018: Yes (contractual)** — implementation must materialize `_aiml_transactional_language` per the ADR.

### Frozen snapshot contract (ADR-0018)

| Topic | Decision |
|---|---|
| Owner of metadata | AIML operational meta on Woo order |
| Key | `_aiml_transactional_language` |
| Capture time | Visitor order creation when `LanguageContext` is deterministic |
| HPOS | Woo CRUD order meta (HPOS-compatible) |
| Retries / resends | Resolve snapshot; `LanguageContext::with()` |
| Immutability | Default immutable after capture |
| Privacy | Language code only |
| Schema | No TARGET bump |

### Forbidden freeze

❌ Active locale at `wp_mail` / `WC_Email::send` time as customer language.

---

## Gate outcome

| Outcome | Condition |
|---|---|
| **Architecture Frozen** | **← current** — ADR-0018 Accepted; order-backed CE subject/heading admitted |
| Blocked pending language-context architecture decision | Planning-time state (historical) |

**A.7d production implementation is authorized** on `feature/a7d-woocommerce-customer-emails` per the frozen plan + ADR-0018. Snapshot + email overlays are implementation work — not started in the ADR/docs gate.
