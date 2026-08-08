# A.7d — Customer-language provenance (hard gate)

**Verdict:** **Blocked pending language-context architecture decision**

Wrong-language leakage is a hard failure. Planning **must not** freeze: “use whatever locale is active when the email sends.”

## Executive finding

There is **no existing deterministic persisted customer-language source** on orders or customers that covers the admitted email send paths.

| Source examined | Result |
|---|---|
| Order meta containing `lang` / `locale` / `aiml` / `wpml` / `polylang` on recent HPOS orders | **Empty** on live samples |
| AIML code persisting order/customer language at checkout | **None found** |
| `LanguageResolver` | URL prefix only — **no cookie**, **no Accept-Language** ([`LanguageResolver.php`](../../../src/Language/LanguageResolver.php)) |
| `LanguageContext::switch_to()` / `with()` | Exists for transactional email use-cases in comments — **not wired** to order language ([`LanguageContext.php`](../../../src/Language/LanguageContext.php)) |
| WP user `locale` meta | Admin UI locale — **not** shopping language; often empty for customers |
| Woo billing country | Geography ≠ language |
| Current request locale at send time | Available only for some sync paths; **untrustworthy** for admin/cron/resend |

Therefore meaningful A.7d for CE1–CE6 requires a **new AIML-owned persistent language-context contract** (order and/or customer snapshot). That is a new architectural contract → **focused language-context ADR required**. **Do not author the ADR in A.7d planning.** **Do not implement the snapshot in A.7d planning.**

CE7/CE8 may defer independently if their non-order provenance remains weaker after the ADR; **do not block CE1–CE6 solely because CE7/CE8 fail**, once order language is solved — but **today both are blocked** by the missing contract.

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

**No.**

### If A.7d required a new AIML-owned snapshot

Document for the future ADR (not decided here):

| Topic | Planning note |
|---|---|
| Owner of metadata | AIML (integration meta), **not** rewriting Woo business fields |
| Capture time | Likely checkout / account registration when `LanguageContext` is trustworthy |
| HPOS implications | Must use Woo order meta APIs compatible with HPOS (live: HPOS **yes**) |
| Retries / resends | Read snapshot at send; wrap render in `LanguageContext::with()` |
| Cleanup / lifecycle | Align with order/user retention; no orphan PII |
| Privacy | Language code only — **no** names, emails, addresses in the marker |
| New architectural contract? | **Yes** |
| Focused ADR required? | **Yes** — do not invent the contract inside A.7d coding |

### Forbidden freeze

❌ Active locale at `wp_mail` / `WC_Email::send` time as customer language.

---

## Gate outcome

| Outcome | Condition |
|---|---|
| Architecture Frozen | Existing deterministic provenance sufficient for admitted CE set |
| **Blocked pending language-context architecture decision** | **← current** — new persistence contract required |

**A.7d production implementation must not start** until the language-context ADR is Accepted (or an alternative existing source is proven). After that ADR, return to A.7d admission freeze (subjects/headings first) without reopening excluded owners.
