# ADR-0016 — Elementor identity and ownership model

## Status

**Accepted** (2026-08-06) — Elementor identity and ownership model frozen from A.R1.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-06  
**Decision:** ADR-0016 **Accepted**  
**Reason:** Architecture review of A.R1 research (CONDITIONAL GO) confirmed Hybrid D, ownership precedence, translation-unit granularity, deny-list, adapter graduation, Candidate B rejection, and overlay/Store/TM/Glossary/Review/Jobs/renderer invariants are consistent and evidence-backed. No architectural inconsistencies found. No production implementation was present on the research branch.

**Scope:** Elementor visitor-facing translation identity, ownership precedence, Hybrid D conceptual contract, deny-list, adapter graduation, and first-surface planning bounds — derived exclusively from A.R1 research. Unchanged from Proposed draft.

**Residual risks accepted:**

- Multi-version Elementor matrix incomplete in A.R1 (revalidate on upgrades)
- Theme Builder definition-owned overlays not exercised end-to-end in research
- First surface is intentionally narrow; product pressure must not expand without evidence
- Concrete production key grammar deferred to A.2 planning (must match Hybrid D composition)

**Implementation gate:** **Open for A.2 planning only.** This ADR does **not** authorize production implementation, extractors, adapters, renderers, schema, REST, UI, migrations, or feature flags. A.2 coding requires a separate implementation plan and delivery authorization.

**A.2 Elementor Foundation planning may now begin.** Production Elementor implementation remains blocked until A.2 is separately authorized.

**Evidence base:**

- [AR1_ELEMENTOR_IDENTITY_RESEARCH_SPIKE.md](../plans/AR1_ELEMENTOR_IDENTITY_RESEARCH_SPIKE.md)
- [AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md](../plans/AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md) (**CONDITIONAL GO**)
- [research/ar1-elementor-identity/DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md)

**Related:** ADR-0001 (overlay-not-duplication); ADR-0005 (segment-centric storage; historical `elementor:…` examples are **not** binding grammar); ADR-0007 (hash semantics); ADR-0013 (Gutenberg `b:<uuid>:<field>` — coexistence required; Elementor remains a separate identity family).

**Revalidation triggers:** Elementor major upgrades; evidence that native element IDs become unstable for ordinary edits; discovery of reference semantics that invalidate ownership precedence; proposal to embed AIML identity in Elementor persistence (Candidate B); proposal to use HTML scraping or fuzzy rematch.

---

## Context

AI Multilingual v1.0.0 translates Gutenberg leaves via persistent block identity (ADR-0013) and Store overlays (ADR-0001). Elementor-primary bodies are detected and **skipped** (`elementor_body`); visitor Elementor content is therefore untranslated.

A.R1 (Elementor Identity Research Spike) asked whether Elementor visitor-facing values can receive deterministic identities compatible with frozen Platform v1 contracts while Elementor retains ownership of canonical content. Research recommendation: **CONDITIONAL GO**.

A.R1 established:

- Native Elementor element IDs are stable across ordinary text edits and Elementor document API resave (**proven by experiment**).
- The same native IDs collide across documents (including intentional shared IDs) — **owner scope is mandatory** (**proven by experiment**).
- Structural paths break under sibling reorder — path identity is unsafe (**proven by experiment**).
- Hybrid model **D** (owner + native element ID + field + nested + optional responsive; hash ≠ identity) is the canonical conceptual model (**supported by evidence**).
- Candidate B (AIML metadata inside Elementor-owned data) preserves values on disposable fixtures but fails the governance bar for mutation of another plugin’s persistence (**supported by evidence**; GO bar not met).
- A bounded first surface and a permanent deny-list are required; adapters graduate; stability ≠ support ≠ ownership.

This ADR freezes those architectural contracts. It does **not** invent new identity models and does **not** define a production key string grammar.

---

## Decision

1. **Elementor remains the canonical owner** of Elementor document data (`_elementor_data` and related Elementor meta/settings). AI Multilingual must not annex Elementor persistence as the translation store.

2. **AI Multilingual owns translation overlays only**, keyed by deterministic identities, consistent with ADR-0001. Source Elementor content is never silently rewritten for translation storage.

3. **Preferred / canonical identity model is Hybrid D** (A.R1 Candidate D). Candidate A (native element ID) is the **element-identifier substrate** inside Hybrid D, not a complete translation-unit identity by itself.

4. **Conceptual translation-unit identity** (not a production grammar):

   ```text
   Owner Scope
     + Owner Identifier
     + Element Identifier
     + Field Identifier
     + Nested Identifier (optional)
     + Responsive Variant (optional)
   ```

5. **Owner precedence** (highest first) — see Ownership model. Silent sharing of translations across unrelated documents is forbidden.

6. **Translation-unit granularity** is mandatory: widget-level identity alone cannot receive implementation GO for a value. Multiple fields on one widget and repeated items sharing a control name must be distinguishable.

7. **Supported first implementation surface** (advisory planning bound for A.2 — not implementation authorization):

   | Widget | Controls |
   |---|---|
   | `heading` | `title` (+ responsive title variants when present) |
   | `text-editor` | `editor` |
   | `button` | `text` |

8. **Adapter architecture** follows graduation: Unsupported → Research → Adapter → Directly Supported. Adapters are not permanent; successful adapters graduate when evidence allows.

9. **Permanent deny-list** remains an architectural deliverable ([DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md)). Items leave only through evidence (and graduation). Source fallback for deny-listed / ambiguous values: **render source**.

10. **Source / stale hash is freshness only** — never identity (aligns with ADR-0007 intent). Source-language and translated values are overlay payload, not identity.

11. **No HTML scraping** as identity mechanism or primary render architecture.

12. **No fuzzy rematching** as identity mechanism.

13. **Candidate B is rejected** for Elementor support under this ADR. Embedding AIML identity into Elementor-owned data requires a **future ADR** that explicitly accepts a persistence-ownership exception **and** completes copy/import/update evidence. A.R1 probe preservation alone is insufficient.

14. **Store ownership unchanged** — Store remains the overlay system of record for translation rows.

15. **TM ownership unchanged**.

16. **Glossary ownership unchanged**.

17. **Review ownership unchanged**.

18. **Jobs ownership unchanged**.

19. **Renderer ownership unchanged** at the platform level: any future Elementor render path must be an Elementor-integration concern that injects overlays at supported Elementor integration points without redesigning Gutenberg block rendering contracts (ADR-0013).

20. **Cache must remain language-aware** — Elementor (and any AIML overlay) caches must not leak one language’s HTML/CSS into another. Language-scoped cache keys or equivalent isolation are required before production Elementor render.

21. **Gutenberg coexistence:** Elementor Hybrid D is a **separate identity family** from `b:<uuid>:<field>`. Neither family may weaken the other.

22. **Stability ≠ support ≠ ownership:** A value may have a stable native element ID and still be unsupported or ownership-unresolved; unresolved ownership → leave source.

---

## Alternatives considered

| Alternative | Outcome |
|---|---|
| Do nothing (Elementor remains skipped forever) | Rejected as product strategy; A.R1 CONDITIONAL GO permits bounded planning |
| Structural path identity (Candidate C) | **Rejected** — reorder changes paths while IDs remain |
| Native element ID alone (Candidate A without owner/field layers) | **Rejected** as complete model — cross-document collisions; insufficient value granularity |
| AIML ID embedded in Elementor settings (Candidate B) | **Rejected** under this ADR — persistence mutation / governance |
| Duplicate full Elementor documents per language | **Rejected** — violates overlay-not-duplication (ADR-0001) |
| HTML/DOM scrape + fuzzy rematch | **Rejected** — charter and Platform v1 invariants |
| Universal Elementor widget coverage in A.2 | **Rejected** — CONDITIONAL GO requires bounded first surface + deny-list |

---

## Candidate comparison

| Candidate | A.R1 verdict | ADR disposition |
|---|---|---|
| **A** Native Elementor element ID | Stable within document; collisions across documents | Substrate for Hybrid D element layer only |
| **B** AIML metadata in Elementor data | Probe preserved on disposable fixtures; GO bar not met | **Rejected** unless future exception ADR |
| **C** Structural path | Failed reorder stability | **Rejected** |
| **D** Hybrid (owner + element + field + nested + responsive; hash≠identity) | Preferred / canonical conceptual model | **Accepted as conceptual contract** (this ADR, when Accepted) |
| **E** Adapter / unsupported | Correct for complex / opaque cases | **Accepted** as support lifecycle + deny-list |

---

## Ownership model

### Scopes

| Scope | Meaning |
|---|---|
| **Document-owned** | Overlay namespace bound to the WordPress post/document that owns the Elementor tree |
| **Shared-definition-owned** | Overlay namespace bound to a referenced library / Theme Builder / global definition |
| **Consuming-document-owned** | After copy/paste/insert-as-copy, overlays bind to the consuming document independently |
| **Explicitly unsupported** | Ownership ambiguous — leave source; never invent silent sharing |

### Precedence (highest first)

1. Unsupported / ambiguous ownership → do not translate.  
2. Shared-definition-owned when a **stable reference** exists (`template_id` or equivalent).  
3. Document-owned for ordinary non-reference trees.  
4. Consuming-document-owned after **copy** (independent tree; do not keep sharing definition overlays).

### Forbidden

- Silently duplicating shared-definition translations onto unrelated consuming documents.  
- Keying overlays by native element ID without owner scope.  
- Treating a copied tree as definition-owned without a live reference.  
- Sharing one overlay identity across posts that reuse the same element ID.

---

## Identity model

Hybrid **D** is the canonical conceptual identity model for Elementor translation units.

- **Owner Scope** + **Owner Identifier** are mandatory.  
- **Element Identifier** = native Elementor element `id` when present and stable.  
- **Field Identifier** = widget control/setting key for one translatable value.  
- **Nested Identifier** = repeater/tab/accordion item identity (e.g. `_id`) when applicable.  
- **Responsive Variant** = device-specific variant when Elementor stores a distinct value.

This ADR freezes the **composition and rules**, not a concrete production key serialization. A.2 planning may propose a concrete grammar consistent with this composition; shipping that grammar requires normal implementation governance after this ADR is Accepted.

---

## Translation-unit model

One Store overlay row corresponds to **one translatable value**, not one widget.

Must distinguish:

- multiple fields in the same widget;  
- repeated rows using the same field name;  
- nested repeater items;  
- tabs/accordion entries;  
- responsive variants;  
- template references (via ownership scope, not by ignoring them).

Widget-level-only identity is insufficient for implementation of a value.

Payload rules:

- source hash → freshness / stale detection only;  
- source and translated text → overlay payload, not identity.

Uncertainty (identity, ownership, or support) → **source fallback**.

---

## Adapter strategy

```text
Unsupported → Research → Adapter → Directly Supported
```

| Stage | Rule |
|---|---|
| Unsupported | On deny-list; source fallback |
| Research | Investigation only; not visitor-translated in production |
| Adapter | Explicit widget/control adapter contract required |
| Directly supported | Covered by general Hybrid D extraction/render without a special adapter |

Adapters are **not permanent**. Graduation to directly supported requires evidence that identity, ownership, and render overlay are safe under the general model.

A.2 first surface targets **directly supportable** controls listed in Decision §7. Repeater/image and similar families remain adapter-deferred or denied until graduated.

---

## Deny-list

The permanent deny-list is an architectural contract. Canonical research artifact: [DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md).

Grouped reasons (non-exhaustive summary):

| Reason | Examples |
|---|---|
| Ownership ambiguity | Globals/templates without stable reference; ambiguous chrome widgets |
| Unstable identity | Field-less widget-only keys; structural paths |
| Dynamic runtime | `__dynamic__` tags; query-generated loop-grid cells |
| Third-party persistence | WooCommerce Elementor widgets; Fluent Form widget; unresearched third-party |
| Unsupported Elementor behavior | `html`, `shortcode` |
| Architectural contract | Candidate B under this ADR; HTML scraping / fuzzy rematch |
| Cache / security-privacy gates | Language-unaware cache forbidden; no secrets/PII in fixtures or overlays |

Future milestones **remove** entries through evidence — they do not replace the deny-list with an unstructured allow-only policy.

---

## Consequences

### Positive

- Clear architectural path from A.R1 CONDITIONAL GO to A.2 **planning**.  
- Collision-safe ownership rules before any extractor/renderer is written.  
- Deny-list and first surface bound scope and prevent scrape/fuzzy shortcuts.  
- Preserves Gutenberg, Store, TM, Glossary, Review, Jobs, and overlay invariants.

### Negative / costs

- Elementor coverage remains unavailable until ADR Accepted **and** A.2 implementation completes under separate authorization.  
- Many widgets stay denied or adapter-deferred; product pressure must not expand first surface without evidence.  
- Multi-version Elementor matrix incomplete in A.R1 — upgrades require revalidation.  
- Concrete production key grammar still to be designed in A.2 planning (must match Hybrid D composition).

### Neutral

- Research prototypes under `research/ar1-elementor-identity/` and `acceptance/ar1-elementor/` remain non-production and ZIP-excluded.

---

## Risks

| Risk | Mitigation |
|---|---|
| Cross-document native ID collisions | Mandatory owner scope |
| Silent share of definition translations | Ownership precedence + anti-patterns |
| Cache language leakage | Language-aware cache required before production render |
| Scope creep beyond first surface | Deny-list + advisory first surface freeze |
| Candidate B revival without governance | Explicit rejection; future ADR required |
| Path identity reintroduction | Candidate C rejected |
| Weakening Gutenberg contracts | Separate identity family; ADR-0013 untouched |
| Premature A.2 coding | Acceptance gate below |

---

## Future work

1. **Accept this ADR** — **done** (2026-08-06).  
2. **Plan A.2 Elementor Foundation** within Decision §7 first surface, Hybrid D, ownership precedence, deny-list, and language-aware cache — **planning only** until separately authorized.  
3. Define production key serialization consistent with Hybrid D (implementation plan / later commits — not this ADR).  
4. Design Elementor-supported overlay injection points (e.g. document/widget filters researched in A.R1) without HTML scraping.  
5. Adapter research for deferred families (`accordion`/`toggle`, image alt/caption) under graduation rules.  
6. Revalidate on Elementor version upgrades.  
7. Theme Builder definition-owned end-to-end overlay validation during A.2 design/tests.

**This ADR authorizes planning of A.2 only. It does not authorize implementation.**

Out of scope until later ADRs/milestones: Candidate B persistence exception; nested Gutenberg identity (A.R2); WooCommerce Elementor bridges; broad widget coverage; production Release ZIP inclusion of research paths.

---

## Acceptance gate

| Gate | Meaning |
|---|---|
| **Proposed** | Architecture drafted from A.R1; implementation **blocked**; A.2 planning not yet authorized |
| **Accepted (current)** | Product Owner accepted this ADR (2026-08-06); **A.2 planning may begin**; production Elementor implementation remains unauthorized until A.2 plan + normal delivery gates say otherwise |
| **Rejected / superseded** | Elementor support returns to research or NO-GO; do not implement Hybrid D production paths |

**A.2 planning may begin.** Production Elementor implementation remains blocked.

While Accepted for planning:

- no Elementor production extractors, adapters, renderers, schema, REST, UI, migrations, or feature flags until A.2 is separately authorized;  
- no weakening of Store / TM / Glossary / Review / Jobs / Gutenberg renderer contracts;  
- research artifacts remain non-runtime.

---

## Document control

| Item | Value |
|---|---|
| ADR | 0016 |
| Title | Elementor identity and ownership model |
| Status | Accepted |
| Approval date | 2026-08-06 |
| Supersedes | None |
| Amends | Clarifies ADR-0013 open question on Elementor-primary coverage without changing Gutenberg acceptance |
| Evidence | A.R1 CONDITIONAL GO research log |
