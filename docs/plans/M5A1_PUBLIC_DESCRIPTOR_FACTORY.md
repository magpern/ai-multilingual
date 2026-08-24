# M5-A.1 — Public Integration Descriptor Factory

**Status:** FROZEN — PO APPROVED  
**Repository:** [magpern/ai-multilingual](https://github.com/magpern/ai-multilingual)  
**Plugin:** AI Multilingual (`ai-multilingual`)  
**Namespace:** `AIMultilingual\`  
**Related public docs:** [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md), [EXTENSION_API_V1.md](../EXTENSION_API_V1.md), [ADR-0022](../adr/0022-public-extension-boundary-and-registration-lifecycle.md), [ADR-0025](../adr/0025-integration-owned-private-cpt-chrome-admission.md)  
**Planning baseline `main` SHA:** `8955a1b5f55ec77675715fc93eddf8de5ffc6933`  
**Baseline version:** 1.7.0  
**Recommended implementation release:** **1.8.0**  
**Drafted:** 2026-08-24  
**Frozen:** 2026-08-24

---

## 1. Objective

Publish a **small additive public Integration API correction** so an external integration can create a valid immutable `TranslationUnitDescriptor` from source text **without** importing internal `Store` symbols or reproducing AIML's undocumented canonical hash implementation.

This is a **generic** contract correction to the external M5-A extraction surface. It is not a downstream-plugin feature and must not include downstream product identifiers, code, or assumptions.

### Explicit non-goals

- Public `Store` read/write APIs.
- Public `Store` constants or documented `Store` imports for third-party integrations.
- A general-purpose public raw hash API if a narrow descriptor factory is sufficient.
- Arbitrary hash callbacks, WordPress filters, or pluggable hash algorithms.
- Translation-storage semantic changes.
- Any change to M5-A chrome admission, host-independent resolver, stale/publication eligibility, or visitor-language context.
- Production tag, GitHub Release, ZIP, deployment, or DEV configuration changes in this planning task.

---

## 2. Problem statement

Current public extraction guidance is incomplete.

| Area | Current state | Gap |
|------|---------------|-----|
| Public Integration contract | `PluginIntegrationInterface::extract_for_post()` returns `list<TranslationUnitDescriptor>` | Public API requires descriptor creation but does not fully document how to produce one |
| Public descriptor surface | `TranslationUnitDescriptor` constructor requires `source_hash` and `text_format` | Third-party integrations cannot compute these through documented public API only |
| Public format vocabulary | No documented public Integration-format constants | Consumers do not have a stable public HTML/plain vocabulary |
| Canonical source hash | Existing implementation lives behind internal canonical code | Consumers must not import internals or duplicate undocumented hashing |
| Existing fixtures/integrations | First-party code can use internal symbols | Third-party examples must not rely on private API |

Evidence in the current repo:

- [`PluginIntegrationInterface.php`](../../src/Integration/PluginIntegrationInterface.php) requires `extract_for_post()` to return descriptors.
- [`TranslationUnitDescriptor.php`](../../src/Integration/TranslationUnitDescriptor.php) currently documents `source_hash` and `text_format` as constructor inputs.
- [`Contract.php`](../../src/Integration/Contract.php) currently exposes identity, ownership, and compatibility constants only.
- [`INTEGRATION_API_V1.md`](../INTEGRATION_API_V1.md) documents registration, identity, lifecycle, and chrome admission, but not a public descriptor-construction path.

**Verdict:** the external Integration API v1 extraction contract is incomplete and requires a narrow additive correction.

---

## 3. Locked public API design

Unless implementation audit finds a materially better **compatible** design, freeze the corrective surface as:

### 3.1 Public format constants

Add documented public Integration-format constants on `AIMultilingual\Integration\Contract`:

- `Contract::FORMAT_PLAIN`
- `Contract::FORMAT_HTML`

These are the only required formats for the corrective release. Do not expand public format vocabulary unless already needed by existing documented external Integration API use cases.

### 3.2 Public immutable factory

Add a public static factory on `AIMultilingual\Integration\TranslationUnitDescriptor`:

```php
TranslationUnitDescriptor::from_source(
	string $segment_key,
	string $source_text,
	string $text_format,
	string $ownership_class,
	string $owner_type,
	string $owner_id,
	string $field,
	string $field_label,
	string $integration_id,
	string $parent_context = ''
): self
```

The factory:

1. Accepts the normal descriptor identity/ownership fields plus source text and documented format.
2. Validates arguments against the public Integration contract.
3. Computes the canonical source hash internally using AIML's existing implementation path.
4. Returns an immutable `TranslationUnitDescriptor`.

### 3.3 Constructor compatibility

The existing public constructor remains available and unchanged for backwards compatibility.

- Do **not** remove it.
- Do **not** reorder constructor arguments.
- Do **not** alter stored descriptor shape.
- Existing constructor callers must continue to work unchanged.

---

## 4. Validation and failure contract

The draft implementation must fail closed.

### 4.1 Format validation

`$text_format` accepted by `from_source(...)` must be restricted to documented public constants only:

- `Contract::FORMAT_PLAIN`
- `Contract::FORMAT_HTML`

Unsupported format values must fail with documented `InvalidArgumentException`.

### 4.2 Required argument validation

Invalid required factory arguments must fail with documented `InvalidArgumentException`, following current descriptor/Integration conventions:

- empty `segment_key`
- empty `integration_id`
- empty `ownership_class`
- empty `owner_type`
- empty `owner_id`
- empty `field`
- empty `field_label`
- invalid or unsupported `text_format`

The factory must not silently coerce unsupported formats or incomplete required identity/ownership fields.

### 4.3 Source text handling

`source_text` is accepted as a string and hashed through AIML's canonical internal path.

- Do not publish raw normalization rules as public API.
- Do not add a new public normalization API.
- Do not permit external callers to override hash generation.

---

## 5. Internal delegation boundary

The factory must delegate to AIML's existing canonical source-hash path **internally**.

Acceptable implementation patterns:

- `TranslationUnitDescriptor::from_source(...)` calling a private/internal helper inside the Integration surface, or
- `TranslationUnitDescriptor::from_source(...)` delegating to the existing canonical implementation path internally.

Not allowed:

- public documentation telling third parties to import `Store`
- public documentation publishing the hash algorithm as an integration requirement
- a new public arbitrary hash callback/filter

The algorithm remains an implementation detail. The public contract is **descriptor creation**, not hash replication.

---

## 6. Documentation changes to ship with implementation

### 6.1 Integration API reference

Update [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md) to document:

- `Contract::FORMAT_PLAIN`
- `Contract::FORMAT_HTML`
- `TranslationUnitDescriptor::from_source(...)`
- argument ordering and meanings
- validation / `InvalidArgumentException` failure contract
- constructor compatibility
- example usage through public APIs only
- explicit prohibition on `Store` imports in third-party examples

### 6.2 Extension API reference

No Extension API behavior change is planned. Update [EXTENSION_API_V1.md](../EXTENSION_API_V1.md) only if a cross-reference helps external integrators understand that this change affects **extraction**, not resolver behavior.

### 6.3 Other docs

Prepare updates under repository conventions as implementation follow-ups:

- `CHANGELOG.md`
- release scope / release notes under `docs/releases/`
- closure documentation under `docs/plans/`
- ADR only if implementation audit determines the public-factory boundary deserves durable architectural capture

Public docs must be sufficient for an integration to use the factory without reading AIML source.

---

## 7. Generic fixture and example requirements

Add or update a **generic** reference fixture integration so it creates descriptors through public APIs only.

Constraints:

- no downstream plugin identifiers
- no downstream code
- no `Store` imports in fixture examples intended to demonstrate public usage

Target fixture areas:

- `tests/Fixtures/ReferenceIntegration/ReferenceIntegration.php`
- `tests/Fixtures/ReferenceIntegration/ChromeReferenceIntegration.php`

The fixture should demonstrate:

1. public format constants
2. `TranslationUnitDescriptor::from_source(...)`
3. unchanged `PluginIdentity::build()` usage for segment keys

---

## 8. Test plan to lock

Implementation must add or update automated coverage for at least:

### 8.1 Factory success cases

- plain descriptor via `from_source(...)`
- HTML descriptor via `from_source(...)`
- returned descriptor shape matches constructor-created shape for equivalent inputs

### 8.2 Canonical equivalence

- `from_source(...)` produces the same `source_hash` as the existing canonical internal path for equivalent source text and format
- `to_segment_array()` output remains unchanged for equivalent descriptor values

### 8.3 Failure contract

- unsupported format fails with `InvalidArgumentException`
- invalid required arguments fail with `InvalidArgumentException`

### 8.4 Compatibility / regression

- existing constructor callers continue to work unchanged
- existing integrations retain existing behavior
- M5-A chrome fixture behavior remains unchanged
- generic external-integration fixture creates descriptors without `Store` imports

### 8.5 Documentation correctness

Where repository conventions allow, public example code should be executable or otherwise covered by tests so docs cannot drift back toward `Store` imports.

---

## 9. Security and compatibility constraints

The corrective release must preserve:

- no public `Store` API
- no arbitrary hash callback/filter
- no change to translation storage semantics
- no change to source hashes for equivalent existing canonical inputs
- no change to M5-A private-CPT admission
- no change to host-independent resolver semantics
- no change to stale/publication eligibility
- no change to visitor-language context
- existing `TranslationUnitDescriptor` constructor compatibility

This is an additive public API correction, not a storage or routing change.

---

## 10. Version recommendation

**Recommended implementation version:** **1.8.0**

Rationale:

1. This is a corrective release for an incomplete external contract.
2. The correction publishes **new public API surface** (`Contract::FORMAT_*`, `TranslationUnitDescriptor::from_source(...)`).
3. Repository history treats additive public API work as a **minor** release, while patch releases are used for corrective behavior without broadening public API.

Do **not** assume a different version without explicit product decision. If release management has already reserved the next minor, update the recommendation during implementation planning with rationale.

---

## 11. Downstream DEV-unblock and production-release sequencing

This corrective release unblocks downstream DEV implementation only after:

1. implementation merged to `main`
2. public docs updated
3. installed on the target development environment
4. feature-probe verified through public symbols

Formal GitHub tag/release remains required only before production or ZIP-based downstream deployment.

```mermaid
flowchart LR
  planDoc[Plan Approved] --> impl[M5A1 Implemented]
  impl --> docs[Public Docs Updated]
  docs --> mergeMain[Merged To main]
  mergeMain --> devInstall[Installed On Target DEV]
  devInstall --> probe[Feature Probe Verified]
  probe --> downstream[Downstream DEV Unblocked]
  downstream --> prodRelease[Formal Tag Release For Production]
```

This plan does not authorize deployment, tag creation, ZIP publication, or production use.

---

## 12. Future implementation touchpoints

Likely implementation files:

- `src/Integration/Contract.php`
- `src/Integration/TranslationUnitDescriptor.php`
- `docs/INTEGRATION_API_V1.md`
- `tests/Fixtures/ReferenceIntegration/ReferenceIntegration.php`
- `tests/Fixtures/ReferenceIntegration/ChromeReferenceIntegration.php`
- relevant unit/integration tests
- `CHANGELOG.md`
- release documentation under `docs/releases/`

No code changes are made in this planning task.

---

## 13. Explicit exclusions

- No AIML implementation code in this planning task.
- No USA code or downstream-specific identifiers.
- No public raw hash API unless implementation audit proves the factory cannot satisfy the use case.
- No release tag, GitHub Release, ZIP, deployment, or DEV configuration change.
- No schema migration or storage-semantic change.

---

## 14. Genuine PO decisions still required

1. Confirm the public surface should remain **format constants on `Contract` + factory on `TranslationUnitDescriptor`** rather than an alternate but still compatible helper arrangement.
2. Confirm the **`1.8.0`** recommendation if product management wants to preserve the repo's current minor/patch distinction for additive public API corrections.

All other decisions in this draft are recommended to be treated as locked implementation guidance once approved.
