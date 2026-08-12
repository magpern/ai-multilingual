# TSC.0 — Internal Surface Capability Foundation Implementation Plan

**Status:** **COMPLETE** on `main` — merge `6ee696cff87070c23201e9bb9447067e72af7248`
**Milestone:** TSC.0 Internal Surface Capability Foundation
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md) (Architecture Frozen on `main`)
**External review:** **FREEZE** (amended architecture) · **STATE A** · **TARGET 7**
**Independent planning review:** **PASS**
**Independent implementation review:** **PASS**
**ADR:** **None** for TSC.0 (term ADR remains TSC.1)
**Freeze merge:** `3532a490cd09487876d5bf09c0eec10ba8566bea`
**Implementation merge:** `6ee696cff87070c23201e9bb9447067e72af7248`
**Validation log:** [TSC0_VALIDATION_LOG.md](TSC0_VALIDATION_LOG.md)
**Evidence:** [TSC0_IMPLEMENTATION_EVIDENCE.md](TSC0_IMPLEMENTATION_EVIDENCE.md)
**Depends on:** AI Multilingual **v1.3.0**; TIQ Complete; OTL Complete; TSC Parent Frozen; `Migrator::TARGET` **7**
**Related:** ADR-0001 overlay; ADR-0005 Store; ADR-0007 hashes; ADR-0017 Integration API; ADR-0015 Review; ADR-0020 Publication; [OTL_PARENT_IMPLEMENTATION_PLAN.md](OTL_PARENT_IMPLEMENTATION_PLAN.md); [TIQ_PARENT_IMPLEMENTATION_PLAN.md](TIQ_PARENT_IMPLEMENTATION_PLAN.md)

**This document is the authoritative implementation specification for TSC.0.** Work packages TSC0.0–TSC0.7 are **COMPLETE**.

**Production implementation status:** **COMPLETE.**
**TSC.1–TSC.6 implementation status:** TSC.1 **COMPLETE**; TSC.2–TSC.6 **NOT STARTED.**

**Exact next step:** **TSC.2 Architecture Frozen** — begin authorized TSC.2 implementation when an implementation task is opened. Do **not** start TSC.3+.

---

## 1. Baseline

| Field | Value |
|---|---|
| Planning baseline | `main` @ `cc96e5426c9a3201e3ac7938270fc53e2787c296` |
| `main` == `origin/main` | Yes (at branch creation) |
| Version | **1.3.0** |
| Tag `v1.3.0` | Unchanged → `c88ba30681439d9e7113a20d7ebc03c942dd240d` |
| `Migrator::TARGET` | **7** |
| TIQ / OTL | Complete |
| TSC Parent | Frozen |
| TSC.0–TSC.6 implementation | Not started |
| Drift from externally reviewed SHA | **None** |

### Repository reality (spine)

| Concern | Path / behavior |
|---|---|
| Store | `src/Translation/Store.php` — segment identity; orphan → `ignored`/`orphaned` |
| Extract | `src/Translation/Extractor.php` + IntegrationRegistry |
| Stale today | `Plugin::register_stale_detection` — `save_post` @20 only |
| OTL gates | `AllowedActionsResolver`, `OperationsBulkCoordinator` — post-only |
| Publication visibility | `PublicationService::is_source_public` — post + publish |
| Jobs | Create accepts any sanitized type; materialization/auth post-shaped; ItemProcessor lacks explicit orphan rule |
| CPT lists | Workspace / RenderGate / Rollout / Admin Editor scattered |
| Fluent Forms | `FORM_ID=5` behavioral; `CONTACT_PAGE_ID=3410` unused; forward embed detect only |
| Rank Math | Six SEO text metas; no `updated_post_meta` observer |
| Activation | Block/Elementor extraction default **OFF** |

---

## 2. Objective

Establish the **internal** surface capability foundation so later TSC milestones can admit new surfaces without scattering `source_type` forks or inventing parallel Stores/policies.

TSC.0 delivers:

- narrow SurfaceCapability + SurfaceRegistry;
- PostSurfaceAdapter proof (delegate, not absorb);
- internal CPT admission consolidation;
- request-local invalidation coordinator (final-state flush);
- Rank Math post SEO stale remediation via coordinator;
- Fluent Forms **neutrality** remediation (stale remains Unsupported);
- orphan/Jobs lifecycle invariants (facts to TI.6);
- PluginGuard structural neutrality;
- parent matrix characterization tests.

TSC.0 does **not** implement SOURCE_TERM, hosted-term adoption, public APIs, Woo config observers, or Elementor document-save hooks.

---

## 3. Ownership boundaries

| Owner | Domain |
|---|---|
| **TSC SurfaceCapability / Registry / PostSurfaceAdapter** | Facts, capability declarations, activation facts, invalidation **event mapping**, extraction **delegation entry** |
| **RequestLocalInvalidationCoordinator** | Mark dirty / coalesce / **one** request-final `Store::sync_source` |
| **Extractor** | Post extraction composition |
| **SegmentAssembler** | Workspace assembly |
| **Store** | Sync, persist, orphan column state |
| **TI.6** | Jobs admission, retry, execution |
| **TI.7** | Publication policy |
| **OTL** | Allowed-action orchestration |
| **Existing URL helpers** | Established edit/frontend links |

**Architecture tests must prove:** registry is not a second orchestrator; PostSurfaceAdapter does not own policy; Store/Extractor/SegmentAssembler authority intact.

---

## 4. Source-type inventory (implementation guidance)

| Cluster | Class | TSC.0 action |
|---|---|---|
| OTL mutate/presentation post gates | C | Rewire to `supports` / auth facts |
| `PublicationService::is_source_public` | A | Delegate visibility **fact** to PostSurfaceAdapter |
| Jobs auth skip-non-post / create any-type | C/A | Admit registered types only; auth via facts |
| Extractor / SegmentAssembler / Worker `WP_Post` | B | Keep shapes; call via adapter delegation |
| Workspace REST `post_id` | B | Unchanged |
| Frontend overlays hardcoded post | B | Unchanged |
| Store / JobLockKey / VMs | D | Leave |

No SOURCE_TERM. No new `if (source_type === 'term')` forks.

---

## 5. SurfaceCapability / SurfaceRegistry architecture

**Package:** `AIMultilingual\Surface\` (internal).

### SurfaceCapability (narrow)

Supplies:

1. `source_type()` classification  
2. `exists(source_id)`  
3. `source_subtype(source_id)`  
4. `user_can_edit_source(user_id, source_id)` — WP-cap delegation  
5. `is_visitor_public(source_id)` — **visibility fact only**  
6. `supports(capability)` — inspect|translate|mutate|jobs|publish_inputs|overlay|stale_observe  
7. activation facts (implemented vs settings-activated)  
8. invalidation event **registration** into coordinator (maps hooks → dirty marks)  
9. extraction **entry/delegation** where needed (posts → Extractor)  
10. link facts only if no established owner  

Must **not**: run Jobs/publication/review/QA policy; absorb SegmentAssembler/Store orchestration; expose `can_publish_translation`.

### SurfaceRegistry

- `register` / `for` / `require` / `supports`  
- Keyed by `source_type`  
- Wired once in `Plugin.php`  
- **No** public registration API / filter  

### PostSurfaceAdapter

- Answers post facts listed above  
- Registers `save_post` (mark dirty) + allowlisted Rank Math meta (mark dirty) into coordinator  
- Delegates extract → Extractor; assemble → SegmentAssembler; sync → Store  
- Compatibility: identity unchanged; no new Store rows; no silent feature-flag ON  

---

## 6. Internal CPT admission foundation

**Class (illustrative):** `AdmittedPostTypes`

- Code-owned single authority consolidating Workspace / RenderGate / Rollout (and related) lists  
- Contexts: `workspace` | `frontend_overlay` | `rollout` (preserve `nav_menu_item` workspace-only behavior)  
- Initial admitted set preserves current first-party behavior  

### Forbidden

- `aiml_admitted_post_types` or any **new** public WP filter/action for CPT admission  
- Public CPT registration API  
- User-configured CPT admission  
- Auto-admit all public CPTs  

**Terminology:** INTERNAL CPT ADMISSION FOUNDATION — not public third-party CPT opt-in (that is TSC.6).  
**Pre-existing public CPT admission hook:** none found that must be preserved.

---

## 7. Fluent Forms

### Reverse-host audit

Only `FluentFormsEmbedDetector::embeds_form( WP_Post $post, int $form_id )` exists (forward). **No** bounded `form_id → host posts` index/API/durable mapping.

### Neutrality / extraction (MUST)

- Remove behavioral `FORM_ID = 5`  
- Remove `CONTACT_PAGE_ID = 3410`  
- Discover form IDs from the **current admitted host post** only  
- Code-owned field allowlist  
- No Biopentra defaults; no sitewide enumeration  
- Unsafe multi-form identity → disable/limit (allowed fallback)  

### Stale (UNSUPPORTED in TSC.0)

Do not invent reverse-host persistence, site scan, schema, or TARGET bump. Keep S15 **UNSUPPORTED**. Do not disable safe genericized extraction solely because stale remains incomplete.

---

## 8. Request-local invalidation coordinator

### Contract

1. Mutation hooks do **not** independently implement sync/extraction policy  
2. Event → bounded `(source_type, source_id)` → **mark dirty**  
3. Duplicate marks coalesce  
4. **One** `Store::sync_source` (via surface/Extractor delegation) after final source values are observable  
5. No provider calls from invalidation  
6. No durable queue/table  
7. Autosave/revision: do not mark dirty / clear pending dirty for that object  
8. Deletion: mark orphan path or explicit object-orphan when extract impossible  

### Final-state flush mechanism (independently verified)

**WordPress lifecycle risk:** plugins commonly call `update_post_meta` **during or after** `save_post` (often at later priorities). Flushing on `save_post` @20 (current stale priority) can observe **intermediate** meta and miss later writes in the same request.

**Frozen mechanism (STATE A refinement):**

| Step | Behavior |
|---|---|
| `save_post` | **Mark dirty only** (skip autosave/revision); **do not flush** |
| Allowlisted `updated/added/deleted_post_meta` | **Mark dirty only** |
| **`shutdown`** | **Sole flush authority** for dirty identities in this request → one sync per identity with final readable source/meta |
| Already-flushed guard | Prevents double flush if shutdown re-enters |

This preserves AC18/AC19/AC36 final-state correctness. Meta-only AJAX requests (no `save_post`) still flush on `shutdown`.

Reusable later by TSC.1/3/5 event mappers without implementing those hooks now.

---

## 9. Rank Math invalidation

Allowlisted keys only:

- `rank_math_title`  
- `rank_math_description`  
- `rank_math_facebook_title`  
- `rank_math_facebook_description`  
- `rank_math_twitter_title`  
- `rank_math_twitter_description`  

Must prove: N meta mutations → one final sync with final values; meta + save_post → one final sync; no provider call; meta-only not missed.

Term SEO remains TSC.1 / Unsupported.

---

## 10. Orphan / deletion

Reuse `Store::sync_source` → `ignored` + `error_code=orphaned`. Overlay already ineligible for ignored/missing.

Object delete: coordinator/orphan pass without writing translations into WP tables.

**Honesty debt (out of TSC.0):** publish axis may remain `published` on orphaned rows while overlay is suppressed — TI.7/OTL policy later; no auto-unpublish invented here.

---

## 11. Jobs lifecycle invariants

TI.6 owns policy. TSC supplies facts. **No** `TSCJobsAdmissionPolicy`.

1. New Jobs work cannot admit **unregistered** `source_type`  
2. New work cannot admit **missing** source objects  
3. Execution revalidates source existence through TI.6 architecture  
4. Authoritative Store state `ignored` / `orphaned` / missing → **not** provider-processed as normal active translation  
5. Retry/resume must **not** revive an orphan merely because a Job record exists  
6. Today ItemProcessor only implicitly skips non-machine statuses — TSC.0 requires **explicit** orphan/ignored short-circuit in TI.6 paths consuming Store status facts  

---

## 12. Visibility / authorization / activation facts

- Visibility: exists / visitor_public / subtype / reason — **never** `can_publish_translation`  
- Auth: `edit_post` (and type caps) via adapter; no admin-configured capability callbacks  
- Activation ≠ capability; block/Elementor defaults remain OFF  

---

## 13. Performance contract

**Allowed:** O(1) cached `registry->for(source_type)` per Operations row.

**Forbidden per-row list work:** DB extraction, assembly, TI.5 assessment, TI.7 explain, Jobs query, network/provider, adapter reconstruction.

---

## 14. PluginGuard contract

Structural proofs in production `src/`:

- FORM_ID=5 behavioral dependency removed  
- CONTACT_PAGE_ID=3410 removed  
- no Biopentra / biopentra.eu brand host literals  
- no named site-specific mapping constants reintroduced  
- no new hardcoded production form/page mappings  

**Not:** generic “suspicious integer” bans. Docs/fixtures excluded.

---

## 15. Provider payload safety

Admitted visitor-facing text only; no secrets, credentials, private operational settings, serialized PHP, arbitrary meta. Code-owned allowlists only.

---

## 16. Parent TS characterization

Characterization tests lock CURRENT honesty and TSC.0 targets:

| Surface | After TSC.0 |
|---|---|
| Core post stale | SUPPORTED |
| Rank Math post SEO stale | **SUPPORTED** (coordinator) |
| Fluent neutrality | fixed or disabled |
| Fluent stale | **UNSUPPORTED** |
| Hosted terms / Woo chrome | UNSUPPORTED (unchanged) |
| Elementor | PARTIAL (unchanged) |
| Block/Elementor activation | OPT-IN OFF |

---

## 17. SF1–SF22 (frozen)

| ID | Requirement | Evidence |
|---|---|---|
| SF1 | Narrow SurfaceCapability + Registry | Architecture unit tests |
| SF2 | Facts≠policy; not second orchestrator | Architecture tests |
| SF3 | PostSurfaceAdapter delegates | Architecture + regression |
| SF4 | OTL/Jobs/Pub consume facts | Integration |
| SF5 | Source-type admission via registry | Unit + Jobs create |
| SF6 | Internal AdmittedPostTypes; **no public filter** | Architecture |
| SF7 | Existing admitted types preserved | Integration |
| SF8 | No auto-all CPTs; no third-party CPT API | Architecture |
| SF9 | Fluent hardcoded IDs removed or disabled | PluginGuard + unit |
| SF10 | No unbounded Fluent scan | Architecture |
| SF11 | Request-local coordinator mark→coalesce→shutdown flush | Unit |
| SF12 | Rank Math allowlisted meta via coordinator | Integration |
| SF13 | Fluent stale UNSUPPORTED | Characterization |
| SF14 | Orphan ignored/orphaned | Store tests |
| SF15 | Jobs orphan invariants (TI.6) | Integration |
| SF16 | Visibility facts; TI.7 policy | Architecture |
| SF17 | Auth facts via WP caps | Unit |
| SF18 | Activation ≠ capability; defaults OFF | Unit |
| SF19 | O(1) registry; no expensive list N+1 | Architecture/perf |
| SF20 | Provider payload safety | Unit/architecture |
| SF21 | PluginGuard structural neutrality | PluginGuardTest |
| SF22 | No public API / schema / SOURCE_TERM / hosted-term adopt | Architecture |

---

## 18. Work packages TSC0.0–TSC0.7 (PLANNED — not implemented)

| WP | Objective | Expected areas | Depends | STOP |
|---|---|---|---|---|
| **TSC0.0** | Characterization of parent stale/activation matrix | `tests/` | — | Overclaim Supported |
| **TSC0.1** | Narrow SurfaceCapability + Registry + ownership tests | `src/Surface/*`, `Plugin.php` | 0.0 | Public API / god interface |
| **TSC0.2** | PostSurfaceAdapter + factual consumer rewires | Operator/*, PublicationService, Jobs create/auth | 0.1 | Post behavior regression |
| **TSC0.3** | AdmittedPostTypes consolidation; **no WP filter** | Workspace/RenderGate/Rollout | 0.2 | Public CPT filter |
| **TSC0.4** | Invalidation coordinator + orphan/delete + Jobs orphan invariants | Coordinator, Plugin hooks, Jobs ItemProcessor/Service | 0.2 | Sync-on-first-meta; schema |
| **TSC0.5** | Rank Math wiring + Fluent neutrality; stale Unsupported | RankMath/*, FluentForms/* | 0.4 | Reverse-host invent; keep FORM_ID |
| **TSC0.6** | PluginGuard + provider safety + perf | PluginGuardTest | 0.5 | Brittle integer bans |
| **TSC0.7** | Docs / validation / milestone closure | docs/plans | all | Version/TARGET bump |

---

## 19. AC1–AC36 (contiguous)

1. Admitted post workflows unchanged (edit/translate/publish/retranslate)  
2. Surface contract is facts/delegation only  
3. No second policy/orchestrator engine  
4. No public surface/CPT/meta registration symbols  
5. TARGET remains 7  
6. Version remains 1.3.0  
7. PostSurfaceAdapter delegates Extractor/Assembler/Store  
8. OTL mutate gates use supports/auth facts  
9. Visibility fact delegated; TI.7 policy retained  
10. Jobs create rejects unregistered source_type  
11. Missing source object cannot admit new Jobs work  
12. Workspace admitted types preserved; unknown CPT excluded  
13. Render/rollout consolidation preserves prior inclusions/exclusions  
14. No `aiml_admitted_post_types` or equivalent new public CPT filter  
15. Fluent: no FORM_ID=5 / CONTACT_PAGE_ID=3410 behavior  
16. Fluent: no sitewide form enumeration  
17. Fluent embed discovery extracts generically **or** path disabled  
18. Rank Math: N meta updates in one request → **one** final sync with **final** values  
19. Rank Math: meta + save_post → **one** final sync  
20. Invalidation hooks never call providers  
21. Autosave/revision exclusions preserved  
22. Missing extract unit → ignored/orphaned  
23. Jobs: ignored/orphaned/missing not provider-processed; retry cannot revive orphan  
24. Deleted post orphan path without WP content writes  
25. Block/Elementor activation defaults OFF  
26. Capability vs activation facts distinct  
27. Operations list: O(1) registry only; no per-row extract/assemble/assess/explain/Jobs/provider  
28. Coordinator coalesces duplicate dirty marks  
29. Provider safety for admitted text only  
30. PluginGuard structural neutrality proofs  
31. Hosted term stale remains Unsupported  
32. Woo filter chrome stale remains Unsupported  
33. Fluent stale characterization remains Unsupported  
34. No SOURCE_TERM / term extractors / hosted-term adoption  
35. No TSC.1 ADR authored  
36. Request-local coordinator **shutdown flush** is the sole Rank Math/save_post invalidation flush authority (hooks mark dirty only; no sync-on-first-meta; no flush-on-save_post that can miss later meta)

---

## 20. Test strategy

- **Unit:** capability, registry, coordinator coalesce/shutdown flush, CPT admission, Fluent discovery, Rank Math keys  
- **Integration:** post regression; Rank Math final-state; Fluent neutrality; Jobs orphan/retry; delete orphan  
- **Architecture:** ownership; no public CPT filter; no god adapter; PluginGuard structural  
- **Perf:** cached registry; no expensive list N+1  
- **Browser:** none required unless Fluent FE breaks → bounded smoke only  

---

## 21. TSC.1 compatibility proof

Future `TermSurfaceAdapter` (`source_type=term`, `source_id=term_id`, `source_subtype=taxonomy`) fits the same narrow interface + coordinator event mapping without second Store, second invalidation system, TARGET bump, or reintroducing scattered type forks (if TSC.0 rewires complete).

**Do not implement TermSurfaceAdapter in TSC.0.**

---

## 22. Schema / TARGET / ADR

| Item | Verdict |
|---|---|
| Schema | **STATE A** — no migration |
| TARGET | **7** |
| Second Store | Forbidden |
| ADR | **None** for TSC.0 |

---

## 23. Risks / debt

- Fluent stale Unsupported until reverse-host architecture (may need later ADR)  
- Publish axis may remain published on orphaned rows while overlay suppressed  
- Jobs worker still post-shaped until TSC.1  
- Multi-form Fluent may force disable fallback  

---

## 24. STOP conditions

STOP if TSC.0 would require: new Store/table/TARGET bump; public surface/CPT/meta API; second policy engine; durable invalidation queue; sitewide Fluent scan; Biopentra defaults; SOURCE_TERM / hosted-term adopt; TSC.1 ADR; WP/Woo translation writes; sync-on-first-meta that fails final-state; god-interface absorption of Store/Extractor/Assembler.

---

## 25. Explicit non-goals

SOURCE_TERM; TermSurfaceAdapter; term hooks; hosted-term lazy adoption; TSC.1 ADR; public surface/CPT/meta registration; user CPT admission; Woo config observers (TSC.3); Elementor document hooks (TSC.5); Fluent reverse-host persistence; sitewide Fluent scans; second Store; second publication/Jobs policy; durable invalidation queue; silently enabling Gutenberg/Elementor flags.

---

## 26. Exact next step after Architecture Frozen on main

Begin authorized **TSC.0 implementation** from frozen main via `feature/tsc0-*` only when an implementation task is opened. Execute TSC0.0→TSC0.7 per this plan. Do not start TSC.1.
