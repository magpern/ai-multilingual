# Hooks

Every hook this plugin registers, why, and how it gates itself. Filters attach
unconditionally and short-circuit at call time, so the registered set does not
vary by request type.

## Routing — `src/Routing/Router.php`

| Hook | Priority | Purpose |
|---|---|---|
| `plugins_loaded` | 999 | Resolve the language, strip the prefix from `REQUEST_URI`, attach the filters below. Late enough that every plugin has loaded; early enough that `locale` is in place before `load_default_textdomain()` and before `WP::parse_request()` reads the URI. |
| `locale` | 10 | Serve the active language's locale. |
| `language_attributes` | 10 | Emit `lang` and `dir`. |
| `redirect_canonical` | 10 | Language-preserving policy for prefixed requests (A.SEOb): never strip the active language prefix; allow same-language corrections. |
| `parse_request` | 0 | Attach the `home_url` filter — **after** routing. See below. |
| `home_url` | 10 | Prefix generated URLs. Skips `/wp-admin`, `/wp-login.php` and the REST namespace. |

Routing is skipped entirely for admin, REST, AJAX, cron, WP-CLI, XML-RPC and the
login screen.

**Why `home_url` attaches late.** `WP::parse_request()` calls `home_url()` and
strips that path from the request URI using an unanchored `|^path|i` pattern. If
the filter were live at that moment, a Swedish request for a page whose slug
merely starts with the language code would be truncated — `/svenska-sidan/`
becomes `enska-sidan/` and 404s. `parse_request` fires at the very end of
`WP::parse_request()`, after the home path has been read.

## Overlays — `src/Translation/Renderer.php`

| Hook | Priority | Notes |
|---|---|---|
| `the_title` | 10 (2 args) | Overlays `post_title` for posts/pages/products and custom `nav_menu_item` titles (A.6 N1). Object-title menu entries call this filter with the linked object ID instead. |
| `the_content` | **1** | Must run before core's `apply_block_hooks_to_content_from_post_object` (8) and `do_blocks` (9). Only substitutes when the incoming string is byte-identical to the queried post's raw `post_content`, because plugins apply this filter to arbitrary strings. |
| `get_the_excerpt` | 10 (2 args) | Manual excerpts only; a generated excerpt derives from the already-translated body. |
| `document_title_parts` | 20 | Singular views only. |

All four are inert unless a non-default language is resolved, and always inert
in the admin. A re-entrancy flag prevents a filter from re-entering itself.

## Lifecycle and content — `src/Plugin.php`

| Hook | Priority | Purpose |
|---|---|---|
| `save_post` | 20 (2 args) | Stale detection. Re-extracts the source, recomputes hashes per format and flags changed segments. Never touches translated text or status. Skips revisions and autosaves. |
| `admin_init` | 10 | Schema drift check. Bind-mount deployments update files in place and never fire the activation hook, so this is the only thing that migrates them. |
| `before_woocommerce_init` | 10 | Declare HPOS compatibility when WooCommerce is present. |

## Front end — `src/Frontend/Switcher.php`

| Hook | Purpose |
|---|---|
| `aiml_switcher` (shortcode) | Renders the switcher. |
| `wp_nav_menu_items` | Appends the switcher to a menu, opt-in only. |

URLs come from `LanguageRelationshipService` (SB11).

## SEO head — `src/Seo/DocumentSeoHead.php`

| Hook | Priority | Purpose |
|---|---|---|
| `get_canonical_url` | 30 | Language-aware WP canonical (SB1/SB2); keeps cross-host overrides. |
| `rank_math/frontend/canonical` | 20 | Language-aware Rank Math canonical cooperation. |
| `wp_head` | 2 | Emit reciprocal `hreflang` + `x-default` from SB11 (SB3/SB4); preview excluded. |

## Admin — `src/Admin/`

| Hook | Capability |
|---|---|
| `admin_menu`, `admin_init` | — |
| `admin_post_aiml_save_language` | `manage_options` + nonce |
| `admin_post_aiml_delete_language` | `manage_options` + nonce |
| `admin_post_aiml_save_translation` | `aiml_translate` + nonce |

No `admin_post_nopriv_*` counterpart exists for any handler, so none is
reachable while logged out.

## Filters this plugin provides

| Filter | Default | Purpose |
|---|---|---|
| `aiml_switcher_in_menu` | `false` | Whether to append the language switcher to a given nav menu. Receives the `wp_nav_menu()` args. |

## Translator workspace REST — `src/Rest/WorkspaceController.php`

| Hook | Purpose |
|---|---|
| `rest_api_init` | Registers `aiml/v1/workspace/*` routes for the translator workspace (F10 + F11 additive). |

All routes require `aiml_translate` unless noted. Post-scoped routes additionally require
`edit_post` for the requested canonical post. OTL operations list/detail accept
`aiml_translate` **or** `aiml_review_translations`. Responses include
`X-AIML-Workspace-Api-Version: 1` and serialize ViewModels only — controllers
delegate to `WorkspaceService` and never touch `Store` directly.

| Method | Route |
|---|---|
| GET | `/aiml/v1/workspace/posts` |
| GET | `/aiml/v1/workspace/{post_id}/segments?language=` |
| GET | `/aiml/v1/workspace/{post_id}/status?language=` |
| GET | `/aiml/v1/workspace/{post_id}/preview-url?language=` |
| POST | `/aiml/v1/workspace/{post_id}/segments/{segment_key}?language=` |
| POST | `/aiml/v1/workspace/{post_id}/segments/batch?language=` |
| POST | `/aiml/v1/workspace/{post_id}/translate?language=` |
| POST | `/aiml/v1/workspace/{post_id}/segments/{segment_key}/suggest?language=` | F11 — AI suggest (no Store persist) |
| POST | `/aiml/v1/workspace/{post_id}/suggestions/accept?language=` | F11 — batch accept exact TM |
| POST | `/aiml/v1/workspace/{post_id}/qa?language=` | F11 — batch QA (read-only) |
| POST | `/aiml/v1/workspace/{post_id}/segments/{segment_key}/submit-review?language=` | Review Workflow — submit/resubmit (`aiml_translate` + `edit_post`) |
| POST | `/aiml/v1/workspace/{post_id}/segments/{segment_key}/approve?language=` | Review Workflow — approve pending (`aiml_review_translations` + `edit_post`) |
| POST | `/aiml/v1/workspace/{post_id}/segments/{segment_key}/reject?language=` | Review Workflow — reject pending, reason required (`aiml_review_translations` + `edit_post`) |
| POST | `/aiml/v1/workspace/{post_id}/segments/batch-review?language=` | Review Workflow — bounded batch submit/approve/reject (ADR-0015 §11.1) |
| GET | `/aiml/v1/workspace/review-queue?post_id=&language=&review_status=&page=&per_page=` | Review Workflow — filtered Store view (`aiml_review_translations`), never persisted |
| GET | `/aiml/v1/workspace/review-diagnostics?post_id=&language=` | Review Workflow — bounded diagnostics (`aiml_review_translations`); ADR-0015 §13 |
| GET | `/aiml/v1/workspace/operations?language=&status=&review_status=&publish_status=&is_stale=&source_type=&source_id=&page=&per_page=` | OTL.0 — language-scoped cheap operations list (`aiml_translate` **OR** `aiml_review_translations`); no QA/assessment/explain per row |
| GET | `/aiml/v1/workspace/operations/{translation_id}` | OTL.0 — detail by Store PK; same list capability plus `edit_post` on post-backed sources; TI.4/TI.5/TI.7 composed read-only |

### Review Workflow audit — `aiml_review_audit` (ADR-0015 §12)

Fired by `ReviewWorkflowService` (submit/resubmit/approve/reject),
`ReviewBatchCoordinator` (one summary per batch call), and
`ReviewEditInvalidationAuditBridge` (bridges the existing
`aiml_review_invalidated_by_edit` Store hook into this channel). Stable event
names: `review_submitted`, `review_resubmitted`, `review_approved`,
`review_rejected`, `review_invalidated_by_edit`, `review_batch_completed`.

Safe payload only — post/source id, segment key, language id, old/new
`review_status`, user id, timestamp, source surface, a non-reversible
submitted-hash fingerprint (first 8 chars), and rejection reason
presence/length. **Never** translation body, source body, or the full
rejection reason.

`GET .../segments` may include additive `meta.suggestions` and `meta.qa` (F11).

## AI provider admin REST — `src/Rest/ProviderController.php`

| Hook | Purpose |
|---|---|
| `rest_api_init` | Registers `aiml/v1/providers/*` (active, test-connection, models). |

Requires `manage_options`. Never returns cleartext API keys. Delegates to
`ProviderRegistry` only — no OpenAI types in the controller.

## Glossary REST — `src/Rest/GlossaryController.php`

| Hook | Purpose |
|---|---|
| `rest_api_init` | Registers `aiml/v1/glossary/*` for platform lexicon CRUD (ADR-0014). |

Requires `aiml_manage_glossary` (granted to Administrator on activation). Responses
include `X-AIML-Glossary-Api-Version: 1` and serialize ViewModels only — never
`GlossaryTermMatch`. Audit events fire on `aiml_glossary_audit` without term text.

| Method | Route |
|---|---|
| GET | `/aiml/v1/glossary` |
| POST | `/aiml/v1/glossary` |
| GET | `/aiml/v1/glossary/diagnostics` |
| GET | `/aiml/v1/glossary/{id}` |
| PUT/PATCH | `/aiml/v1/glossary/{id}` |
| DELETE | `/aiml/v1/glossary/{id}` |
| POST | `/aiml/v1/glossary/{id}/activate` |
| POST | `/aiml/v1/glossary/{id}/deactivate` |

Admin UI: `src/Admin/GlossaryAdminPage.php` (submenu under Multilingual).

## Background Translation Jobs REST — `src/Jobs/JobsController.php`

| Hook | Purpose |
|---|---|
| `rest_api_init` | Registers `aiml/v1/jobs/*` for orchestration (ADR-0011). |

Capabilities: `aiml_view_translation_jobs`, `aiml_manage_translation_jobs`,
`aiml_run_translation_jobs`, `aiml_cancel_translation_jobs` (see
`JobsCapabilities`). Controllers never hold canonical translation bodies;
Store remains SoT for content. Diagnostics never include secrets, prompts, or
segment bodies. Operator runbook:
[`docs/ops/BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md`](ops/BACKGROUND_TRANSLATION_JOBS_RUNBOOK.md).

| Method | Route |
|---|---|
| GET | `/aiml/v1/jobs/health` |
| GET | `/aiml/v1/jobs/diagnostics` |
| GET | `/aiml/v1/jobs` |
| POST | `/aiml/v1/jobs` |
| GET | `/aiml/v1/jobs/{id}` |
| GET | `/aiml/v1/jobs/batch/{batch_id}` |
| POST | `/aiml/v1/jobs/{id}/pause` |
| POST | `/aiml/v1/jobs/{id}/resume` |
| POST | `/aiml/v1/jobs/{id}/cancel` |
| POST | `/aiml/v1/jobs/{id}/retry-failed` |

CLI: `wp aiml jobs {list\|show\|run\|pause\|resume\|cancel\|retry-failed\|cleanup}`.

## Deliberately not hooked

- **No rewrite rules.** `add_rewrite_rule`, `add_rewrite_tag` and
  `flush_rewrite_rules` are absent by design (ADR-0002); prefix stripping means
  there is no rewrite state to manage.
- **No cookie.** The URL is the only language authority in this milestone, so
  front-end responses carry no `Set-Cookie` and stay cacheable at the edge.
- **REST under `aiml/v1` only.** Allowed controllers: `WorkspaceController`,
  `ProviderController`, `GlossaryController`, and `JobsController`.
- **No deactivation hook.** Deactivation must remove nothing, so there is
  nothing for it to do.
- **No WooCommerce hooks yet** beyond the compatibility declaration.

## Integration API v1 — `src/Integration/`

| Hook | Priority | Purpose |
|---|---|---|
| `aiml_register_integrations` | default | Receives `IntegrationRegistry`; code-owned `PluginIntegrationInterface` registration only |
| `aiml_integration_diagnostics_log` | — | Bounded diagnostics counter increments (no bodies/secrets) |
| `wp` (via `IntegrationFrontendBridge`) | 20 | Registers integration overlay hooks for non-default languages |

See [INTEGRATION_API_V1.md](INTEGRATION_API_V1.md).

## Workspace translation + suggestions — `src/Workspace/`

F10+F11 route automatic **persist** translation through `TranslationService` →
`AIProviderInterface` (resolved via `ProviderRegistry`; `NullAIProvider` when
unconfigured).

All **suggestions** (TM + AI) go through `TranslationSuggestionService` and
registered `SuggestionProvider` implementations. Controllers and
`BatchOperationCoordinator` never call suggestion providers or vendor APIs
directly.

TM write-back policy lives in `TranslationMemoryService` (ADR-F11-004). Machine
persist must never write TM; eligible human / AI-accepted / import saves are the
only write-back origins. **Amended by ADR-0015** (Review Workflow): when Review
Workflow is enabled, new-content write-back fires on review **approval**, not
on save — see `F11_FROZEN_API.md` §7.
