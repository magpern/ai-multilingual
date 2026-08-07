# A.8 Fluent Forms Contact Form #5 — Admission Record

| Field | Value |
|---|---|
| Integration ID | `fluentform` |
| Package | `src/Integration/FluentForms/` (production) |
| Plugin / version fixture | Fluent Forms **6.2.9** (`FLUENTFORM_VERSION`) |
| Min supported version | **6.2.0** (verified field-data filter family) |
| Form ID | **5** (Biopentra Contact Form) |
| Containing page | **3410** / `contact` / https://dev.biopentra.eu/contact/ |
| Embed mechanism | Elementor `fluent-form-widget`, `settings.form_list = "5"` |
| Ownership | `record-owned` — Fluent Forms form row ID 5; AIML overlays only |
| Surfaces | `full_name` label; `email` label; `submit_text` |
| Identities | `p:fluentform:form:5:full_name:label`; `p:fluentform:form:5:email:label`; `p:fluentform:form:5:submit_text` via `PluginIdentity::build()` |
| Extraction | Allowlisted JSON paths only when post embeds form 5; `Store::source_hash` freshness |
| Overlay hooks | `fluentform/rendering_field_data_input_text` (guard `full_name`); `…_input_email` (guard `email`); `…_button` → `button_ui.text` |
| Hook callback shape | `($data, $form)` → mutated array `$data` before HTML emit |
| Sanitization | Plain text; ingest `IntegrationSecurity::sanitize_plain`; overlay plain unescaped; Fluent Forms owns `fluentform_sanitize_html` |
| Lifecycle | missing / inactive / unsupported_version / missing_required_hook / disabled / compatible; Store retained; no fuzzy rematch |
| Copy/delete | New form ID → new owner_id; deleted form → source fallback |
| Fallback | Store miss/stale/error → original Fluent Forms source; continue |
| Platform | Store / Workspace / Review / TM / Glossary / Jobs unchanged |
| Performance | See validation log A87 |
| Disposition | **Supported** |
| Limitations | Form #5 only; three surfaces only; single published embed required; no confirmation HTML / options / other forms |

## Evidence pointers

- Plan: [A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md](A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md)
- Selection: [A8_INTEGRATION_CANDIDATE_SELECTION.md](A8_INTEGRATION_CANDIDATE_SELECTION.md)
- Validation: [A8_FLUENTFORMS_CONTACT_INTEGRATION_VALIDATION_LOG.md](A8_FLUENTFORMS_CONTACT_INTEGRATION_VALIDATION_LOG.md)
- ADR-0017: [../adr/0017-plugin-integration-framework-ownership-and-identity.md](../adr/0017-plugin-integration-framework-ownership-and-identity.md)
- API: [../INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md)
