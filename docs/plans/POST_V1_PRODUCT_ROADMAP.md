# Post-v1 Product Roadmap — AI Multilingual

> **Superseded for long-term planning.** Do not extend this document with new strategic programs or milestones. The canonical long-term product roadmap is [`POST_V1_PLATFORM_ROADMAP.md`](POST_V1_PLATFORM_ROADMAP.md) (**Roadmap v1.0**, frozen). This file remains as the historical archive of the completed v1 platform track (Glossary → Review → Jobs) and as the product-parent reference for those shipped initiatives.

**Status:** Background Translation Jobs **Completed / merged / tagged** — ADR-0011 **Accepted**; tag `background-translation-jobs-complete`; merge `b308138c4`  
**Branch:** `main`  
**Baseline:** `main` after Jobs merge  
**Scope:** Product priorities after the Strategy F platform program  
**Code changes:** Glossary + Review + Background Translation Jobs shipped on `main`

---

## 1. Executive recommendation

Strategy F milestones **F1–F14** are the completed **platform-foundation program**: UUID identity, Store, leaf Gutenberg adapters, Translator Workspace, Translation Memory, AI provider framework, limited rollout, general availability, and expanded leaf allowlist.

The next phase is **not** an automatic F15 chain. It is a set of **named, independently shippable product initiatives** ordered for Biopentra merchant and translator value **without reopening frozen architecture**.

**Next initiative:** Post-v1 platform track (Glossary → Review → Jobs) is **complete**. Further initiatives are independently planned (see §6 deferred list); optional Nested Block Identity Spike remains research-only.

**Glossary MVP (complete):** High translator and merchant value — brand and scientific terminology stay consistent across Swedish content. Delivered via F11-reserved seams (`glossary_fragment`, `glossary_version`, `SuggestionProvider` tier, Workspace extension hooks) without a parallel suggestion path, UUID/Store/rollout/routing changes, or provider-architecture changes. See [GLOSSARY_MVP_VALIDATION_LOG.md](GLOSSARY_MVP_VALIDATION_LOG.md) and tag `glossary-mvp-complete`.

**Optional parallel research only:** Nested Block Identity Spike — timeboxed, non-blocking, no production behavior change. It is **not** a prerequisite for Review Workflow.

---

## 2. Platform baseline (complete)

| Area | State |
|---|---|
| F1–F14 | Complete, merged to `main`, tagged where applicable |
| ADR-0013 | Accepted |
| Supported Gutenberg blocks | `core/paragraph`, `core/heading`, `core/button`, `core/list-item`, `core/preformatted`, `core/verse`, `core/code` |
| Containers / nested identity | Unsupported |
| Elementor body identity | Deferred (open question on ADR-0013) |
| Render cache | Implemented; **disabled** pending measured tech + PO GO |
| Implementation started | **None** for post-v1 initiatives |

Related: [STRATEGY_F_PRODUCTION_IMPLEMENTATION.md](STRATEGY_F_PRODUCTION_IMPLEMENTATION.md), [F14_IMPLEMENTATION_SUMMARY.md](F14_IMPLEMENTATION_SUMMARY.md), [docs/ROADMAP.md](../ROADMAP.md).

---

## 3. Architectural boundaries (frozen)

Preserve without redesign:

- UUID identity (`aimlBlockId`, `b:<uuid>:<field>`)
- Store
- Translation Memory
- AI provider framework (`AIProviderInterface` / `ProviderRegistry`)
- `TranslationSuggestionService` and `SuggestionProvider`
- Translator Workspace
- Rendering pipeline and render gate
- Rollout architecture (stages, cohorts, kill switches)
- Metrics and diagnostics
- Cache contracts (including default-off render cache)
- REST API backwards compatibility (F10/F11 frozen surfaces)
- Security model

**Forbidden for every candidate:**

- Second rendering pipeline
- Duplicate translation storage
- Alternate UUID system
- Provider-specific logic outside provider boundaries
- Bypassing `TranslationSuggestionService` for suggestions

---

## 4. Candidate initiative comparison matrix

Ratings: **L** = Low, **M** = Medium, **H** = High.  
Columns: Merchant value | Translator value | Arch risk | Size | Ops risk | Deps | Urgency | Frozen-API fit | Biopentra usage | Spike/ADR

| # | Initiative | Merch | Trans | Arch | Size | Ops | Deps | Urg | API | Bio | Spike/ADR | Disposition |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | Container / nested Gutenberg identity | M | M | **H** | H | M | H | M | M | M | **Spike + likely ADR** | Parallel research only |
| 2 | Glossary & terminology | **H** | **H** | L | M | L | L | **H** | **H** | **H** | Plan; ADR if new table | **Next (#1)** |
| 3 | Human review & approval | **H** | **H** | L–M | M | M | M | H | H | H | Plan; maybe ADR for statuses | **#2** |
| 4 | Background translation jobs | H | H | M | H | M | M | M | H | H | **ADR-0011 baseline** | **#3** |
| 5 | Translation import/export | M | M | M | M | M | M | L | M | L–M | Plan | Deferred |
| 6 | Media translation | L–M | L | H | H | M | H | L | M | L | Spike | Deferred |
| 7 | WooCommerce-specific support | **H** | M | H | **H** | H | H | M | M | **H** | Plan + likely ADRs | Deferred (reassess after #1–#3) |
| 8 | Elementor support | **H** | M | **H** | **H** | H | H | M | L | **H** | **Spike + ADR required** | Deferred research; no impl without spike/ADR |
| 9 | String translation outside posts | M | M | M | M | M | M | L–M | M | M | Plan | Deferred |
| 10 | Translation version history | M | M | M | H | M | M | L | M | L–M | ADR likely | Deferred (not before Review) |
| 11 | Additional AI providers | L–M | M | L | M | L | L | L | H | L | Provider only | Deferred |
| 12 | Translator productivity metrics | L | M | L | M | L | L | L | H | L | Optional | Deferred |
| 13 | Translation analytics | L | L–M | L | M | L | L | L | H | L | Optional | Deferred |
| 14 | Multi-site support | L | L | H | H | H | H | L | M | L | Spike + ADR | Deferred |
| 15 | Translation package portability | L–M | M | M | M | M | M | L | M | L | Plan | Deferred |

### Per-initiative rationale (concise)

1. **Nested identity** — Needed for layout-heavy Gutenberg; recursive identity is a real architecture extension. Biopentra storefront is Elementor-primary, so urgency is Medium, not blocking.
2. **Glossary** — Immediate quality win on existing GA content; F11 seams reserved; Low architecture risk.
3. **Review** — Merchant trust and TM hygiene; additive to Workspace/QA/TM write-back; no version history prerequisite.
4. **Jobs** — Prerequisite for safe bulk AI; canonical plan written; J1+ gated on ADR-0011 amendment disposition.
5. **Import/export** — Useful later; not required for Biopentra’s current `sv` editorial loop.
6. **Media** — High complexity (alt text, captions, files); low current urgency.
7. **WooCommerce** — High merchant value for a shop, but F-track GA is `post`/`page`; cart/email/Store API is a large surface — reassess after #1–#3.
8. **Elementor** — High merchant value on Biopentra storefront, but highest architectural cost; spike+ADR gate mandatory.
9. **Strings** — Menus/theme/gettext matter for chrome; after content quality loop.
10. **Version history** — Nice-to-have; Review can ship on existing statuses/hashes first (ADR-0007 deferred pattern).
11. **More AI providers** — Framework ready; OpenAI sufficient for Biopentra now.
12. **Productivity metrics** — Optional operator nicety; not product-critical.
13. **Analytics** — Distinct from billing; low urgency; avoid analytics platforms.
14. **Multi-site** — Not Biopentra’s topology; High risk.
15. **Package portability** — Adjacent to import/export; defer.

---

## 5. Recommended initiative order

| Order | Named initiative | Type |
|---|---|---|
| 1 | **Glossary MVP** | Product implementation — **complete** (`glossary-mvp-complete`) |
| 2 | **Review Workflow** | **Complete** — merged + tagged |
| 3 | **Background Translation Jobs** | **Complete** — merged + tagged (`background-translation-jobs-complete`) |
| Parallel optional | **Nested Block Identity Spike** | Research only — does **not** block #2 |

No F15/F16 numbering. Each initiative is independently shippable with its own plan, validation, and release boundary.

---

## 6. Deferred initiatives

| Initiative | Why deferred |
|---|---|
| WooCommerce Translation Coverage | High value later; not next after platform. Reassess after Glossary, Review, Jobs. |
| String Translation Outside Post Content | Chrome/i18n residual; after content quality loop. |
| Translation Import/Export | Operational convenience; not blocking Biopentra `sv` workflow. |
| Translation Version History | Not required before Review; separate ADR/plan. |
| Additional AI Providers | OpenAI path sufficient; framework already provider-agnostic. |
| Translator Productivity Metrics | Optional; Workspace already covers core productivity. |
| Translation Analytics | Low urgency; keep distinct from billing platforms. |
| Media Translation | High complexity; low Biopentra urgency now. |
| Multi-site | Not required for this deployment. |
| Translation Package Portability | Follows import/export. |
| Render-cache activation | Remain **disabled** until measured technical and PO GO. |

---

## 7. Rejected / out of foreseeable product scope

| Item | Disposition |
|---|---|
| Billing / usage analytics **platform** | Out of product scope (F13 register). |
| Percentage / hash / visitor cohort rollout math | Rejected for foreseeable post-v1; F13 GA model sufficient. |
| Duplicate rendering or storage pipelines | **Hard reject** — architectural invariant. |
| Full Elementor implementation **without** prior spike and ADR | Rejected as an implementation start condition. Elementor **spike** may be scheduled later as research only. |

---

## 8. Architecture-impact assessment

| Initiative | Extension point only? | Schema | New public contract | New storage | Spike | ADR |
|---|---|---|---|---|---|---|
| Glossary MVP | Partially (suggestion + prompt seams) | Likely | Possibly Workspace glossary routes | Likely term store | No (plan first) | **If** new persistent storage |
| Review Workflow | Mostly Workspace/Store/TM | Possibly status fields | Possibly review routes | Possibly minimal | No | Maybe status model |
| Background Jobs | ADR-0011 + plan | **Yes** (`aiml_jobs`, `aiml_job_items`) | Job status APIs | Job tables (target 6) | Plan only | Amendment **Accepted** |
| Nested Block Spike | Research | No in spike | No in spike | No in spike | **Yes** | Decide in spike output |
| WooCommerce (deferred) | Limited today | Likely | Likely | Possibly | Likely | Likely |
| Elementor (deferred) | Deny paths only | Likely | Likely | Likely | **Required** | **Required** |

---

## 9. Required spikes and ADRs

| Item | When | Purpose |
|---|---|---|
| Glossary storage ADR | Before Glossary coding if a new table/option model is chosen | Own glossary persistence; versioning; invalidation vs TM `glossary_version` |
| Nested Block Identity Spike | Optional parallel | Evidence whether recursive identity needs a new ADR; **no production change** |
| Elementor spike + ADR | Only if PO prioritizes storefront body i18n | Segment identity for Elementor data model |
| ADR-0011 | **Accepted** (amendment Gate A, 2026-08-06) | Jobs complete — see [0011-resumable-job-pipeline.md](../adr/0011-resumable-job-pipeline.md) |

---

## 10. Special decisions

| # | Question | Answer | Class |
|---|---|---|---|
| 1 | Nested identity before more product features? | **No.** Optional parallel research only. | Product recommendation |
| 2 | WooCommerce-specific coverage now? | **Not next.** Reassess after Glossary, Review, Jobs. | Product recommendation |
| 3 | Is Elementor worth the cost now? | **Defer.** Spike + ADR required before any implementation. | Architecture requirement + product deferral |
| 4 | Glossary before Review? | **Yes.** | Product recommendation |
| 5 | Jobs before broader AI automation? | **Yes.** Retries, concurrency bounds, cost control, failure recovery, operator visibility, idempotency, safe bulk translate. | Architecture requirement |
| 6 | Version history before Review? | **No.** | Product recommendation |
| 7 | Render cache remain disabled? | **Yes**, until measured tech + PO GO. | Architecture / ops requirement |
| 8 | Ready for v1.0.0? | **Yes — scoped platform v1.0.0**, with explicit exclusions (below). Distinct from feature completeness. | Product recommendation |

### Scoped v1.0.0 readiness

**Release readiness:** The Strategy F platform on `main` (F1–F14) is ready to be described as a **scoped v1.0.0** for Gutenberg leaf translation + Workspace + TM + AI + GA rollout controls.

**Explicit exclusions (not “incomplete platform” — documented product limits):**

- No Elementor body translation
- No container / nested block identity
- No complete WooCommerce surface translation (cart, email, Store API, attributes, etc.)
- Render cache disabled
- Only the seven documented Gutenberg leaf blocks supported

Formal packaging/hardening remains Roadmap M7 territory and does not block naming the platform scope.

---

## 11. Named roadmap — initiative cards

### 11.1 Glossary MVP

| Field | Content |
|---|---|
| **Objective** | Enforce approved terminology for target languages through suggestion ranking and AI prompt fragments. |
| **User outcome** | Translators and merchants see consistent brand/scientific terms; fewer manual corrections; AI suggestions respect glossary. |
| **Scope** | Term storage ownership (to be decided in Glossary plan); source term; approved target term; language scope; optional context/domain; active/inactive; glossary versioning; `GlossarySuggestionProvider`; Workspace glossary management UI; AI prompt-fragment integration; QA terminology checks. |
| **Out of scope** | Review workflow; background jobs; Elementor; nested blocks; WooCommerce-specific surfaces; version history; new AI providers; schema freeze in *this* roadmap document. |
| **Dependencies** | F11 frozen APIs; TM `glossary_version` reserved column; `SuggestionProvider` interface; Workspace shell. |
| **Architecture impact** | Additive. Must register via `TranslationSuggestionService`. Must not bypass suggestion policy. May need schema + ADR for persistence. No UUID/Store render/rollout/routing changes. |
| **Likely work packages** | Plan/ADR → storage → domain service → `GlossarySuggestionProvider` → prompt fragment wiring → Workspace UI → QA checks → validation. |
| **Validation** | Unit + integration for provider ranking and prompt fragment; Workspace UI smoke; FP=0 render regression on allowlisted blocks; F11 API compatibility tests. |
| **Stop conditions** | Any second suggestion path; provider-specific glossary logic outside providers; breaking F11 contracts; production render path changes. |
| **Release boundary** | Independently shippable after Glossary plan DoD; default-compatible with existing GA. |
| **Readiness gate** | See §12. |
| **Implementation status** | **Complete** — merged to `main` @ `ab66fefd6`; tag `glossary-mvp-complete`. See [GLOSSARY_MVP_VALIDATION_LOG.md](GLOSSARY_MVP_VALIDATION_LOG.md). |

**Why next:** Highest Biopentra value among Low-architecture-risk candidates; reserved F11 seams; improves content already in GA without storefront Elementor investment.

### 11.2 Review Workflow

| Field | Content |
|---|---|
| **Objective** | Add human approval on the existing Workspace translation loop (Store-owned two-axis review state). |
| **User outcome** | Merchants can require review before treating translations as approved / TM-eligible where policy demands. |
| **Scope** | Submit for review; approve; reject with required reason; reviewer capability `aiml_review_translations`; **review queue = filtered Store view** on `review_status` (not an assignment system); basic status filters and summaries; QA and Glossary context during review; TM write-back gated by approval; audit and bounded diagnostics. |
| **Out of scope** | Collaboration comments; assignment queues / reviewer assignments; notifications; reporting dashboards; enterprise multi-step workflows; full version history; background jobs; glossary redesign; approval-gated frontend rendering (separate ADR if ever required). |
| **Dependencies** | Glossary MVP complete; [REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md](REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md); [ADR-0015](../adr/0015-review-workflow-and-tm-approval-policy.md) disposition before schema/code. |
| **Architecture impact** | Additive Store review columns (schema v5); translation `status` unchanged; no second review table; F11 TM write-back moves to approval-time; render path unchanged. |
| **Likely work packages** | R0 plan/ADR → R1 schema → R2–R3 domain → R4 REST/caps → R5 TM gate → R6 UI → R7 validation. |
| **Validation** | Permission tests; two-axis transition tests; submitted-hash 409; TM write-back policy tests; Workspace smoke. |
| **Stop conditions** | Requiring version history; building a second editor; changing render pipeline; overloading `status` with review states. |
| **Release boundary** | Independently shippable after Review plan DoD and ADR-0015 Accepted (or complete provisional). |
| **Readiness gate** | Canonical plan frozen; ADR-0015 Accepted or complete PO provisional; implementation branch from `main`. |
| **Implementation status** | **Complete** — merged to `main` @ `c8b383c67`; tag `review-workflow-complete`. ADR-0015 **Accepted**. Dev smoke **68/68 PASS**. See [REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md](REVIEW_WORKFLOW_IMPLEMENTATION_PLAN.md) and [REVIEW_WORKFLOW_VALIDATION_LOG.md](REVIEW_WORKFLOW_VALIDATION_LOG.md). |

### 11.3 Background Translation Jobs

| Field | Content |
|---|---|
| **Objective** | Safe asynchronous bulk translation using ADR-0011’s resumable job pipeline. |
| **User outcome** | Operators can queue large translation work with retries, visibility, and cost control. |
| **Scope** | Job table/storage per ADR-0011; Action Scheduler as trigger only; bounded concurrency; checkpoints; failure recovery; operator visibility; idempotent stages. |
| **Out of scope** | Expanding AI provider architecture; new suggestion pipelines; Glossary/Review feature work inside job milestone; Elementor/Woo surfaces. |
| **Dependencies** | Amended ADR-0011 Gate A or Gate B; Glossary and Review complete so jobs automate an approved quality loop. |
| **Architecture impact** | New job storage and APIs; must call existing AI/TM/Store paths; no alternate translation pipeline. |
| **Likely work packages** | J0–J8 per [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md). |
| **Validation** | Concurrency/idempotency tests; failure injection; cost/bounds tests; no FP on render allowlist. |
| **Stop conditions** | J1 without ADR amendment disposition; provider-specific job logic; unbounded fan-out; worker TM write-back; auto-approve. |
| **Release boundary** | Independently shippable; default-off or capability-gated until ops ready. |
| **Readiness gate** | Canonical plan frozen; **ADR-0011 amendment Accepted** (Gate A, 2026-08-06) — J1 authorized. |
| **Planning status** | **Complete** — merged to `main` @ `b308138c4`; tag `background-translation-jobs-complete`. Live smoke **35/35 PASS**; browser Jobs UI PASS. See [BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md](BACKGROUND_TRANSLATION_JOBS_IMPLEMENTATION_PLAN.md) and [BACKGROUND_TRANSLATION_JOBS_VALIDATION_LOG.md](BACKGROUND_TRANSLATION_JOBS_VALIDATION_LOG.md). |

**Why jobs before broader automation:** Retries, bounded concurrency, cost control, failure recovery, operator visibility, idempotency, and safe bulk translation are prerequisites for responsible AI automation at scale.

### 11.4 Nested Block Identity Spike (research only)

| Field | Content |
|---|---|
| **Objective** | Determine whether recursive/container identity (`core/list`, `core/quote`, `core/group`, `core/columns`, etc.) needs a new ADR and what render-safety proofs are required. |
| **User outcome** | Architecture evidence for a future product decision — **no user-facing change** from the spike itself. |
| **Scope** | Timeboxed research; fixture corpus; identity/render-safety analysis; ADR recommendation yes/no; written spike report. |
| **Out of scope** | Production adapters; `SUPPORTED_BLOCKS` expansion; rollout changes; any user-visible behavior. |
| **Dependencies** | None blocking Glossary. |
| **Architecture impact** | None in production during spike. |
| **Likely work packages** | Charter → corpus → analysis → ADR draft or “no ADR yet” → close. |
| **Validation** | Documented evidence only; zero production FP by construction (no prod change). |
| **Stop conditions** | Shipping adapters from the spike branch; expanding allowlist without a product plan. |
| **Release boundary** | Research artifact only — not a product release. |
| **Readiness gate** | Optional; may run in parallel with Glossary planning/implementation. |

---

## 12. Glossary MVP readiness gate

**Implementation must not begin until all of the following are true:**

1. This post-v1 roadmap is **reviewed and frozen** by product/engineering owners.
2. A **dedicated implementation branch** is created from updated `main` (not from this planning branch alone).
3. A **canonical Glossary MVP implementation plan** is written and approved.
4. Glossary **storage ownership** is decided (table vs options vs other) in that plan.
5. Any required **schema migration** is defined (not in this roadmap).
6. `GlossarySuggestionProvider` responsibilities and ranking tier are **frozen**.
7. Workspace glossary management integration is **defined**.
8. AI **prompt-fragment** behavior is **defined** (still via existing provider/prompt profiles).
9. QA **terminology checks** are **defined**.
10. **F11 frozen APIs** remain backwards compatible (see [F11_FROZEN_API.md](F11_FROZEN_API.md)).

If new persistent storage is introduced, an **ADR** must accompany the Glossary plan.

---

## 13. Relationship to Roadmap M3–M7

Strategy F absorbed platform identity (F1–F14) and a productivity subset of M3 (TM/AI/QA via F11). Post-v1 named initiatives continue product delivery without F-series numbering:

| Post-v1 initiative | Related classic roadmap |
|---|---|
| Glossary MVP | Remaining M3 glossary slice |
| Review Workflow | M3 / deferred review-hash themes |
| Background Translation Jobs | M3 / ADR-0011 |
| WooCommerce (deferred) | M4 |
| Strings (deferred) | M6 |
| Elementor (deferred spike) | M6 |
| Import/export (deferred) | M7 |

---

## 14. Confirmation

- Glossary MVP is **complete**, merged, and tagged (`glossary-mvp-complete`).
- ADR-0014 remains **Accepted**.
- F1–F14 remain **complete**.
- Review Workflow is **complete** (merged + tagged); ADR-0015 **Accepted**.
- Background Translation Jobs is **complete** (merged + tagged `background-translation-jobs-complete`); ADR-0011 **Accepted**.

---

## 15. Exact next step

Controlled production deployment of AI Multilingual Platform v1.0 (Glossary + Review + Background Translation Jobs) per ops runbooks; no further post-v1 platform-track coding required for this release boundary.
