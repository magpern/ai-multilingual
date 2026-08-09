# A.SEOb Evidence — Relationship Service Analysis (SB11)

**Status:** Planning freeze evidence  
**Candidate:** SB11 — Canonical reusable language-relationship contract consumed unchanged by A.SEOc–A.SEOf  
**Baseline:** `main` @ `a1e91f442`

---

## 1. Question

Does the architecture already provide a reusable language-relationship abstraction, or can one be frozen without Store/schema/TARGET/Integration-API/identity/router redesign — such that A.SEOc–A.SEOf consume it **unchanged**?

## 2. What exists today

| Component | Relationship graph? | Consumer-ready? |
|---|---|---|
| Router | URL prefix only | No |
| LanguageContext | Current language | Partial |
| Languages::routable | Language set | Partial |
| Store | Content overlays | **Not** URL graph under SA7 |
| Switcher::links | Builds alternate URLs + hreflang codes | **UI-coupled**; not a stable SEO contract |
| PreviewService | Preview URLs | Not public graph |
| Integration API v1 | Plugin content units | Not SEO relationship API |

**Verdict on “already sufficient”:** Primitives exist, but **no** stable, documented, non-UI contract for downstream SEO waves. Leaving A.SEOc–A.SEOf to copy Switcher logic would recreate architectural drift.

## 3. Lightweight contract (without forbidden changes)

A.SEOb may freeze a **read-only language-relationship contract** implemented as a small AIML service/class (name left to implementation) that:

**Inputs (examples):** current request context and/or unprefixed site-relative path and/or queried object id+type — using WP query / permalink APIs already owned by WordPress.

**Outputs:** ordered list of relationship records:

- `language_code`
- `hreflang` (BCP47)
- `url` (absolute, SA7 rules)
- `is_default`
- `is_current`
- optional: `status` = published only for public consumers

**Rules:**

- Public SEO consumers call with preview **excluded** (`routable(false)` / equivalent).
- URLs built exactly like SA7 / Switcher path rules — no reverse slug lookup.
- No persistence; no new tables; TARGET remains 6.
- No Integration API v1 change; no new identity family; no second router.
- Depends only on: A.SEOa URL identity, Router/SA7, LanguageContext, Languages/LanguageResolver, Store **only if** needed for non-URL concerns (URL graph itself does not require Store reads under SA7).

## 4. Downstream dependency rule (frozen)

The contract is **self-contained**. It may depend on A.SEOa, Router, LanguageContext, Store, Integration API v1.

It must **not** depend on A.SEOc, A.SEOd, A.SEOe, or A.SEOf.

Those waves are **consumers only**. Circular SEO-wave dependencies are forbidden. The planner must not defer this contract to “decide in A.SEOc.”

## 5. Consumer proof (raises SB11 bar)

| Consumer | Uses contract for | Can consume unchanged? |
|---|---|---|
| A.SEOb itself | hreflang + canonical current URL | Yes — primary emitter |
| A.SEOc | Absolute URL / locale assumptions when cooperating with Rank Math | Yes — read URLs/locales; does not redefine graph |
| A.SEOd | Correct `og:url` / locale alignment | Yes — read current + alternates as needed |
| A.SEOe | Sitemap alternates / indexable URL set | Yes — enumerate published relationships |
| A.SEOf | Reciprocity / orphan / duplicate diagnostics | Yes — validate against same contract |

No consumer requires Store redesign or TARGET bump to **read** the contract.

## 6. Outcomes considered

| Outcome | Applies? |
|---|---|
| Existing Router/Store/LanguageContext alone are already a frozen reusable contract | **No** — Switcher is not a contract |
| Lightweight reusable service/contract without Store/API/identity/TARGET change | **Yes** |
| Requires focused ADR / Deferred | **No** for the read-only SA7-based published graph |

## 7. Disposition recommendation

**SB11 → Supported** as an architecture contract: freeze the interface + invariants in the wave plan; implement the lightweight service in A.SEOb coding; extract/share logic currently embedded in Switcher without changing Switcher UX ownership.

**Not Supported / remains Forbidden:** persistent relationship table, reverse translated-slug map, second routing engine, HTML scrape of switcher, depending on Rank Math for the graph.
