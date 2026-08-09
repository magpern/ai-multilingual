# A.SEOa Evidence — URL Migration Analysis

**Status:** Investigation complete (planning)
**Rule:** Document current WP/Woo (+ AIML) lifecycle behavior. Do **not** invent URL history mechanisms.

For each case: owner · persistence · redirect · rollback · diagnostics · failure modes.

---

## 1. First publication of a translated slug

| Field | Current evidence |
|---|---|
| Owner | Would be AIML Store overlay (not implemented); WP still owns `post_name` |
| Persistence | No slug segments written today |
| Redirect | N/A |
| Rollback | N/A |
| Diagnostics | None |
| Failure | Inbound translated path 404 / wrong object if naively generated without reverse map |

**Limit:** Publishing a Store slug that appears in outbound URLs without inbound resolution creates broken links. Cannot Support end-to-end without reverse resolution contract (ADR).

## 2. Changing an approved translated slug

| Field | Current evidence |
|---|---|
| Owner | WP `_wp_old_slug` only tracks **source** `post_name` changes |
| Persistence | Store upsert would replace value (if existed); no history row |
| Redirect | None for old translated value |
| Rollback | No prior-value registry |
| Diagnostics | None |
| Failure | Inbound old translated URL orphaned |

**Limit:** Requires URL-history / redirect registry → **Deferred** (stop condition).

## 3. Restoring an older translated slug

| Field | Current evidence |
|---|---|
| Owner | — |
| Persistence | No AIML slug history |
| Redirect / rollback | Unavailable |
| Failure | Cannot restore deterministically |

**Disposition impact:** Blocks SA6; constrains SA1 migration story → Deferred.

## 4. Deleting a translated slug

| Field | Current evidence |
|---|---|
| Owner | Store row delete/status (pattern from other segments) |
| Persistence | Segment removed; `post_name` unchanged |
| Redirect | None to “previous translated” |
| Rollback | Re-translate from source |
| Failure | Outbound reverts to source slug; old translated URL 404 |

## 5. Language removal

| Field | Current evidence |
|---|---|
| Owner | ADR-0008 language status → `disabled` |
| Persistence | Translations retained when disabled |
| Redirect | Language prefix not routable |
| Failure | Prefixed URLs for disabled language should not resolve as public |

Compatible with existing LanguageResolver — **no new subsystem**.

## 6. Post trash / restore

| Field | Current evidence |
|---|---|
| Owner | WordPress post status |
| Persistence | Store rows may remain (existing overlay lifecycle patterns) |
| Redirect | WP trash behavior for source permalinks |
| Failure | Must not invent AIML redirects around trash |

## 7. Taxonomy lifecycle (merge / split)

| Field | Current evidence |
|---|---|
| Owner | WordPress / WooCommerce terms |
| Persistence | No AIML term slug Store host (`SOURCE_TERM` absent) |
| Failure | Term slug translation not implementable as post-hosted units without shared-definition / term identity ADR |

## 8. Source-title changes → slug regeneration

| Field | Current evidence |
|---|---|
| Owner | WordPress (UI/settings dependent) |
| Persistence | May change `post_name`; `_wp_old_slug` may record prior **source** slug |
| AIML | Title overlay does not drive `post_name` |
| Failure | Translated Store slug (if any) would not auto-sync — must not fuzzy-rematch |

## 9. Permalink cache invalidation

| Field | Current evidence |
|---|---|
| Owner | WP caches + AIML language render cache on translation save |
| Persistence | No slug→object cache exists |
| Failure | N/A for slug index |

## 10. Historical redirect accumulation

| Field | Current evidence |
|---|---|
| Owner | WP `_wp_old_slug` for source only |
| AIML | **Absent** — creating accumulation store = new URL-history subsystem → **stop / ADR** |

## 11. Canonical URL updates

| Field | Current evidence |
|---|---|
| Owner | Core / SEO plugins; AIML suppresses `redirect_canonical` when prefixed |
| A.SEOa boundary | Do not emit document canonicals (A.SEOb) |
| Failure | Blind suppress remains until A.SEOb |

## 12. Sitemap impact

| Field | Current evidence |
|---|---|
| Owner | Rank Math / WP sitemaps (A.SEOe) |
| A.SEOa | No sitemap work; note that Deferred translated slugs mean sitemaps continue source paths + prefixes until SA1+ admitted |

## 13. Inbound links to previous translated URLs

| Field | Current evidence |
|---|---|
| Owner | — |
| Behavior today | 404 / wrong match |
| Required for Support | Deterministic redirect or resolution — **not available** without history registry or reverse map ADR |

---

## Migration verdict

Deterministic translated-slug migration (rename / restore / historical inbound) **cannot** be implemented using only existing Router + Store APIs + TARGET 6 without a forbidden URL-history subsystem or a new reverse-resolution contract (ADR).

Candidates that depend on that migration story are **Deferred**.
