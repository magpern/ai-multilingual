# M5-A — Private CPT Chrome Integration Closure

**Status:** COMPLETE on `main` (implementation merged; public tag/ZIP/deploy separately authorized)  
**Plan:** [M5A_PRIVATE_CPT_CHROME_INTEGRATION.md](M5A_PRIVATE_CPT_CHROME_INTEGRATION.md) (`FROZEN — PO APPROVED`)  
**ADR:** [0025-integration-owned-private-cpt-chrome-admission.md](../adr/0025-integration-owned-private-cpt-chrome-admission.md)  
**AIML version:** **1.7.0** (schema TARGET remains **8**)  
**Closed:** 2026-08-23

---

## 1. Verdict

**M5-A IMPLEMENTATION: PASS**

Additive public Integration/Extension capability for integration-owned private-CPT site-wide chrome is implemented, tested, documented, and ready for a separately authorized 1.7.0 release/deploy. USA M5-B remains **unimplemented** and **blocked** until that release is authorized and deployed to the USA target environment.

---

## 2. Public API contract (shipped)

| Symbol | Role |
|--------|------|
| `AIMultilingual\Integration\DeclaresChromeOwnedSurfaces` | Optional companion interface |
| `AIMultilingual\Integration\ChromeOwnedSurfaceDeclaration` | Sealed declaration (post_type, owner_types, fields, `integration_units_only`) |
| `AIMultilingual\Integration\IntegrationAdmissionRegistry` | Collect → post-`init` validate → activate |
| `AIMultilingual\Extension\VisitorTranslationResolver` | Host-independent `p:` resolve for activated chrome sources |
| `aiml_visitor_language(): ?VisitorLanguageContext` | URL/host language code + `is_default` |
| `aiml_mark_source_dirty` | Accepts activated chrome CPT sources |

### Lifecycle contracts

1. **Declaration validation:** after CPT registration (normally `init` priority 20). Invalid → disable **that** chrome-surface declaration + authorized diagnostic (`chrome_declaration_disabled`); continue other declarations/integrations; never fail the registry.
2. **Chrome resolve eligibility (Extension-strict):** missing/stale/unpublished/ineligible/invalid identity/unsupported/non-`publish` source/no-longer-admitted → `null`. Default language → `null`.
3. **FrontendBridge:** host-bound; **I7 unchanged** (stale may overlay when publication-eligible).
4. **Visitor language:** valid after AIML request language context is established; `null` when unavailable/too early; no cookie/geo/`Accept-Language`.

---

## 3. Compatibility / privacy evidence

- Existing `PluginIntegrationInterface` implementors require no changes.
- No `aiml_admitted_post_types` (or similar) public filter.
- AIML does not flip CPT `public` / `show_in_rest` / rewrite / archives / permalinks.
- Chrome extract is `integration_units_only` (no natives/blocks/Elementor/meta).
- No USA-specific symbols or code in AIML.
- No per-page source duplication; no public Store API.

---

## 4. Test evidence

| Suite | Result |
|-------|--------|
| Unit (`phpunit.xml.dist`) | PASS (929 tests; 2 skipped pre-existing) |
| M5-A integration filter `M5aChromeAdmissionTest` | PASS (10 tests, 50 assertions) via generic fixture `ChromeReferenceIntegration` (`aiml_chrome_ref` / `aiml_chrome_item`) |
| PHPCS on M5-A touched paths | PASS after autofix |
| Full CI on implementation PR | Required green before merge |

Fixture restored/test-scoped only — never registered from production `Plugin.php`.

---

## 5. USA M5-B dependency gate

USA M5-B **must not** be frozen or implemented until:

1. This M5-A implementation is on `main` (done at closure merge).
2. AIML **1.7.0** is tagged/released under separate authorization.
3. That release is deployed to the USA target environment under separate authorization.

This closure does **not** authorize tag, GitHub Release, ZIP, production deploy, or USA M5-B work.

---

## 6. Key paths

- `src/Integration/DeclaresChromeOwnedSurfaces.php`
- `src/Integration/ChromeOwnedSurfaceDeclaration.php`
- `src/Integration/IntegrationAdmissionRegistry.php`
- `src/Integration/IntegrationAdmission.php`
- `src/Extension/VisitorLanguageContext.php`
- `src/Extension/VisitorTranslationResolver.php`
- `src/Extension/ExtensionServices.php`
- `src/Extension/functions.php`
- `src/Translation/Extractor.php`
- `src/Workspace/WorkspaceService.php`
- `tests/Fixtures/ReferenceIntegration/ChromeReferenceIntegration.php`
- `tests/integration/Extension/M5aChromeAdmissionTest.php`
