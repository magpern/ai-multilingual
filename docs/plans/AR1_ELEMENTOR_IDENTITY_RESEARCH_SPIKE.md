# A.R1 — Elementor Identity Research Spike

**Status:** Planning complete — research **not started**  
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — milestone **A.R1** (Research Spike)  
**Baseline:** Platform **v1.0.0**; **P1 complete** ([validation log PASS](P1_PLATFORM_STABILIZATION_VALIDATION_LOG.md)); planning branch rooted at `5d224cd074a29695f116ace664001760296c5218`  
**Planning branch:** `feature/ar1-elementor-identity-spike-plan`  
**Research branch (after plan merge):** `feature/ar1-elementor-identity-spike` (create from updated `main`; do **not** run ER0–ER7 on the planning branch)  
**Evidence log (reserved; create when ER0 begins):** `docs/plans/AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md` — **do not create until research starts**  
**ADR:** Not written in this milestone. Expected later: *Elementor identity and ownership model*.  
**Elementor production implementation:** **Blocked**.  
**A.2 Elementor Foundation:** **Blocked** until:

1. ER0–ER7 are complete  
2. Result is **GO** or **CONDITIONAL GO**  
3. Required Elementor identity/ownership ADR is written  
4. ADR is **explicitly Accepted**

**This document authorizes research planning only.** It does **not** authorize Elementor production translation, extractors, adapters, renderers, migrations, REST, UI, schema changes, research prototypes, fixtures, or ADRs.

---

## 1. Purpose

Determine whether AI Multilingual can support **visitor-facing Elementor content** while preserving frozen Platform v1 invariants, and—if so—which **identity family**, **ownership scopes**, and **translation-unit granularity** are safe.

The spike must determine **both**:

- what **can** be translated safely; and  
- what **must not** be translated (deterministic deny-list with explicit reasons).

A.R1 is architecture-heavy **research**. It must produce evidence, confidence-labelled findings, a GO / CONDITIONAL GO / NO-GO recommendation, and—when GO or CONDITIONAL GO—an advisory **recommended first implementation surface** for A.2 planning. It must **not** ship Elementor support or authorize implementation.

---

## 2. Core research questions

### 2.1 Primary question

> Can Elementor visitor-facing values receive deterministic identities compatible with AI Multilingual while Elementor retains ownership of canonical content and all frozen v1 contracts remain intact?

Supporting invariants to preserve:

- every translated visitor-facing value has a **deterministic identity**;
- canonical Elementor data remains **owned by Elementor**;
- AI Multilingual stores **overlays**, not duplicated Elementor documents;
- source content is never silently rewritten for translation storage (overlay-only);
- identity or rendering uncertainty falls back to **source**;
- no generic rendered-HTML scraping;
- no fuzzy rematching as the identity mechanism;
- Store, TM, Glossary, Review, Jobs, routing, and suggestion ownership remain unchanged;
- existing Gutenberg UUID and Store contracts are **not weakened**.

The spike must answer whether an Elementor-specific identity family can **coexist safely** with the existing Gutenberg block identity family (`b:<uuid>:<field>`).

### 2.2 Ownership scope (do not predetermine)

The spike must **determine** the correct ownership scope for Elementor identities. It must **not** presuppose that every Elementor identity is document-local.

Use these ownership terms:

| Term | Meaning |
|---|---|
| **Document-owned** | Bound to a specific WordPress post/document that owns the Elementor data |
| **Shared-definition-owned** | Bound to a shared template / global / Theme Builder definition that is referenced |
| **Consuming-document-owned** | Bound to a consuming page when insertion is a copy or when reference semantics are insufficient |
| **Explicitly unsupported (ambiguous ownership)** | No safe identity; leave source; do not invent silent sharing |

**Frozen defaults (until evidence revises them):**

- Ordinary page elements are **presumed document-owned** unless evidence disproves it.
- Reliably **referenced** templates may be **definition-owned** if Elementor provides stable reference semantics.
- **Copied** content becomes **independently owned**.
- Translations are **never silently shared** across unrelated documents.

The research must produce a final **ownership decision matrix** (content kind × ownership scope × collision rules). Cross-document collision protection must not be weakened.

### 2.3 Translation-unit granularity

**Element / widget identity alone is not enough.**

Explicit question:

> What is the stable identity of **one translatable value** inside an Elementor document?

Evaluate identity granularity for at least:

- element / widget instance;
- widget control / setting;
- responsive variant;
- repeater row;
- nested repeater row;
- tab / accordion item;
- template reference;
- dynamic-tag configuration;
- field inside a third-party widget.

**Every viable identity model must account for:**

| Component | Role |
|---|---|
| Owner scope | Document-owned / shared-definition-owned / consuming-document-owned / unsupported |
| Element identity | Stable handle for the Elementor element (or rejection) |
| Control / field key | Distinguishes two fields in the same widget |
| Repeater / nested-item identity | Distinguishes two repeater/tab rows sharing a field name |
| Responsive variant | Where Elementor stores distinct device values |
| Source-language value | Overlay payload semantics |
| Source / stale hash | Freshness — **not** a substitute for identity |

The model must distinguish:

- multiple fields in one widget;
- repeated rows using the same field name;
- nested repeater items;
- tabs and accordion entries;
- responsive variants;
- template references;
- third-party widget fields.

**A widget-level identity that cannot uniquely identify each value cannot receive a GO recommendation.**

### 2.4 Unsupported content as a first-class output

Discovering supportability is incomplete without an equally rigorous **deny** surface.

For every widget/control category and every identity candidate, classify into exactly one of:

| Classification | Meaning |
|---|---|
| **Directly supportable** | Safe under the recommended identity model without a special adapter |
| **Supportable through adapter** | Safe only with an explicit widget/control adapter contract |
| **Unsupported** | Must not be translated; leave source |

For every **unsupported** item, record an explicit reason from this bounded taxonomy (extend only with documented justification):

- ownership ambiguity  
- unstable identity  
- dynamic runtime value  
- third-party opaque persistence  
- unsupported Elementor behavior  
- unacceptable performance  
- cache/language safety  
- security/privacy concern  
- architectural contract violation  

The research deliverable must include a **deterministic deny-list**, not merely an allow-list. Source-fallback policy is unchanged: unsupported or ambiguous values render **source**.

---

## 3. Frozen research principles

1. Elementor remains the canonical owner of Elementor data.  
2. AI Multilingual stores translation overlays only.  
3. Every translated visitor-facing value requires deterministic identity.  
4. Source content is never silently rewritten.  
5. Identity uncertainty falls back to source.  
6. No arbitrary rendered-HTML scraping.  
7. No fuzzy rematching as identity.  
8. Gutenberg UUID contracts remain unchanged.  
9. Store, TM, Glossary, Review, Jobs, routing, and suggestion ownership remain unchanged.  
10. Unsupported or ambiguous content remains untranslated.  
11. Duplicate/copy operations must not create cross-document collisions.  
12. Research prototypes may not become production code without explicit approval.

Additional Platform v1 principles remain in force (overlay-not-duplication, PluginGuard-class invariants, sole suggestion path, Jobs vs Store ownership, etc.). See roadmap §3.

---

## 4. Scope

### In scope

- Elementor document storage model and meta (`_elementor_data`, edit mode, template type, …)
- Element/node identity and nested containers
- Widget settings, responsive settings, structured/repeater fields
- Template / Theme Builder / global / reusable ownership
- Stable IDs vs paths; hybrid models; unsupported/adapter policy
- Source extraction **candidates** (research only)
- Frontend rendering interception **research** (supported hooks only)
- Editor save/update, duplicate/copy/template, import/export behaviour
- Version compatibility research matrix
- Research-only prototypes and fixture analysis under contained paths (on the **research** branch only)

### Out of scope (this planning task and A.R1 production)

- Production Elementor translation
- Beginning ER0–ER7 on the **planning** branch
- Creating the research log in this planning task
- Research prototypes, fixtures, extractors, adapters, render hooks
- Broad widget support / UI / REST / DB migration / public release / schema changes
- Writing or accepting the Elementor identity ADR in this planning milestone
- WooCommerce coverage; nested Gutenberg implementation (A.R2)
- HTML/DOM scraping as a production design
- Modifying Elementor core/plugin files
- Storing copied Elementor documents per language
- Translation of wp-admin or Elementor editor UI

---

## 5. Baseline context (platform)

| Reference | Relevance |
|---|---|
| ADR-0001 | Overlay storage; no translation writes that annex foreign persistence |
| ADR-0005 | Historical segment-key examples include `elementor:…` — **not** a binding Elementor grammar |
| ADR-0013 | Gutenberg `b:<uuid>:<field>` accepted; Elementor-primary coverage remains an **open** question |
| Strategy F production plan | Save pipeline **skips Elementor bodies**; gate reason `elementor_body` |
| Extractor | Detects Elementor via `_elementor_data` / `_elementor_edit_mode` and refuses body translation today |
| P1 | Complete; operational verification exists before architecture-heavy research |

### Version baseline policy

Do **not** state that a particular Elementor or Elementor Pro version is installed unless verified evidence exists.

**ER0 must capture**, from the live research environment, before experiments begin:

- Exact Elementor plugin slug and version  
- Exact Elementor Pro plugin slug and version  
- WordPress version  
- PHP version  
- Relevant Elementor feature flags  
- Theme and integration context (including Blocksy where relevant)

Distinguish:

- **Verified environment facts** (captured in the research log during ER0)
- **Proposed test-matrix versions** (planned comparisons)

Until ER0 evidence exists, treat any previously mentioned site versions as **provisional and unverified**.

> Exact installed versions must be captured during ER0 before experiments begin.

---

## 6. Elementor storage analysis (required investigation)

The spike must investigate (prove with evidence, do not assume):

- `_elementor_data`
- `_elementor_edit_mode`
- `_elementor_template_type`
- Elementor document/post relationships
- Native element IDs
- Nested containers and child elements
- Controls and settings
- Responsive values
- Repeaters and nested repeaters
- Saved templates
- Theme Builder templates
- Global widgets
- Reusable sections/containers
- Revisions and autosaves
- Duplicate / copy / paste behaviour
- Page duplication
- Import / export
- ID regeneration or mutation
- Dynamic tags
- Shortcode and HTML controls
- Third-party widgets
- Caching and CSS generation

**Do not assume Elementor element IDs are stable enough.** Prove or reject with repeatable evidence.

---

## 7. Identity candidates to evaluate

For every candidate assess: determinism; value-level granularity; edit stability; uniqueness; copy/duplicate semantics; cross-document safety; template ownership; import/export; overlay purity; maintenance cost; Store compatibility; rendering safety.

For every candidate, also record:

- overall classification under §2.4 (**directly supportable** / **supportable through adapter** / **unsupported** as a general model, noting partial coverage where applicable);
- explicit unsupported reasons (from the §2.4 taxonomy) for categories the candidate cannot cover safely;
- **evidence confidence** under §14.1 for each major claim about the candidate.

### Candidate A — Native Elementor identity

Conceptual composition (illustrative only):

```text
owner scope
  + document/reference ID
  + native element ID
  + field/control key
  + nested-item identity where required
  (+ responsive variant where applicable)
```

Evaluate stability, uniqueness, duplication, copy/paste, template reuse, import/export, third-party widgets, and **value-level** field/nested-item keys.

### Candidate B — AIML identity stored inside Elementor-owned data

Keep as a research candidate only.

**Higher governance threshold:** Candidate B **mutates another plugin’s persistence model**.

It may receive a GO recommendation **only if** evidence proves:

- Elementor safely preserves the metadata;
- copy/duplicate behaviour is controllable;
- import/export behaviour is understood;
- visitor-visible Elementor behaviour is unchanged;
- Elementor updates remain compatible;
- the future ADR **explicitly accepts** the ownership exception.

**Do not approve Candidate B merely because it resembles Gutenberg UUID handling.**

**Spike safety:** Do **not** persist AIML metadata into real site content during the spike. Only **disposable fixtures** with **explicit cleanup** may be used.

### Candidate C — Structural path

Identity from ancestry/path + widget type + field (+ nested indices).

Evaluate instability under:

- reorder;
- insertion;
- deletion;
- nesting changes;
- repeated siblings;
- copy/paste;
- responsive data;
- value-level ambiguity.

### Candidate D — Hybrid identity

Evaluate native identity plus owner scope, field key, nested-item identity, and source hash used **only** for stale detection (never as identity).

Evaluate whether this avoids persistence mutation while remaining stable and value-granular enough.

### Candidate E — Adapter-required or unsupported

Use for values without a safe general identity model. Require a widget-specific adapter or explicit third-party integration contract. This may be the correct outcome for some widgets.

---

## 8. Field and widget classification taxonomy

Classify at minimum:

- plain text  
- rich text  
- headings  
- buttons  
- labels  
- URLs  
- image captions and alt text  
- repeater fields  
- nested repeaters  
- tabs and accordions  
- HTML controls  
- shortcode fields  
- dynamic tags  
- query-generated values  
- global widgets  
- saved templates  
- Theme Builder content  
- third-party widgets  

For each category record:

- ownership scope;
- value-level identity feasibility;
- extraction feasibility;
- render-overlay feasibility;
- stale/hash behaviour;
- adapter requirement;
- classification under §2.4 (exactly one of):
  - **directly supportable**;
  - **supportable through adapter**;
  - **unsupported**;
- if **unsupported**: explicit reason from the §2.4 taxonomy;
- evidence confidence under §14.1.

Categories that would previously have been labelled “separate ADR required” are recorded as **unsupported** (or adapter-only) with reason **architectural contract violation** or another matching §2.4 reason—never as silently supportable.

Aggregate outputs:

- allow-oriented taxonomy (supportable / adapter); and  
- a **deterministic deny-list** of unsupported widgets/controls with reasons.

Do **not** promise widget coverage in this spike. Do **not** weaken source-fallback for deny-listed items.

---

## 9. Rendering research

Research Elementor-supported integration points only.

Evaluate:

- document-data filters;
- widget/settings filters;
- frontend element hooks;
- widget render hooks;
- template hooks;
- dynamic-tag integration;
- caching;
- CSS generation;
- language/request isolation;
- batched Store lookups.

**Reject** post-render HTML string replacement as the primary architecture.

Require evidence that:

- Elementor source remains unchanged;
- overlays are request/language scoped;
- fallback to source is deterministic;
- editor / wp-admin behaviour remains separate;
- cache language leakage is prevented;
- unsupported values remain source;
- performance is operationally viable.

---

## 10. Template and reuse ownership

Research:

- saved templates;
- Theme Builder templates;
- global widgets;
- referenced reusable content;
- copied template content;
- content inserted into multiple documents.

Determine whether translation ownership belongs to:

- shared definition;
- consuming document;
- copied independent document;
- unsupported ambiguous reference.

Do **not** silently duplicate or share translations across unrelated documents.

Produce the **ownership decision matrix** required by §2.2.

---

## 11. Copy, duplicate, and import matrix

Define repeatable experiments for:

- edit;
- reorder;
- move between containers;
- duplicate widget;
- duplicate container;
- same-document copy/paste;
- cross-document copy/paste;
- duplicate page;
- save as template;
- insert template;
- edit referenced template;
- autosave;
- revision restore;
- import/export;
- migration/regeneration.

For each experiment record:

- native ID before/after;
- candidate identity before/after;
- owner scope;
- field/nested-item identity;
- source hash;
- expected translation retention;
- expected reset/copy behaviour;
- ambiguity.

---

## 12. Version matrix

ER0 establishes verified versions.

The research matrix should distinguish:

- current Biopentra environment;
- at least one recent earlier Elementor version where practical;
- current WordPress / PHP;
- Elementor Pro;
- theme integration;
- representative third-party widgets.

Do **not** infer universal support from one environment.

---

## 13. Prototype containment

### Allowed paths (research branch only)

- `research/ar1-elementor-identity/`
- `acceptance/ar1-elementor/`

### Containment contracts

- Excluded from production Release ZIPs  
- No `Plugin.php` registration  
- No normal runtime bootstrap  
- Hooks loaded explicitly by research scripts only  
- No schema migration  
- No public REST route  
- No production admin UI  
- No production namespace promotion  
- Fixtures contain no secrets or unrelated customer data  
- Exported Elementor payloads are sanitized  
- Fixture provenance and Elementor version are recorded  
- Experiments use dedicated dev-only content  
- Deleting research directories leaves runtime behaviour unchanged  

**ER7** must include a **containment audit**.

Do not place experimental code into production service namespaces unless a later conversion is explicitly approved after ADR acceptance.

---

## 14. Evidence requirements

Reserve, but do **not** create during planning:

`docs/plans/AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md`

Require evidence for:

1. Native Elementor ID stability  
2. Translation-unit granularity  
3. Ownership scope  
4. Copy / duplicate behaviour  
5. Cross-document safety  
6. Templates and reuse  
7. Extraction feasibility  
8. Rendering-hook viability (no HTML scraping)  
9. Fallback safety  
10. Cache / language leakage  
11. Store compatibility  
12. Performance  
13. Version compatibility  
14. Third-party widget limits  
15. Unsupported-value policy and **deterministic deny-list**  
16. Prototype containment  
17. Evidence-confidence labels on candidates and major recommendations  
18. Recommended first implementation surface (GO / CONDITIONAL GO only; advisory)

### 14.1 Evidence-confidence model

The spike answers unknown architectural questions. Facts and hypotheses must not be conflated.

For every identity candidate and every major recommendation, classify each material claim as exactly one of:

| Confidence | Meaning |
|---|---|
| **Proven by experiment** | Repeatable experiment in the research log demonstrates the claim |
| **Supported by evidence** | Strong observational or documented Elementor/platform evidence without a full controlled experiment |
| **Inferred** | Reasonable conclusion from related evidence; not yet directly demonstrated |
| **Assumption requiring validation** | Working premise; must not enter the future ADR as settled fact |

The final recommendation must clearly distinguish **verified findings** from **architectural judgement**.

The future ADR must inherit **only evidence-backed conclusions** (proven by experiment or supported by evidence). Inferences and assumptions may inform A.2 planning discussion but must be called out for validation and must not be frozen as ADR norms until upgraded.

---

## 15. Decision criteria

### GO

At least one identity model is deterministic at **individual-value** level, safe across edits/copy/templates, renderable through supported Elementor integration points, operationally viable, and compatible with frozen v1 contracts.

Additionally:

- ownership scopes are decided and collision-safe;
- source-hash stale detection works (hash ≠ identity);
- no Store/TM/Review/Jobs redesign is required;
- Candidate B, if chosen, meets §7 governance bar;
- a **deterministic deny-list** exists with §2.4 reasons;
- major claims carry §14.1 confidence labels;
- a **recommended first implementation surface** is recorded (advisory only).

### CONDITIONAL GO

Only bounded widget/control categories are safe, with explicit adapter allowlists, a deterministic deny-list for everything else, and deterministic source fallback for unsupported values.

Ownership matrix must still cover templates/globals; complex/dynamic widgets remain unsupported and fail safely.

Major claims carry §14.1 confidence labels. A **recommended first implementation surface** (smallest safe subset) is recorded (advisory only).

### NO-GO

Identity depends on unstable paths, persistent safe identity is unavailable, HTML scraping is required, full Elementor documents must be duplicated by language, ownership/collision safety is unresolved, or frozen v1 contracts must be broken.

Even under NO-GO, record the deny-list and confidence-labelled findings so future work does not re-litigate unsupported cases without new evidence.

### Recommended first implementation surface (advisory)

If the result is **GO** or **CONDITIONAL GO**, ER7 must recommend the **smallest safe subset** suitable for a future A.2 implementation plan. Identify:

- supported widget families;  
- supported control types;  
- excluded controls;  
- excluded widgets;  
- known limitations;  
- rationale (tied to evidence and §14.1 confidence).  

This recommendation is **advisory only**. It does **not** authorize implementation. It provides a clean transition into the future A.2 implementation plan after ADR acceptance.

---

## 16. ADR expectation

Do **not** create an ADR in this planning task.

**Expected later ADR:** Elementor identity and ownership model (including ownership scopes and translation-unit grammar).

Do **not** write or accept the ADR during the spike itself as a substitute for evidence.

The ADR must inherit **only** conclusions labelled **proven by experiment** or **supported by evidence** (§14.1). Deny-list reasons and the advisory first implementation surface may be cited as research outputs; they do not by themselves authorize A.2 coding.

A.2 remains blocked until ER0–ER7 complete, result is GO or CONDITIONAL GO, the required ADR is written, and the ADR is **explicitly Accepted**.

---

## 17. Work packages (ER0–ER7)

**Ordering is fixed.** Execute only on `feature/ar1-elementor-identity-spike` after the planning document is reviewed and merged. Do **not** begin ER0 on the planning branch.

### ER0 — Baseline, environment, and fixture inventory

| Field | Content |
|---|---|
| **Objective** | Capture verified environment facts and representative fixtures before experiments. |
| **Research questions** | Exact Elementor/Pro slugs and versions? Feature flags? WP/PHP? Theme/integration context? Which pages/templates/widgets represent Biopentra? What storage shapes appear? |
| **Fixtures** | Dev-only Elementor pages/templates; sanitized exports; provenance recorded |
| **Tools / prototypes** | Inspection scripts under research paths only |
| **Evidence** | Version table; fixture inventory; create research log |
| **Validation** | Versions recorded before any stability experiment |
| **Stop conditions** | Experiments started without version evidence; customer PII/secrets in fixtures |
| **Commit boundary** | `docs(elementor): record AR1 ER0 baseline inventory` / research tooling commits on research branch |

### ER1 — Native Elementor identity stability

| Field | Content |
|---|---|
| **Objective** | Test Elementor IDs across edits, reorder, duplicate, copy, revisions, imports. |
| **Research questions** | Do IDs survive normal edits? What happens on duplicate/copy? Cross-document collisions? |
| **Fixtures** | Controlled Elementor documents from ER0 inventory |
| **Tools / prototypes** | Identity stability probes under `research/ar1-elementor-identity/` |
| **Evidence** | Before/after ID matrices; collision cases |
| **Validation** | Repeatable experiment protocol documented |
| **Stop conditions** | Uncontrolled mutation of production / real site content |
| **Commit boundary** | Research evidence commits on research branch |

### ER2 — Alternative and hybrid identity models

| Field | Content |
|---|---|
| **Objective** | Evaluate Candidates A–E including ownership scopes and value granularity. |
| **Research questions** | Which model is deterministic at field/nested-item level? Does B meet governance bar? What must each candidate deny? |
| **Fixtures** | Disposable fixtures only for Candidate B; explicit cleanup |
| **Tools / prototypes** | Candidate scoring harnesses; no production bootstrap |
| **Evidence** | Candidate scorecards against §7 assessment axes; §2.4 classifications; §14.1 confidence labels |
| **Validation** | Widget-level-only models rejected for GO |
| **Stop conditions** | Persistent AIML metadata written to real site content |
| **Commit boundary** | Research evidence commits |

### ER3 — Widget/control extraction and translation-unit taxonomy

| Field | Content |
|---|---|
| **Objective** | Classify core widget fields; map translation-unit keys; ownership implications; build allow and deny surfaces. |
| **Research questions** | Which categories are directly supportable vs adapter vs unsupported? What §2.4 reason applies to each deny? |
| **Fixtures** | Representative widgets covering §8 taxonomy |
| **Tools / prototypes** | Extraction inspection scripts (research paths only) |
| **Evidence** | Field/widget taxonomy table with §2.4 verdicts; deterministic deny-list with reasons; §14.1 confidence labels |
| **Validation** | No promised production widget coverage; every unsupported item has an explicit reason |
| **Stop conditions** | Promising production widget coverage as if shipped |
| **Commit boundary** | Research evidence commits |

### ER4 — Rendering integration research

| Field | Content |
|---|---|
| **Objective** | Test supported Elementor request-time injection points; reject HTML scraping. |
| **Research questions** | Which hooks preserve source, isolate language, and allow deterministic fallback? |
| **Fixtures** | Dev-only documents; no editor/admin translation |
| **Tools / prototypes** | Non-production render-hook experiments loaded only by research scripts |
| **Evidence** | Hook viability matrix; cache/language isolation notes |
| **Validation** | Post-render HTML replacement rejected as primary architecture |
| **Stop conditions** | Prototype registered via `Plugin.php` / normal bootstrap |
| **Commit boundary** | Research evidence commits |

### ER5 — Template and reusable-content ownership

| Field | Content |
|---|---|
| **Objective** | Resolve saved templates, Theme Builder, globals; produce ownership decision matrix. |
| **Research questions** | Definition-owned vs consuming-document-owned vs unsupported? |
| **Fixtures** | Templates referenced and copied into multiple documents |
| **Tools / prototypes** | Ownership-matrix worksheets; collision probes |
| **Evidence** | Final ownership decision matrix |
| **Validation** | No silent cross-document translation sharing |
| **Stop conditions** | Silent sharing proposed as acceptable |
| **Commit boundary** | Research evidence commits |

### ER6 — Compatibility, cache safety, and performance

| Field | Content |
|---|---|
| **Objective** | Version behaviour, Store lookup patterns, render overhead, cache/language leakage. |
| **Research questions** | Is performance operationally viable? Does cache leak language? |
| **Fixtures** | Multi-language request scenarios on dedicated content |
| **Tools / prototypes** | Performance / cache probes under research paths |
| **Evidence** | Version matrix; performance notes; leakage assessment |
| **Validation** | No claim of universal support from one environment |
| **Stop conditions** | Claiming universal support from one version |
| **Commit boundary** | Research evidence commits |

### ER7 — Evidence synthesis, containment audit, and recommendation

| Field | Content |
|---|---|
| **Objective** | GO / CONDITIONAL GO / NO-GO; deny-list; confidence-labelled findings; advisory first implementation surface (if GO/CONDITIONAL GO); ADR recommendation; limitations; A.2 readiness; containment audit. |
| **Research questions** | Is A.2 ready to plan under an Accepted ADR? What is the smallest safe first surface? Are prototypes contained? What must never be translated? |
| **Fixtures** | Full evidence set from ER0–ER6 |
| **Tools / prototypes** | Containment checklist; ZIP exclusion verification |
| **Evidence** | Recommendation memo; deterministic deny-list; §14.1 confidence summary; recommended first implementation surface (advisory); containment audit PASS/FAIL |
| **Validation** | All acceptance criteria checked; research dirs deletable / ZIP-excluded; verified findings separated from judgement |
| **Stop conditions** | Recommending A.2 without ADR gate; treating advisory first surface as implementation authorization |
| **Commit boundary** | `docs(elementor): conclude AR1 identity spike recommendation` |

---

## 18. Acceptance criteria

The spike (**research execution**) is accepted when all of the following hold:

1. Remains research-only; no production registration.  
2. Research log exists and records ER0 version facts before experiments.  
3. Representative Elementor fixtures (sanitized, provenance recorded).  
4. Native ID stability evidence.  
5. Duplicate/copy evidence.  
6. Cross-document identity safety evidence.  
7. Template **ownership decision matrix** complete.  
8. **Translation-unit granularity** defined for approved candidate(s).  
9. Field/widget taxonomy with §2.4 verdicts.  
10. Rendering-hook viability without HTML scraping.  
11. Source fallback demonstrated for unsupported/ambiguous cases.  
12. Stale/hash semantics defined (hash ≠ identity).  
13. Store compatibility without ownership redesign.  
14. No TM/Glossary/Review/Jobs redesign required.  
15. Language leakage assessment.  
16. Caching assessment.  
17. Version matrix with verified ER0 baseline.  
18. Third-party widget policy.  
19. Performance evidence.  
20. Unsupported-content behaviour defined; **deterministic deny-list** with §2.4 reasons complete.  
21. Clear GO / CONDITIONAL GO / NO-GO.  
22. ADR recommendation recorded; ADR inheritance limited to evidence-backed conclusions (§14.1).  
23. Candidate B governance satisfied or Candidate B rejected.  
24. Prototype containment audit PASS.  
25. A.2 remains blocked pending Accepted ADR.  
26. Planning branch contains no experimental research runtime.  
27. §14.1 evidence-confidence labels applied to identity candidates and major recommendations.  
28. If GO or CONDITIONAL GO: **recommended first implementation surface** recorded (advisory only; does not authorize implementation).

**Planning-document acceptance (this milestone):** criteria above are **defined**; research has **not** begun; ER0 is **not** started; research log is **not** created; Elementor production implementation remains **blocked**.

---

## 19. Risks

- Elementor element IDs changing unexpectedly  
- Copied IDs causing collisions  
- Template ownership ambiguity  
- Value-level identity ambiguity (widget-stable, field-unstable)  
- Third-party widget variability  
- Nested repeater complexity; dynamic tags  
- Elementor cache/CSS interactions; frontend language leakage  
- Product pressure to use HTML scraping  
- Candidate B persistence mutation / update breakage  
- Maintenance cost across Elementor versions  
- Accidental promotion of research prototypes to production  
- Mixing research artifacts into the planning branch  

---

## 20. Planning vs research lifecycle

1. Plan is written on `feature/ar1-elementor-identity-spike-plan`.  
2. Plan is reviewed, frozen, and merged through normal governance.  
3. Research branch is created from updated `main`: **`feature/ar1-elementor-identity-spike`**.  
4. ER0 creates the research log.  
5. ER0–ER7 execute only on the research branch.  
6. A.2 remains blocked until the research result is GO or CONDITIONAL GO and the required ADR is explicitly Accepted.

---

## 21. Exact next step

Review and merge this planning document. Then create `feature/ar1-elementor-identity-spike` from updated `main` and begin **ER0** (version capture + fixture inventory).

Do **not** implement Elementor production support. Do **not** treat any recommended first implementation surface as authorization. A.2 remains blocked until research completes, result is GO or CONDITIONAL GO, and the required ADR is **explicitly Accepted**.

---

## Document control

| Item | Value |
|---|---|
| Canonical plan | `docs/plans/AR1_ELEMENTOR_IDENTITY_RESEARCH_SPIKE.md` |
| Roadmap milestone | A.R1 |
| Supersedes | None (first A.R1 plan) |
| Related | ADR-0001, ADR-0005, ADR-0013; Strategy F Elementor skip; P1 complete |
