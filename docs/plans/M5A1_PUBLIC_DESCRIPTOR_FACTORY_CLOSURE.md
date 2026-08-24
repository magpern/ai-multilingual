# M5-A.1 — Public Descriptor Factory Closure

**Status:** COMPLETE on `main` (implementation merged; formal tag/ZIP/deploy separately authorized)  
**Plan:** [M5A1_PUBLIC_DESCRIPTOR_FACTORY.md](M5A1_PUBLIC_DESCRIPTOR_FACTORY.md) (`FROZEN — PO APPROVED`)  
**AIML version:** **1.8.0**  
**Closed:** 2026-08-24

---

## 1. Verdict

**M5-A.1 IMPLEMENTATION: PASS**

The public Integration extraction contract is now complete for descriptor creation through public APIs only.

- Public format constants ship on `AIMultilingual\Integration\Contract`.
- Public immutable `TranslationUnitDescriptor::from_source(...)` ships for canonical descriptor creation without third-party `Store` imports.
- Existing constructor compatibility is preserved.
- Translation storage semantics and all M5-A chrome/resolver contracts remain unchanged.

---

## 2. Public API delivered

| Symbol | Role |
|--------|------|
| `AIMultilingual\Integration\Contract::FORMAT_PLAIN` | Public plain-text format constant |
| `AIMultilingual\Integration\Contract::FORMAT_HTML` | Public HTML format constant |
| `AIMultilingual\Integration\TranslationUnitDescriptor::from_source(...)` | Public immutable descriptor factory with canonical internal hash delegation |

### Factory contract

- Accepts descriptor identity/ownership fields plus `source_text` and public `text_format`
- Validates required arguments and supported formats
- Fails closed with `InvalidArgumentException`
- Computes canonical `source_hash` internally
- Returns immutable descriptor `self`

### Compatibility

- Existing public constructor remains available and unchanged
- Existing integrations continue to work unchanged

---

## 3. Privacy / boundary evidence

- No public `Store` API added
- No public raw hash function exposed
- No arbitrary hash callback/filter added
- No change to translation storage semantics
- No change to M5-A private-CPT admission, host-independent resolver, stale eligibility, or visitor-language context
- Public Integration docs now direct third-party integrations to public format constants and `from_source(...)`, not `Store`

---

## 4. Test evidence

| Area | Evidence |
|------|----------|
| Public factory success | Plain + HTML descriptor tests |
| Canonical equivalence | Factory output matches canonical internal hash for equivalent inputs |
| Failure contract | Unsupported format / invalid required argument tests |
| Constructor compatibility | Legacy constructor shape remains valid |
| Fixture / regression | Generic reference fixtures now create descriptors through public APIs only |
| Existing integration behavior | Full test suite and repository checks required green before merge |

---

## 5. Downstream DEV gate

Downstream DEV implementation is unblocked only after:

1. this implementation is on `main`
2. target development environment runs AIML **1.8.0**
3. public feature probe verifies:
   - `Contract::FORMAT_PLAIN`
   - `Contract::FORMAT_HTML`
   - `TranslationUnitDescriptor::from_source(...)`

Formal GitHub tag/release remains required only before production or ZIP-based downstream deployment.

---

## 6. Key paths

- `src/Integration/Contract.php`
- `src/Integration/TranslationUnitDescriptor.php`
- `docs/INTEGRATION_API_V1.md`
- `tests/unit/Integration/TranslationUnitDescriptorTest.php`
- `tests/Fixtures/ReferenceIntegration/ReferenceIntegration.php`
- `tests/Fixtures/ReferenceIntegration/ChromeReferenceIntegration.php`
- `CHANGELOG.md`
