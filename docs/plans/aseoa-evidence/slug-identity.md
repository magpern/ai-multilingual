# A.SEOa Evidence — Slug Identity

**Status:** Investigation complete (planning)
**Code:** [`src/Translation/Store.php`](../../../src/Translation/Store.php), [`src/Translation/Extractor.php`](../../../src/Translation/Extractor.php)

---

## 1. Existing identity model

| Item | Fact |
|---|---|
| Store host | `source_type = post` (`SOURCE_POST`) only |
| Classic post fields | `field_key` = `segment_key` ∈ {`post_title`, `post_excerpt`, `post_content`} |
| Hash | `segment_hash = sha1(field_key + "\\x1f" + segment_key)` — freshness via `source_hash` (ADR-0007) |
| Formats | Includes `FORMAT_SLUG` normalization (`strtolower(trim)`) — **unused for post_name** |
| Forbidden | New identity family; URL/path/rewrite identity; routing serializer; fuzzy URL matching |

## 2. Hypothetical slug unit (not admitted end-to-end)

A future ADR-backed design *could* store:

| Component | Value |
|---|---|
| `source_type` | `post` |
| `source_id` | post ID |
| `field_key` / `segment_key` | `post_name` |
| `text_format` | `slug` |

That reuses existing identity machinery **for storage/Workspace** without a new family.

It does **not** solve inbound URL→object resolution or uniqueness indexing.

## 3. Taxonomy

No `SOURCE_TERM`. Term labels in Woo waves use `p:` hosted on shop post — inappropriate for term **slug** identity (would invent shared-definition / wrong owner). Term slug translation requires a term identity contract → **Deferred / ADR**.

## 4. Decision

| Question | Answer |
|---|---|
| New identity family needed for storage shape? | **No** for post/page/product `post_name` units |
| Existing Store sufficient for **end-to-end** translated URLs? | **No** — missing reverse lookup + uniqueness constraints |
| Path / URL identity allowed? | **No** |
| Schema / TARGET bump for reverse index? | **Forbidden** without ADR |

Identity conclusion feeds admissions: storage shape is not the blocker; **resolution and history contracts** are.
