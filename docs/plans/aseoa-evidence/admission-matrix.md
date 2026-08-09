# A.SEOa Evidence — Admission Matrix

**Status:** Evidence-based dispositions frozen for planning
**Started as:** Candidate (all)
**Inputs:** [ownership-inventory.md](ownership-inventory.md), [routing-inventory.md](routing-inventory.md), [rewrite-analysis.md](rewrite-analysis.md), [redirect-analysis.md](redirect-analysis.md), [collision-analysis.md](collision-analysis.md), [url-migration-analysis.md](url-migration-analysis.md), [slug-identity.md](slug-identity.md)

Dispositions: **Supported** | **Deferred** | **Unsupported**

---

## Matrix

| ID | Candidate | Disposition | Evidence rationale |
|---|---|---|---|
| **SA1** | Translated post slugs | **Deferred** | Store can shape `post_name` + `FORMAT_SLUG` under existing identity, but inbound `/lang/{translated}/` resolution and cross-object uniqueness need reverse lookup / index or registry. TARGET 6 has neither; table scans rejected; mutating `post_name` forbidden (ADR-0001). Requires focused ADR before Support. |
| **SA2** | Translated page slugs | **Deferred** | Same mechanism as SA1 (`page` is a post type). No separate page identity. Blocked on same reverse-resolution ADR as SA1. |
| **SA3** | Translated product slugs | **Deferred** | Product CPT uses WP `post_name` inside Woo permalink structure; rewrite **bases** remain Deferred (ADR-0002). Leaf slug translation blocked on same inbound/uniqueness ADR as SA1. |
| **SA4** | Translated taxonomy slugs | **Deferred** | No `SOURCE_TERM`; term slug persistence is WP/Woo; hosting on shop post would be wrong ownership / shared-definition risk. Needs term identity + reverse resolution ADR; rewrite bases stay Deferred. |
| **SA5** | Slug uniqueness | **Deferred** | WP/Woo uniqueness applies to **source** slugs only today. AIML translated uniqueness cannot be guaranteed without indexed constraints (schema/TARGET or registry) — both stop without ADR. Depends on SA1+. |
| **SA6** | Historical redirects | **Deferred** | WP `_wp_old_slug` does not track Store overlays. A.SEOa forbids new URL-history DB / redirect registry without ADR. Cannot Support. |
| **SA7** | Language-aware permalink generation | **Supported** | Already implemented via Router `home_url` prefixing + LanguageContext; uses source paths. Freeze: continue building on Router/WP/`get_permalink` — no second URL generator. Does not claim translated leaf slugs. |
| **SA8** | Reserved words | **Deferred** | WP reserved-slug behavior exists for source paths. AIML reserved-word engine for translated slugs is unnecessary/unsafe until SA1 ADR exists; do not invent a parallel reserved list now. |
| **SA9** | Collision handling | **Deferred** | Documented WP/Woo source collision behavior is the baseline. AIML collision engine for translated slugs blocked with SA1/SA5. No competing uniqueness algorithm. |
| **SA10** | Preview URLs | **Supported** | `PreviewService` + ADR-0008 preview routing already provide translator-routable prefixed URLs on source permalinks. Freeze preview vs published gates; no public SEO discovery claims (A.SEOb/e). |

---

## Explicitly not Supported in this freeze

- Translated rewrite bases (ADR-0002)
- Persistent URL-history / redirect registry
- Second routing engine
- Path/URL identity families
- Attachment slug translation (not a candidate; ownership inventory only — remains untouched)

## ADR gates (do not freeze surfaces that need them)

| Gate | Unblocks |
|---|---|
| Focused ADR: reverse slug resolution + uniqueness under overlay model (and TARGET justification if index required) | SA1, SA2, SA3, SA5, SA8, SA9 |
| Focused ADR: term slug identity / `SOURCE_TERM` or equivalent without ownership theft | SA4 |
| Focused ADR: URL-history / redirect registry **or** proven reuse of an existing owner | SA6 |
| Focused ADR reopening ADR-0002 | Translated rewrite bases (still out of A.SEOa Supported set until then) |

## Architecture freeze eligibility

**Supported set {SA7, SA10}** is implementable inside existing contracts (largely already shipped).
**Deferred set** is frozen as Deferred — not silently Supported.

→ Parent A.SEOa wave may mark **Architecture Frozen (planning)** for this admission outcome.
