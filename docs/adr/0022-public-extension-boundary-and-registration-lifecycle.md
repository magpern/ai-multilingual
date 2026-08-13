# ADR-0022 — Public Extension Boundary and Registration Lifecycle

## Status

**Accepted** (2026-08-13) — Public Extension API v1, root extension ownership model, registration phase/sealing, resolver source identity, public meta v1, narrow public block contract, and diagnostics scope frozen for TSC.6.

**Decision maker:** Product Owner  
**Approval date:** 2026-08-13  
**Decision:** ADR-0022 **Accepted**  
**Reason:** External review of the frozen TSC.6 plan confirmed that TSC.6 introduces long-lived third-party contracts beyond Integration API v1 (ADR-0017): root extension ownership, registry sealing, public meta/block registration, and a read-only visitor resolver. No architectural contradiction with ADR-0001 (overlay), ADR-0005/0007 (segment + hash), ADR-0013 (`b:`), ADR-0016 (Elementor ownership), ADR-0020 (publication), or ADR-0021 (taxonomy admission). Store `segment_key` remains opaque `VARCHAR(191)`; Migrator TARGET remains 7; STATE A holds — no schema change.

**Scope:** Public vs internal API boundary; Extension API v1 registration lifecycle; root extension ownership (`ExtensionRegistrar`, `ExtensionManifest`, `RegisteredExtension`); public meta v1; narrow public block adapter (`ExtensionBlockAdapter`); visitor resolver identity DTOs; invalidation helper; activation ACTIVE/INACTIVE/REMOVED; failure-isolation guarantee boundaries; WP-CLI diagnostics; semver/deprecation policy; explicit list of unsupported internal APIs. Derived from [TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md](../plans/TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md).

**Does not replace:** ADR-0017 (Integration API v1 / `p:` grammar), ADR-0013 (Gutenberg `b:`), ADR-0016 (Elementor `e:`), ADR-0021 (taxonomy admission).

**Residual risks accepted:**

- Public Extension API v1 creates long-term semver obligations for `AIMultilingual\Extension\*` contracts
- Third-party extensions may register overlapping namespaces if collision guards regress — fail-closed guards are mandatory
- Uninstall limitation: AIML cannot retain INACTIVE semantics without extension code registering a stub
- CPT/taxonomy public admission deferred — slug-only filters would bypass `SurfaceCapability` facts
- Elementor public registration deferred — third-party Elementor fields remain Integration-only or internal until a future ADR

**Implementation gate:** **Open for TSC.6 implementation** on branch `feature/tsc6-public-extension-seo-stabilization` per the frozen TSC.6 plan. This ADR does **not** authorize schema changes, TARGET bump, release/tag, Yoast implementation, Site Health UI, CPT/taxonomy admission filters, or public Elementor widget registration.

**Evidence / plan base:**

- [TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md](../plans/TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_IMPLEMENTATION_PLAN.md)
- [TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_PLANNING_VALIDATION_LOG.md](../plans/TSC6_PUBLIC_EXTENSION_SEO_STABILIZATION_PLANNING_VALIDATION_LOG.md)
- [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md)

**Related:** ADR-0001 (overlay-not-duplication); ADR-0005 (segment-centric storage); ADR-0007 (hash ≠ identity); ADR-0013 (Gutenberg `b:`); ADR-0016 (Elementor ownership); ADR-0017 (Integration API v1); ADR-0020 (publication/TI.7); ADR-0021 (taxonomy admission).

**Revalidation triggers:** Proposal to expose Store rows or write APIs publicly; proposal for durable registration database; proposal for slug-only CPT/taxonomy admission without bounded source-adapter contract; proposal to promote internal `TranslatableBlockAdapter` as public API; proposal to add public overlay registration API; proposal to require Yoast in TSC.6 scope; Store key-length insufficiency requiring schema redesign.

---

## Context

AI Multilingual v1.3.0 completes TSC.0–TSC.5 with proven internal registries for meta (`RegisteredMetaRegistry`), blocks (`AdapterRegistry`), integrations (`IntegrationRegistry`), and surfaces (`SurfaceRegistry` / `SurfaceCapability`). Integration API v1 (ADR-0017) already provides a public contract for `p:` plugin integrations.

TSC.6 must stabilize a **second public surface** — Extension API v1 — for:

- exact-key registered meta (`m:`);
- narrow custom Gutenberg block adapters;
- read-only visitor translation resolution;
- request-local source invalidation notification.

Without an ADR, third-party developers lack a frozen boundary between supported public contracts and internal services that TSC.0–TSC.5 rely on.

---

## Decision

### 1. Public vs internal boundary

**Public (supported for third parties):**

| Surface | Contract |
|---|---|
| Integration API v1 | `aiml_register_integrations` + `PluginIntegrationInterface` — `p:` integrations (ADR-0017) |
| Extension API v1 | `aiml_register_extensions` + registrar/manifest/handle + public DTOs |
| Visitor resolver | `VisitorTranslationResolver` + identity DTOs — read-only |
| Invalidation helper | `aiml_mark_source_dirty()` — mark dirty only |
| Language switcher filter | `aiml_switcher_in_menu` — unchanged |

**Internal (unsupported for third parties — do not import or depend on):**

- `AIMultilingual\Translation\Store` and repositories
- `RegisteredMetaRegistry`, `AdapterRegistry`, `SurfaceRegistry`, `ElementorControlRegistry`
- `SurfaceCapability`, `PublicationPolicy`, Jobs/TI.6 admission internals
- OTL mutate paths, Extractor, SegmentAssembler internals
- Internal `TranslatableBlockAdapter`, `TranslatableField`, `ValidationResult`, `SanitizationSpec`
- Coordinator internals except via public invalidation wrapper

**Callable product APIs** (Workspace/Jobs/Glossary REST, existing WP-CLI commands, audit hooks) are first-party product surfaces — not extension registration APIs.

### 2. Root extension ownership

Extensions register through a **single registration phase** hook:

```php
add_action( 'aiml_register_extensions', function ( ExtensionRegistrar $registrar ): void {
    $handle = $registrar->register_extension( new ExtensionManifest( /* ... */ ) );
    $handle->register_meta( /* ExtensionMetaDefinition */ );
    $handle->register_block_adapter( /* ExtensionBlockAdapter */ );
} );
```

**`ExtensionManifest`** declares:

- `extension_id` (stable, unique)
- `version` (semver)
- `owned_namespaces` (meta namespace ownership)

**`RegisteredExtension`** handle owns nested registrations and diagnostics attribution.

**Rules:**

- Duplicate `extension_id` → reject
- Namespace collision across extensions → reject
- Registries **seal** after registration phase; late calls rejected
- Activation evaluated once at seal; cached per request
- Integration API v1 remains on separate `aiml_register_integrations` hook

### 3. Public meta v1

**`ExtensionMetaDefinition`** (minimal):

- `namespace`, `source_type` (`post`\|`term`), `meta_key` (exact)
- `label`, `text_format`, optional `admitted_subtypes`
- `provider_allowed` default **false**
- optional `activation` callable

**Excluded from public v1:** `overlay_capable`, overlay ownership tokens, `external_p` mode, wildcards, serialized/object meta, options/usermeta/theme_mods.

**Identity:** `m:{namespace}:{meta_key}` — collision with existing keys → reject.

**Visitor overlay:** extension owns output hooks; calls resolver; applies in-memory only. No generic overlay registration API.

### 4. Activation semantics

| State | Behavior |
|---|---|
| ACTIVE | Normal extract/provider/resolver |
| INACTIVE | Registered but inactive; CASE B retain existing rows; no provider/overlay |
| REMOVED | Absent from registration; orphan/retire on next sync |

**Uninstall limitation:** If extension code is entirely absent, AIML cannot distinguish temporary vs permanent removal. Retain requires loaded extension registering INACTIVE stub. **No durable registration table.**

### 5. Public resolver identity

```php
VisitorTranslationResolver::resolve(
    SourceSegmentReference $source,  // source_type, source_id, segment_key
    LanguageReference $language      // code only — not DB ID
): ?ResolvedTranslation;
```

**Resolver must:**

- validate complete source identity
- map language code internally
- validate admitted/existing source via internal capability checks
- enforce TI.7 visitor eligibility internally
- return null for source/default language
- fail closed for unknown/inactive/unadmitted references
- expose no Store row; no `force` bypass

### 6. Public block contract (Decision B)

Public: **`AIMultilingual\Extension\Block\ExtensionBlockAdapter`**

Internal: bridge to `TranslatableBlockAdapter` — owns `b:` UUID pipeline (ADR-0013), structural guard, sanitization.

**Not public:** internal `TranslatableBlockAdapter`, `AdapterRegistry`.

Hard invariants: explicit block names/fields; no JSON-path API; no canonical `post_content` mutation; no core block override; feature flags not bypassed.

### 7. Invalidation

`aiml_mark_source_dirty(source_type, source_id)` — thin validated wrapper over request-local coordinator. Mark dirty only; coalesce; shutdown @ 20 sole sync authority.

### 8. Failure isolation (honest tiers)

| Tier | Guarantee |
|---|---|
| A — Registrar validation | Reject malformed input; no partial registration |
| B — AIML-invoked callback | Catch Throwable; fail closed; bounded diagnostic |
| C — Hook callback outside AIML | Normal WordPress/PHP semantics — **not claimed isolated** |

### 9. Diagnostics

**Supported:** WP-CLI `wp aiml extensions list`, `wp aiml extensions status <extension_id>`

**Deferred:** Site Health, admin diagnostics UI

Output: bounded safe facts only — no callbacks, values, secrets, Store rows.

### 10. Deferred / unsupported (TSC.6)

- Public Elementor widget registration
- Yoast adapter implementation
- CPT/taxonomy slug-only admission filters
- Generic overlay registration API
- Public translation writes / review / publication / provider invocation
- REST registration endpoints
- Runtime admin field UI
- Durable registration database

### 11. Semver / deprecation

- Extension API v1 namespace: `AIMultilingual\Extension\`
- Parallel versioning to Integration API v1
- Deprecation: minimum one minor release warning before removal; document in release notes and EXTENSION_API_V1.md

### 12. SEO (TSC.6 scope)

No separate SEO public API. Rank Math remains on `p:rankmath:*`. TSC.6 SEO work = regression + documentation only. Yoast Deferred.

---

## Consequences

### Positive

- Third-party developers get a frozen, deny-by-default extension boundary
- Internal registries remain evolvable behind facade and bridge
- Resolver uniqueness guaranteed by full source segment identity
- Honest failure-isolation claims reduce support risk

### Negative

- Two public registration hooks (extensions + integrations) — documented cross-links required
- CPT/taxonomy admission deferred — agencies cannot admit custom types via filter in v1
- Elementor third-party widgets remain Integration-only until future work
- Extension authors must register INACTIVE stubs for retain semantics on dependency loss

---

## Compliance

TSC.6 implementation must satisfy PX1–PX31 and AC1–AC37 in the frozen plan. `PluginGuardTest::assert_tsc6_invariants()` enforces public symbol whitelist at implementation closure.

**STATE A · TARGET 7 · no migration.**
