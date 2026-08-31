# Site Translate — Full Path — Definitive Implementation Plan

**Status:** **FROZEN** — authoritative Site Translate specification  
**Frozen:** 2026-08-31  
**Freeze baseline / reconciled main:** `d6501a12d58eeb95783423d9773b2ebeac2771ee`  
**Version during milestone:** **1.10.0** (compatible drift from plan baseline 1.9.0)  
**TARGET:** **8**  
**Migration:** **NONE**  
**Release:** **`MILESTONE CLOSURE != RELEASE CLOSURE`** — no automatic release after this milestone  

**Review verdict applied:** PASS — READY FOR PLANNING FREEZE → IMPLEMENTATION

---

## 1. Goal

Deliver a **Site Translate** operator workflow inside Translator Workspace for **Pages, Posts, and Products**: coverage-aware multi-select → chunked translation Jobs with shared batch identity → **Run batch now** (async enqueue) → manual segment publication → Localized URL batch generate/publish with honest eligibility outcomes including **`title_stale`**.

## 2. Scope

| In scope | Out of scope |
|---|---|
| Pages, Posts, Products | PR preview environments |
| Coverage contract per object × language | Slug auto-increment (`slug-2`, `slug-3`, …) |
| Selection-scoped Strategy F hard gate | Site-wide Strategy F hard gate |
| Chunked Jobs (`JobBounds::MAX_POSTS_PER_BULK` = 50) | Mega-job > 50 |
| Run batch now (thin orchestration) | Synchronous batch execution in one HTTP request |
| Partial create + retry failed chunks only | New durable idempotency schema/API |
| LU batch via existing routing authorities | Direct route-table writes from Site Translate |
| Manual publication gate sequencing | Required review-before-publish |
| Rank Math literals in Jobs; runtime SEO overlays unchanged | Rank Math Model A sitemap redesign |
| User manual update to current version | Rewriting historical v1.6.0 acceptance |

## 3. Frozen sequencing (language / gate / LU)

Deterministic operator order — **no Preview language as anonymous-public acceptance evidence**:

1. Configure target language (e.g. `sv`) as **Preview** while preparing.
2. Configure AI / Strategy F as required.
3. Segment publication gate **ON**; auto-publication mode **MANUAL**.
4. Create/run initial Site Translate Jobs; AI output lands **UNPUBLISHED**.
5. Promote target language to **Published** **before** anonymous/public acceptance.
6. Keep **Localized URLs OFF**.
7. Verify anonymous gate holdback: `/sv/<source-slug>/` renders source until segment Publish.
8. Operator manually **Publishes** translations.
9. Enable **Localized URLs** → wait **On**.
10. Generate/publish localized routes for eligible selection.
11. SEO / switcher / EffectiveUrl acceptance.

Candidate generation may occur before language publication only when existing slug authority allows it. **Public route publication must not precede language Published.**

## 4. Strategy F gate — selection-scoped

Classify each selected object via `Extractor::body_status` (`BODY_OK`, `BODY_BLOCKS`, `BODY_ELEMENTOR`).

- **Strategy-F-dependent** iff body surface is **blocks**.
- **Site Translate Create** and run admission for Jobs originating from Site Translate:
  - **Hard block** if ANY selected object is Strategy-F-dependent **and** Strategy F is not fully valid.
  - **Allow** if every selected object is classic-only (`BODY_OK`), even when Strategy F is Off.
- **Fully valid** reuses existing Strategy F capability/diagnostic authority: registration → UUID → extraction → frontend render (`FeatureFlags::PRODUCTION_FLAGS` effective + no prohibited combination).

Elementor bodies: **not** a Site Translate hard block in v1; coverage reports honestly (`body_elementor`).

## 5. Coverage contract

Per source object × target language:

| Field | Meaning |
|---|---|
| `eligible_total` | Current extraction output ∩ AI provider-admitted units (exclude `FORMAT_SLUG`, `provider_allowed=false`, empty source) |
| `missing` | Eligible, no usable/non-empty translation |
| `translated` | Eligible, non-empty translation (any publish state) |
| `unpublished` | Translated ∩ `publish_status=unpublished` |
| `published` | Translated ∩ `publish_status=published` |
| `stale` | Translated ∩ existing AIML stale authority (`is_stale`) |
| `blocked_or_unsupported` | Structured reason codes |

Reason classes: `body_blocks_without_strategy_f`, `body_elementor`, `zero_eligible` (repository-consistent naming).

**Picker completeness for publish intent:** `missing == 0` AND `stale == 0`. Unpublished may be nonzero.

**`eligible_total=0`:** display **no extractable work** with reason — never "100% translated."

Published stale counts in **both** `published` and `stale`.

Reuse `SegmentAssembler`, `RegisteredMetaRegistry::provider_allowed_for_segment`, and Jobs admission semantics — no parallel extraction model.

## 6. Site Translate picker

Workspace operator surface for Pages / Posts / Products. Filtering, multi-selection, coverage/status columns. Feeds existing Jobs — **no second translation application**.

## 7. Chunked job creation

- Chunk at `JobBounds::MAX_POSTS_PER_BULK` (50); do not raise bound.
- All jobs from one Site Translate operation share one `batch_id`.
- After create: switch/focus Workspace **Jobs**, highlight **entire batch group**.
- Reuse `bulk_translate` via `BackgroundTranslationBatchCoordinator`.

### Partial create / idempotency

If chunk N fails: preserve successful jobs + batch identity; expose incomplete batch; retry **failed creation only** without duplicating successful post IDs/jobs. Reuse `client_token` / `JobIdempotencyKey`.

**STOP** if safe partial-create retry requires new DB schema or new durable public idempotency API.

## 8. Run batch now

One admin action enqueues all **waiting** (`queued`) Jobs in the batch via existing per-job run authority (`BackgroundTranslationScheduler::enqueue_job`). **Must not** synchronously execute an arbitrarily large batch in one HTTP request. **Must not** become a second Job execution engine.

## 9. Localized URL batch

Authorities only: `SlugCandidateService`, `RoutePublicationService`, `ObjectLanguagePublicEligibility`, EffectiveUrl lifecycle. **No direct route/candidate table writes from Site Translate.**

Per-object outcomes: `eligible_success`, `not_admitted`, `missing_translated_title`, `title_not_published`, **`title_stale`**, `manual_slug_locked`, `collision`, `publication_ineligible`, `language_not_published`, `other_error`.

### Automatic generate contract

Requires non-empty translated title with **`publish_status=published` AND `stale=false`** (stale from Store `is_stale` — do not independently compute hashes).

Collisions: edit / clear / retry per item; one collision must not abort the batch. No automatic slug suffix increment.

## 10. Review vs publication (v1)

Gate **ON**, auto-pub **MANUAL**, AI → **UNPUBLISHED**, operator → **Publish**. Review/approve **optional**; Approved ≠ Published.

## 11. SEO

No redesign. Rank Math title/description/social literals in extraction where supported. Canonical/hreflang/og:url/locale remain runtime overlays. Localized URLs own localized paths. Model A unchanged.

## 12. Cache contract

Preserve anonymous URL/host-authoritative language resolution. No language cookies, Accept-Language selection, or same-URL visitor-specific anonymous output.

## 13. Test requirements

Ship in same PR:

- **Coverage:** denominator, missing/translated/unpublished/published/stale overlap, zero eligible, blocked codes, slug/provider exclusions
- **Strategy F:** Gutenberg blocked / classic allowed / mixed blocked / Elementor not hard-block
- **Jobs:** chunking, shared batch_id, Jobs focus, partial create, idempotency, Run batch now enqueue-only
- **Publication:** gate holdback, manual publish
- **LU:** title states, manual lock, collision, batch continues
- **SEO/cache:** no regression
- **Jest:** picker, batch UX, LU outcomes

## 14. Documentation

Update `docs/user-manual/index.html` to current product version + Site Translate operator workflow. Preserve `docs/acceptance/v1.6.0-vlad-acceptance.html` as historical.

## 15. DEV acceptance

Bounded dogfood on `https://dev.biopentra.eu` only. **Production forbidden.**

## 16. Generic plugin invariant

No Biopentra branding, site-specific IDs, or merchant-specific workflow rules in runtime code/tests.

## 17. Verdict

**FROZEN.** Implementation proceeds against this document.
