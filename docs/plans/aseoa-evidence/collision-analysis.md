# A.SEOa Evidence — Collision Analysis

**Status:** Investigation complete (planning)
**Rule:** Document **current** WordPress / WooCommerce behavior. Do **not** invent a competing uniqueness engine.

---

## 1. Case matrix (current WP/Woo behavior)

| Case | Current owner / behavior | AIML today | Fit under TARGET 6 + no registry? |
|---|---|---|---|
| Duplicate translated post slugs | N/A — translated slugs not stored | None | **No deterministic inbound uniqueness** without reverse index/registry |
| Duplicate translated page slugs | Same as posts | None | Same |
| Duplicate translated product slugs | Product is CPT; WP uniqueness on **source** `post_name` | None | Same |
| Duplicate translated taxonomy slugs | WP `wp_unique_term_slug` on **source** term slug | No term Store host | Term translation blocked also by missing `SOURCE_TERM` |
| Post ↔ taxonomy slug collisions | WP allows same string in different namespaces (post vs term) depending on rewrite structure; Woo product base separates many cases | None | Source-path collisions follow WP/Woo; translated leaf collisions undefined without AIML engine |
| Translated slug vs source-language slug collisions | N/A until overlays exist | None | Risk: `/sv/foo` where `foo` is another post’s EN slug — WP would resolve EN object after strip |
| Historical redirect collisions | WP `_wp_old_slug` for source renames only | None | See [redirect-analysis.md](redirect-analysis.md) |
| Language-prefix interactions | Prefix strip then WP parse; default language never prefixed | Router | Documented; must not treat language codes as slugs incorrectly (ADR-0002 already handles home_url timing) |
| Slug regeneration after source title changes | WP may change `post_name` on publish depending on settings/UI; uniqueness via `wp_unique_post_slug` | Title overlay does **not** change `post_name` | Source slug lifecycle remains WP-owned |
| Permalink cache invalidation | WP object cache / AIML language render cache on translation save | `Store::invalidate` + rollout language invalidation — **no slug→id index** | No slug index to invalidate |
| Canonical redirect behavior | Core `redirect_canonical`; AIML suppresses when prefixed | Suppress only | Correct language-aware canonical = A.SEOb |
| WooCommerce permalink interactions | Woo rewrite + product base + `%postname%` | Prefix only | Bases Deferred (ADR-0002) |
| WordPress unique-slug generation | `wp_unique_post_slug` / `wp_unique_term_slug` | Not wrapped | Source only |
| Rollback after removing approved translated slug | N/A — no Store slug rows | Store delete/retain policies for other fields | Without history registry, rollback = remove overlay → URLs revert to source slug; old translated URL 404s |

## 2. Architecture limitation (critical)

Store read APIs are keyed by `(source_type, source_id, language_id, segment_key)` only. There is:

- **no** `WHERE translated_text = ?` reverse lookup
- **no** unique index on translated slug values
- **no** slug reservation table (classic ROADMAP M2 `slugs` schema was never migrated; TARGET **6**)

Therefore deterministic **inbound** resolution and cross-object uniqueness for translated slugs **cannot** be Supported without one of:

1. Schema/index / TARGET bump (forbidden without ADR), or
2. Persistent redirect/uniqueness registry (forbidden without ADR), or
3. Mutating `post_name` per language (forbidden — ADR-0001 overlay model; breaks single canonical object).

Full-table scans of `translated_text` are **not** accepted as a production uniqueness/routing architecture (races, performance, no uniqueness guarantee).

## 3. Policy freeze when/if SA1 later admitted

Do **not** invent AIML uniqueness algorithms. Any future ADR must specify how uniqueness reuses or cooperates with `wp_unique_post_slug` / Woo structures without ownership theft.

## 4. Implication for admissions

Collision-dependent candidates that need AIML-enforced translated-slug uniqueness or inbound collision safety → **Deferred** pending ADR. Source-path WP/Woo collision behavior remains the documented baseline.
