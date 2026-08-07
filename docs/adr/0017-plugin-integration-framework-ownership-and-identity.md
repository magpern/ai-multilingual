# ADR-0017 — Plugin Integration Framework, Ownership, and Identity

## Status

**Accepted** (2026-08-07) — Plugin Integration Framework ownership vocabulary, Integration API v1, and namespaced `p:` identity family frozen for A.1.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-07  
**Decision:** ADR-0017 **Accepted**  
**Reason:** Architecture review of the frozen A.1 implementation plan confirmed consistency with ADR-0001 (overlay), ADR-0005/0007 (segment + hash), ADR-0013 (`b:`), and ADR-0016 (foreign-persistence ownership principles). No architectural contradiction found. No production implementation exists. Store `segment_key` remains opaque `VARCHAR(191)`; Migrator TARGET remains 6. A dedicated ADR is required because A.1 introduces a long-lived third-party extension surface and a new identity family not frozen by prior ADRs.

**Scope:** General plugin-integration ownership vocabulary; visitor-facing Integration API v1 responsibilities; code-owned registration; namespaced `p:` identity family and serializer safety invariants; ownership classifications; extraction/output contracts; lifecycle/compatibility; platform reuse; security; reference-fixture proof strategy; admission governance. Derived from [A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md](../plans/A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md). Exact production class names and final delimiter arity details remain implementation concerns within these invariants.

**Residual risks accepted:**

- Realistic integration identities may stress the `VARCHAR(191)` bound — serializer must reject invalid keys; schema bump is a stop condition, not an A.1 escape hatch
- Public Integration API v1 creates long-term versioning obligations
- Some plugin output will remain permanently unsupported (opaque/unstable identity)
- Compatibility maintenance burden shifts to each integration boundary
- Future product integrations (WooCommerce, forms, etc.) may need additional surface-specific ADRs without reopening this framework ADR

**Implementation gate:** **Open for A.1 implementation (A10–A18)** on branch `feature/a1-plugin-integration-framework` per the frozen A.1 plan. This ADR does **not** authorize WooCommerce, forms, Elementor/Gutenberg expansion, email interception, schema changes, new REST/CLI, or unrelated Program A milestones.

**Evidence / plan base:**

- [A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md](../plans/A1_PLUGIN_INTEGRATION_FRAMEWORK_IMPLEMENTATION_PLAN.md)
- [POST_V1_PLATFORM_ROADMAP.md](../plans/POST_V1_PLATFORM_ROADMAP.md) (A.1; F2 after A.1 + E.0)

**Related:** ADR-0001 (overlay-not-duplication); ADR-0005 (segment-centric storage); ADR-0007 (hash ≠ identity); ADR-0013 (Gutenberg `b:` — coexistence required); ADR-0016 (Elementor ownership principles generalized here for arbitrary plugins).

**Revalidation triggers:** Proposal to scrape HTML as primary integration mechanism; proposal to mutate foreign persistence for AIML identity; proposal to globally intercept email/PDF/SMS/webhooks; Store key-length insufficiency requiring schema redesign; proposal to let integrations emit unvalidated free-form keys; proposal to introduce a second translation pipeline.

---

## Context

AI Multilingual v1.0.0 translates visitor-facing content through Store overlays (ADR-0001) with deterministic identity families for Gutenberg (`b:`, ADR-0013) and Elementor (`e:`, ADR-0016). Platform principles require deterministic identity and non-ownership of foreign persistence.

Program A milestone **A.1 Plugin Integration Framework** defines how *other* WordPress plugins expose visitor-facing translatable values without AIML annexing their persistence and without creating plugin-specific translation architectures.

Existing ADRs are **necessary but insufficient** alone:

| Existing ADR | Covers | Does not freeze |
|---|---|---|
| 0001 | Overlay storage | How third parties register extract/overlay surfaces |
| 0005 / 0007 | Segment Store; hash freshness | Public extension identity family for plugins |
| 0013 | Gutenberg `b:` | Other plugin families |
| 0016 | Elementor ownership / Hybrid D | General multi-plugin Integration API |

A.1 therefore introduces three long-lived contracts that require this ADR:

1. third-party plugin integration **ownership vocabulary**;
2. public/extension **Integration API v1**;
3. namespaced plugin-integration identity family **`p:`** and serializer safety rules.

---

## Decision

### 1. Ownership

The **external plugin remains the canonical owner** of its source data and lifecycle.

AIML must never require another plugin to surrender persistence ownership.

AIML may persist only its own platform concerns:

- translation overlays;
- Store records;
- TM;
- Glossary;
- Review state;
- Jobs orchestration;
- bounded diagnostics.

AIML must **not**:

- duplicate whole foreign records as a shadow persistence model;
- rewrite foreign records to establish AIML identity;
- delete foreign records;
- alter foreign lifecycle semantics;
- infer ownership from rendered output.

**Ambiguous ownership → unsupported / source fallback.**

### 2. Visitor-facing boundary

Integration API v1 is for **visitor/customer-facing** translation surfaces only.

It is **not** for: wp-admin; plugin settings; operator interfaces; internal diagnostics; arbitrary database strings.

Non-page outputs (email, PDF, feed, SMS, notification, webhook) may participate **only** when the producing plugin explicitly exposes deterministic translation surfaces. AIML must **not** globally intercept these channels.

### 3. Integration API v1

AIML defines a versioned extension contract: **Integration API v1**.

**Stable responsibilities** (conceptual — not every final class name):

- registration;
- compatibility / lifecycle;
- deterministic identity components;
- source extraction;
- visitor-output overlay / application;
- surface metadata;
- diagnostics / admission information.

**Extension / public contract:** intended for integration implementations.

**Internal AIML contract:** repositories, internal DTOs, orchestration internals, metrics internals — **not** automatically public API.

Breaking Integration API v1 requires deprecation policy, version bump, migration guidance, and normal architecture governance.

### 4. Registration model

- Central deterministic registry;
- code-owned typed integration objects;
- immutable integration ID (lowercase safe token);
- duplicate integration ID rejected;
- missing plugin does not affect AIML core;
- integration-specific logic does **not** enter generic Store / translation services.

A WordPress hook may expose code-based registration. Forbidden: database-defined PHP callbacks; arbitrary serialized callbacks; untrusted runtime executable registration.

### 5. Identity family `p:`

A dedicated integration key family is reserved, distinct from Gutenberg `b:` and Elementor `e:`:

```text
p:
```

The **serializer is owned by the AIML framework**. Integrations provide **typed identity components**, not arbitrary final keys.

Conceptual identity:

```text
p:<integration_id>:<owner_type>:<owner_id>:<field>[:<nested_id>...]
```

Delimiter arity and escaping are finalized in A.1 implementation within these invariants (A12). The ADR freezes the family and rules, not a claim that every conceivable nested arity is already production-tested.

**Invariants:**

- integration ID immutable lowercase safe token;
- owner type explicit;
- owner ID deterministic;
- field explicit;
- nested IDs deterministic when present;
- source text never part of identity;
- source hash = freshness only (ADR-0007);
- no fuzzy identity;
- no unstable structural-path identity;
- namespace collision with `b:` / `e:` forbidden;
- identity not coupled to translated text.

### 6. Serializer safety

Store `segment_key` remains bounded by the existing storage contract (`VARCHAR(191)`). Schema must **not** change merely to accommodate poorly bounded keys. Migrator TARGET remains **6** for A.1.

Serializer requirements:

- deterministic encoding;
- explicit token validation;
- component-length validation;
- total key-length validation (≤ existing Store bound);
- **no silent truncation**;
- deterministic failure on invalid identity;
- collision tests;
- explicit Unicode policy;
- versioned parsing behavior if the serialized form is public.

If realistic identities cannot fit: **STOP** and return to architecture review — do not invent unsafe hashing to hide bad design unless a future ADR explicitly chooses that representation.

### 7. Ownership classifications

Frozen vocabulary:

- `record-owned`
- `document-owned`
- `shared-definition-owned`
- `runtime/dynamic`
- `unsupported/ambiguous`

Each surface declares: canonical owner; owner ID; source field; extraction safety; overlay safety; copy semantics; deletion semantics.

Shared-definition ownership requires a stable external definition ID. Runtime/dynamic content requires deterministic identity **and** safe output interception to be eligible; otherwise unsupported.

### 8. Extraction / output contract

**Extraction:** explicit field allowlist; deterministic typed source values; source hash; bounded execution; no arbitrary recursive string scanning; no wp-admin extraction.

**Output application — allowed:** official plugin hooks; documented filters; view-model/data APIs; explicit render/template callbacks.

**Forbidden:** generic post-render HTML replacement; DOM scraping; unscoped output buffering; persistent translated mutation of source records.

**Local failure:** failing unit → source value; remaining units continue. One integration must never take down the site.

### 9. Dynamic / opaque content

| Class | Eligibility |
|---|---|
| Deterministic persisted source | Candidate for admission |
| Deterministic generated source | Evidence required |
| Runtime dynamic with stable identity | Integration-specific evidence required |
| Runtime dynamic without stable identity | Unsupported |
| Opaque rendered output | Unsupported |

Framework existence does **not** imply automatic translation of arbitrary plugin output.

### 10. Lifecycle / compatibility

Lifecycle states include: plugin missing; inactive; active/compatible; unsupported version; missing required hook; integration disabled; degraded; plugin removed; reactivated.

AIML core remains healthy in every state. Store history may remain when an integration is disabled/removed. Translations must **not** render when the integration cannot safely resolve the foreign owner/output. Reactivation resumes only after compatibility passes. Compatibility logic belongs inside the integration boundary.

### 11. Platform reuse

All integration units flow through the existing platform:

```text
Store → Workspace → Suggestions → Review → TM → Glossary → Jobs
```

No plugin-specific translation pipeline, Review system, TM, or Jobs pipeline. No unrestricted crawler. Future dataset enumeration remains bounded and integration-specific.

### 12. Security

- Code-owned integration registration;
- existing translation capabilities reused where possible;
- sanitized source handling;
- sanitized translated output appropriate to the foreign field;
- no secrets in diagnostics;
- no arbitrary executable callback data;
- no wp-admin translation;
- no raw foreign payload exposure through public APIs unless explicitly designed.

### 13. Reference integration

A.1 proves Integration API v1 using a **test/reference fixture integration only**.

It must **not** be WooCommerce, Fluent Forms, Elementor, or a production merchant feature.

It must demonstrate: registration; compatibility; identity; extraction; Store; Workspace; Review/TM/Glossary compatibility; Jobs compatibility where applicable; visitor overlay; local failure; disabled/reactivated lifecycle; diagnostics.

### 14. Admission governance

Future production integrations require evidence-backed admission records covering: integration ID; package/plugin; ownership; supported versions; surfaces; identity/extraction/output-hook evidence; sanitization; lifecycle; copy/delete; cache; performance; output/browser validation; limitations.

**Dispositions:** Supported | Experimental | Deferred | Unsupported.

### 15. Alternatives rejected

| Alt | Rejected because |
|---|---|
| **A.** Integrations construct arbitrary Store keys | Weak collision/validation governance |
| **B.** Duplicate foreign data into AIML-owned tables | Foreign persistence ownership violation / second source of truth |
| **C.** Generic HTML scraping | Unstable identity and unsafe application |
| **D.** Fuzzy rematching | Not deterministic identity |
| **E.** Plugin-specific pipelines | Architecture fragmentation |
| **F.** Globally intercept emails/output channels | Ownership and semantic ambiguity |

---

## Consequences

### Positive

- Consistent future plugin integrations (A.8 and beyond);
- No plugin-specific translation architecture;
- Stable identity discipline alongside `b:` / `e:`;
- Clear ownership boundaries;
- Safer upgrades and fail-safe lifecycle;
- Reusable admission process.

### Costs / obligations

- Integration authors must explicitly model ownership;
- Some plugin output remains unsupported;
- Compatibility maintenance required per integration;
- Public API creates long-term versioning obligations;
- Serializer length constraints must be respected (191-bound).

---

## Implementation gate

ADR-0017 is the hard gate for A.1 production work.

**After acceptance:**

- A10 baseline may proceed;
- A11–A18 may implement the frozen A.1 plan.

No new architecture review is required unless implementation discovers a plan stop condition (schema redesign, scrape, foreign mutation, key truncation, second pipeline, etc.).

**A.1 Plugin Integration Framework implementation is authorized.** Product integrations beyond the reference fixture remain out of A.1 scope.
