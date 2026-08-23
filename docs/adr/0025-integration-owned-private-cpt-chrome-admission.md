# ADR-0025 — Integration-Owned Private CPT Chrome Admission

## Status

**Accepted** (2026-08-23) — Additive public Integration/Extension capability for site-wide visitor chrome owned by vetted integrations (M5-A).

**Decision maker:** Product Owner  
**Approval date:** 2026-08-23  
**Decision:** ADR-0025 **Accepted**  
**Reason:** Frozen plan [M5A_PRIVATE_CPT_CHROME_INTEGRATION.md](../plans/M5A_PRIVATE_CPT_CHROME_INTEGRATION.md) establishes that Integration API v1 alone cannot correctly localize site-wide chrome (announcement bars and peers) without either host-bound resolve, private Store access, or per-page duplication. A narrowly scoped, evidence-gated companion declaration + Extension-strict host-independent resolve closes the gap without reopening TSC.6 deferred slug-only CPT filters.

**Scope:** Companion interface `DeclaresChromeOwnedSurfaces`; `ChromeOwnedSurfaceDeclaration`; post-`init` declaration validation; `IntegrationAdmissionRegistry`; host-independent `p:` resolve via `VisitorTranslationResolver`; source `post_status=publish` gate; public `aiml_visitor_language()`; dual-path eligibility documentation (Extension-strict chrome vs FrontendBridge I7).

**Does not replace:** ADR-0017 (Integration API v1), ADR-0022 (Extension boundary), ADR-0020 (publication), ADR-0024 (URL/host language authority).

**Residual risks accepted:**

- Invalid declarations must be fail-closed per-surface without breaking the registry
- Operators may confuse FrontendBridge I7 with Extension chrome eligibility — dual-path docs are mandatory
- Custom post-status visitor policies remain deferred

**Implementation gate:** Closed by M5-A implementation on `main` (see closure). This ADR does **not** authorize production tag/ZIP/deploy or USA M5-B.

**Related:** ADR-0001; ADR-0017; ADR-0020; ADR-0022; ADR-0024.

---

## Context

Site-wide chrome renders independently of the queried page. Host-bound `IntegrationFrontendBridge` resolve (I7: stale may overlay when publication-eligible) is correct for page-anchored plugin output, not for chrome CPT sources. Extension `VisitorTranslationResolver` is already source-id-explicit and Extension-strict (stale → `null`) but refused non-admitted CPTs. CPT admission via public filters remains forbidden (ADR-0022 / PluginGuard).

## Decision

1. Integrations optionally implement `DeclaresChromeOwnedSurfaces` declaring private CPT + owner types + fields + `integration_units_only`.
2. AIML validates declarations only after CPTs exist (normally post-`init`). Invalid → disable **that** declaration + authorized diagnostic; continue others.
3. Chrome consumers resolve via Extension resolver + `aiml_visitor_language()`, never FrontendBridge host-bound resolve.
4. Chrome eligibility: Extension-strict + TI.7 publication + source `publish`.
5. AIML never changes CPT public/REST/rewrite flags.

## Consequences

- Additive public API; binary-compatible with existing Integration API v1 implementors.
- Recommended release train: **1.7.0** (no schema TARGET bump).
- USA M5-B (and peers) may adapt only after 1.7.0 is released and deployed under separate authorization.
