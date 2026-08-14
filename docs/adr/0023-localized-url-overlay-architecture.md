# ADR-0023 — Localized URL overlay architecture (MSEO)

## Status

**Accepted** (2026-08-14) — Localized URL overlay architecture for the MSEO program.

**Decision maker:** Product Owner (external architecture review freeze)  
**Approval date:** 2026-08-14  
**Scope:** Optional administrator-controlled localized URL slugs; STATE B; Migrator TARGET 7→8; dual GenerationEnablement / PathRecognition model; full normalized unprefixed path authority; candidate vs active route lifecycle; source-identity route history; canonical collision safety; ObjectLanguagePublicEligibility; RoutingCapabilityRegistry; activation state machine; EffectiveUrlService authority; sitemap Model A; preview source-slug only; ADR-0002 compatibility preserved.

**Does not authorize:** MSEO.1+ production routing/SEO URL behavior until the relevant milestone implementation plan is frozen and explicitly opened. This ADR does **not** bump runtime `Migrator::TARGET` by itself.

**Residual risks accepted:**

- TARGET 8 introduces three new tables and one additive Store column; runtime TARGET remains 7 until MSEO.0 implementation merges
- Path recognition on feature OFF uses 302 fallbacks — bookmarks to old localized URLs remain usable but are not permanently redirected on disable
- Capability gating (Model B) means global ON still serves source-slug URLs for unsupported object/path configurations until later milestones
- Hierarchy descendant path updates require bounded background reindex (MSEO.3)
- History path reservation blocks reuse by other sources until an explicit future release tool (deferred)

**Evidence / plan base:**

- [MSEO_PARENT_IMPLEMENTATION_PLAN.md](../plans/MSEO_PARENT_IMPLEMENTATION_PLAN.md)
- [MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md](../plans/MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md)
- [ASEOA_SLUGS_PERMALINK_TRANSLATION_IMPLEMENTATION_PLAN.md](../plans/ASEOA_SLUGS_PERMALINK_TRANSLATION_IMPLEMENTATION_PLAN.md) (SA1–SA6 Deferred evidence)
- [ADR-0001](0001-translation-overlay-not-duplication.md), [ADR-0002](0002-prefix-strip-routing.md), [ADR-0003](0003-custom-tables-explicit-migrations.md), [ADR-0020](0020-controlled-auto-publication-and-frontend-gate.md)

**Related:** ADR-0005 segment storage; ADR-0007 hash semantics (Store SHA-1 unchanged); ADR-0008 language states; ADR-0021 SOURCE_TERM; SB11 LanguageRelationshipService; Rank Math integration (A.SEOc/e).

**Revalidation triggers:** Proposal to mutate `post_name` / term `slug`; proposal to register AIML rewrite rules; proposal to store redirect destination paths instead of source-identity history; proposal to gate path recognition on `localized_urls_state`; proposal to enable localized URLs by default on upgrade; proposal to emit competing sitemap `<loc>` entries per language; proposal to call translation providers for slug generation.

---

## Context

After v1.4.0, AIML serves language-prefixed URLs using **source slugs** only (A.SEOa SA7). Translated leaf slugs (SA1–SA6) were Deferred pending reverse resolution, collision safety, and redirect history contracts.

Product authorization: administrators may opt in to **localized URL slugs** that are auto-generated (from translated title/name), manually editable, stable once public, and reflected across AIML-owned SEO surfaces without manual repair.

Constraints from repository evidence:

- ADR-0001 forbids mutating WordPress canonical slug columns per language
- ADR-0002 prefix-strip routing must remain — no per-language rewrite duplication
- Store has no reverse slug lookup; SHA-1 segment hashes are unrelated to URL path identity
- SB11 builds URLs from a shared unprefixed source path today
- TI.7 publication axis exists; slug lifecycle must not fork into a second policy engine

---

## Decision

### 1. Source-slug preservation

WordPress owns `post_name` and term `slug`. AIML stores localized slug **candidates** in the segment Store and **active routes** in dedicated tables. AIML never writes canonical slug columns.

### 2. ADR-0002 compatibility

Inbound: strip language prefix on `plugins_loaded:999`, then optionally map localized unprefixed path → canonical source path before `WP::parse_request()`.

Outbound: apply localized path substitution **before** language prefix via `EffectiveUrlService`; attach `home_url` filter on `parse_request:0` as today.

No `add_rewrite_rule`, no `flush_rewrite_rules`.

### 3. PathCanonicalizer

One internal normalizer for route creation, lookup, history, collision checks, redirects, outbound substitution, and tests. WordPress-compatible slug generation uses `sanitize_title()`. Fail closed on malformed encoding, `%2F` in slug segments, backslashes, and overlong paths (>2048 chars).

### 4. Full normalized unprefixed path authority

`aiml_slug_routes` indexes:

- **Inbound:** `(language_id, localized_path_hash)` → source identity + `source_path`
- **Outbound:** `(language_id, source_path_hash)` → `localized_path`

Full path strings stored in `VARCHAR(2048)`. Hash index hit requires full-string equality verification.

### 5. SHA-256 path identity

New MSEO tables use **`BINARY(32)` SHA-256** of UTF-8 normalized path. Existing Store `CHAR(40)` SHA-1 segment hashes are unchanged.

### 6. GenerationEnablement vs PathRecognition

| Mode | Controls |
|---|---|
| **GenerationEnablement** | Whether new effective public URLs use localized paths (`localized_urls_state=on` + RoutingCapability + active route) |
| **PathRecognition** | Whether inbound resolver recognizes AIML-owned paths — **always on** for routable frontend translated-language requests |

Setting OFF does **not** disable recognition.

### 7. Current-route uniqueness

Exactly one row per `(source_type, source_id, language_id)` in `aiml_slug_routes` (`UNIQUE object_language`). Route activation UPSERTs that row.

### 8. Candidate vs active route lifecycle

| State | Storage | Public URL |
|---|---|---|
| Editorial candidate | Store segment (`translated_text`, `slug_origin`, TI.7 axes) | None until route published |
| Active route | `aiml_slug_routes` (`route_status`) | When active + eligible + capable + ON |

Candidate material edit invokes normal `publish_clear_fields()` on the slug segment; active route unchanged until explicit atomic route publication.

### 9. slug_origin persistence

Additive column `aiml_translations.slug_origin` (`generated` | `manual` | ``). Meaningful when `text_format=slug`. Copied to route row on activation. Generate action cannot overwrite `manual`.

### 10. Route publication (atomic)

1. Validate candidate, eligibility, capability, collisions (§13–14)  
2. UPSERT route row; set `route_status=active`  
3. Insert superseded localized path into history (if any)  
4. Update candidate publication state  
5. Invalidate URL/SEO caches  

Failure rolls back; prior active route remains.

### 11. Current/history reservation domain

`(language_id, normalized localized_path)` reserved across `aiml_slug_routes` and `aiml_route_history`. Publication and history insertion cross-check both. Max **5** history rows per source/language; purge on source delete.

### 12. Canonical WordPress/Woo collision authority

Before route activation, `CanonicalPathCollisionChecker` verifies localized path does not conflict with:

1. Another AIML route or history row (different source)
2. Another object's routable canonical path via bounded `url_to_postid()` / admitted term path resolution

Manual collision → HTTP 409. Auto-generation → deterministic suffix retry (bounded).

### 13. Redirect permanence policy

| Case | Status |
|---|---|
| Deliberate published slug replacement (history, ON) | **301** → current EffectiveUrl (one hop) |
| Source-slug request when active localized route (ON) | **301** post-resolution @ `wp:5` |
| Feature OFF; inactive route; unpublish; language disable transition | **302** or gate-resolved fallback — **no new permanent 301** |
| Source object deleted | **410** or **404** after cleanup — no automatic parent 301 |

History stores **no destination path** — redirect target is always computed from source identity via `EffectiveUrlService`.

### 14. ObjectLanguagePublicEligibility

Single compositional authority for route activation and SEO discoverability. Combines:

- ADR-0008 language `published` (preview excluded from public surfaces)
- Source object public (WP API)
- Object/language overlay bundle: when publication gate ON, ≥1 published non-empty admitted segment; when OFF, ≥1 `Store::is_publicly_overlay_eligible` segment — **any admitted field, not title-only**
- Active route + generation state + RoutingCapability for discoverability

Manual slug route publication does not require title translation when object/language bundle is otherwise valid.

### 15. Routability vs discoverability

| Concern | Rule |
|---|---|
| **Routability** | Known paths recognized per §6; stale published routes remain routable (URL stable) |
| **Discoverability** | Hreflang/sitemap/switcher include localized URL only when `ObjectLanguagePublicEligibility::is_discoverable()` |

Do not emit hreflang to URLs that render wholly source-language content with zero overlay-eligible segments.

### 16. EffectiveUrlService authority

Single read/write URL authority consumed by Router outbound filter, SB11, canonical/hreflang, sitemap overlay, switcher, and redirect resolution.

Outbound sequence: **source path → canonicalize → localize → prefix → query**.

When generation disabled or object unsupported, returns source-slug path (existing SA7 behavior).

### 17. Sitemap Model A

Rank Math emits one `<url>` per object; default-language `<loc>` unchanged. AIML enriches with `<xhtml:link rel="alternate">` alternates using SB11 effective URLs. No per-language duplicate URL entries; no `loc` replacement for non-default languages.

### 18. Preview policy

[`PreviewService`](../src/Workspace/PreviewService.php) continues **source-slug** prefixed preview URLs (ADR-0008 capability gate). Localized public paths begin only after route activation.

### 19. RoutingCapabilityRegistry (Model B)

Global ON does not localize every object. Capabilities ship per milestone:

| Capability | Milestone |
|---|---|
| `post_flat`, `page_top_level`, `product_plain_permalink` | MSEO.2 |
| `page_hierarchical`, `term_archive` | MSEO.3 |
| `product_category_permalink` | MSEO.4 |

Unsupported → source-slug effective URLs even when global ON.

### 20. Activation state machine

Persisted in `aiml_settings`:

- `localized_urls_state`: `off` | `activating` | `on` | `failed`
- `localized_urls_activation_checkpoint` (JSON cursor)
- `localized_urls_activation_error` (string)

Administrator enable request sets `activating` (O(1)); `SlugRouteActivationJob` processes eligible objects in bounded batches; completion sets `on`. UI never labels **On** during `activating`.

### 21. Bounded hierarchy traversal

Parent path changes enqueue incremental frontier reindex (`aiml_slug_reindex_frontier`). Worker processes ≤100 objects per tick; discovers direct children in paginated queries only. No full-tree materialization in one request.

### 22. Woo exclusions

Product/page **leaf slug** localization in scope. **Rewrite bases** (`product`, `product-category`, shop base) and **machine endpoints** (cart, checkout, my-account) remain untranslated.

### 23. TARGET 8 migration

Forward-only idempotent step 8 per ADR-0003:

- Create `aiml_slug_routes`, `aiml_route_history`, `aiml_slug_reindex_frontier`
- Add `aiml_translations.slug_origin`

No down migration. Uninstall retention per ADR-0004 default.

### 24. Provider and Extension API

Slug generation derives from existing translated title/name in Store — **no provider API call**.

Extension API v1 unchanged. Optional private filter `aiml_effective_url` documented in HOOKS.md (not Extension API).

---

## Schema (TARGET 8)

```sql
-- aiml_slug_routes
route_id, language_id, source_type, source_id, source_subtype,
source_path VARCHAR(2048), source_path_hash BINARY(32),
localized_path VARCHAR(2048), localized_path_hash BINARY(32),
localized_slug VARCHAR(191), route_namespace VARCHAR(64),
slug_origin VARCHAR(16), route_status VARCHAR(16),
activated_at, created_at, updated_at
UNIQUE object_language (source_type, source_id, language_id)
UNIQUE localized_identity (language_id, localized_path_hash)
UNIQUE source_identity (language_id, source_path_hash)

-- aiml_route_history
history_id, language_id, historical_path VARCHAR(2048),
historical_path_hash BINARY(32), source_type, source_id, source_subtype, created_at
UNIQUE history_identity (language_id, historical_path_hash)

-- aiml_slug_reindex_frontier
frontier_id, parent_source_type, parent_source_id,
frontier_json LONGTEXT, status VARCHAR(16), updated_at

-- aiml_translations (additive)
slug_origin VARCHAR(16) NOT NULL DEFAULT ''
```

Table names use `$wpdb->prefix` via `Schema::*()` helpers.

---

## Router integration seam (frozen)

| Phase | Hook | Priority | Action |
|---|---|---|---|
| Prefix strip + inbound | `plugins_loaded` | 999 | `Router::resolve()` — history redirect; localized→source substitution |
| WP resolution | core | — | `WP::parse_request()` |
| Outbound prefix attach | `parse_request` | 0 | `enable_url_prefixing()` |
| Source-slug canonical | `wp` | 5 | Compare request to EffectiveUrl; 301 if active localized route |

Lookup precedence after prefix strip: **current route → history → WP parse**.

Query strings preserved through REQUEST_URI rebuild.

---

## Consequences

- MSEO can ship localized URLs without duplicating rewrite rules or mutating WP slugs
- SB11 must upgrade to per-language effective paths (MSEO.2)
- PluginGuard gains MSEO invariants (no rewrite rules, no post_name mutation, no provider slug calls)
- Classic ROADMAP M2 `slugs` table design superseded by TARGET 8 tables above
- Installations default OFF; v1.4.0 URL behavior unchanged until administrator activates after MSEO.2

---

## Implementation gate

**Open for MSEO.0** when [MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md](../plans/MSEO0_LOCALIZED_URL_FOUNDATION_IMPLEMENTATION_PLAN.md) is Architecture Frozen on `main`.

MSEO.0 delivers inert TARGET 8 + PathCanonicalizer + EffectiveUrlService scaffold only.

Localized URL enable UI and public behavior change are gated to **MSEO.2** acceptance.
