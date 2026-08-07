# A.8 — First Production Plugin Integration (Fluent Forms) — Implementation Plan

**Status:** **Complete / merged / tagged** `a8-fluentforms-contact-integration-complete`
**Selected integration:** Fluent Forms — Contact Form **#5** (`fluentform`)
**Admission disposition:** **Supported** — [a8-fluentforms-contact-admission.md](a8-evidence/a8-fluentforms-contact-admission.md)
**Validation:** [A8_FLUENTFORMS_CONTACT_INTEGRATION_VALIDATION_LOG.md](A8_FLUENTFORMS_CONTACT_INTEGRATION_VALIDATION_LOG.md)
**Plan freeze:** Integration API v1 consumer only; record-owned form fields; official Fluent Forms 6.2.9 field-data filters; `p:` via `PluginIdentity`
**Single-embed scope:** Contact page **ID 3410** (`contact`) via Elementor `fluent-form-widget` `form_list: "5"` — exactly one published visitor embed (verified)
**ADR assessment:** **No new ADR required** — ADR-0017 + Integration API v1 sufficient
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — Program A — milestone **A.8**
**Selection matrix:** [A8_INTEGRATION_CANDIDATE_SELECTION.md](A8_INTEGRATION_CANDIDATE_SELECTION.md)
**Planning branch:** `feature/a8-first-production-integration-plan`
**Implementation branch:** `feature/a8-fluentforms-contact-integration` (**create only after this plan freezes on `main`**)
**Baseline (plan authoring):** `main` @ `5d51a69ada67be7c2d7048aaf16e9a11d2ea789a`
**Depends on:** A.1 complete (`a1-plugin-integration-framework-complete`); ADR-0017 **Accepted**; A.0 complete (`a0-gutenberg-leaf-expansion-complete`)
**Related:** [INTEGRATION_API_V1.md](../INTEGRATION_API_V1.md); [a1-reference-admission.md](a1-evidence/a1-reference-admission.md)

**Operational success:** A real installed third-party plugin (Fluent Forms) registers through Integration API v1, extracts a tiny allowlisted visitor-facing surface into Store under `p:` keys, overlays those values on the live Contact form, and reuses Workspace / Review / TM / Glossary / Jobs without a second pipeline.

**This plan is the proposed implementation contract for A80–A88.** Do not implement production code on the planning branch.

---

## 1. Purpose

Ship the **first production consumer** of Integration API v1.

A.8 validates the framework under real plugin ownership. It is **not** Fluent Forms completeness, not CookieYes, not Age Gate, not WooCommerce (A.7), and not Rank Math (A.SEO).

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| A.0 merged / tagged `a0-gutenberg-leaf-expansion-complete` | **Pass** |
| A.1 complete / tagged `a1-plugin-integration-framework-complete` | **Pass** |
| ADR-0017 **Accepted** | **Pass** |
| Roadmap Next = A.8 planning | **Pass** |
| `main` clean @ `5d51a69ad…` | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| Integration API v1 + `PluginIdentity` present | **Pass** |
| Fluent Forms active on dev (`fluentform` **6.2.9**) | **Pass** |
| Contact Form **#5** present; Contact page embeds it | **Pass** |
| No existing production Fluent Forms integration in `src/` | **Pass** |
| No existing `docs/plans/A8*` plan | **Pass** |

If any precondition regresses before coding: **STOP**.

---

## 3. Selection summary

Full matrix: [A8_INTEGRATION_CANDIDATE_SELECTION.md](A8_INTEGRATION_CANDIDATE_SELECTION.md).

| Role | Plugin | Why |
|---|---|---|
| **Chosen** | **Fluent Forms** (`fluentform`) form **#5** | Record-owned form ID; strong official render filters; fits post-scoped Store + `extract_for_post`; live Contact fixture; M-sized but bounded to 3 fields |
| **Runner-up** | Age Gate (`age-gate`) | Smaller surface, but **shared-definition** options collide with current Store overlay resolution (`source_id` = queried post) without a framework extension |
| **Deferred** | CookieYes (`cookie-law-info`) | High visitor value; **no** official content overlay filters; JS/`#ckyBannerTemplate` / cloud mode conflict with no-scrape overlay contract |
| **Rejected for A.8** | WooCommerce and commerce add-ons | Explicitly too broad (A.7 family) |
| **Out of lane** | Rank Math | A.SEO milestone |
| **Out of lane** | Elementor / Gutenberg first-party | Already covered by A.2–A.4 / A.0 |
| **Not third-party proof** | biopentra-storefront / loop-card | First-party; valuable later, not the Integration API third-party proof |

**Why not Age Gate first despite smaller size:** A.8 must prove overlay through the existing IntegrationFrontendBridge Store lookup `(post, queried_post_id, language, segment_key)`. Global `age_gate_messages` would require site-scoped resolution or duplicated per-post rows — either a framework change or an ownership hack. Fluent Forms form `#5` is naturally record-owned and page-embedded.

---

## 4. Frozen production surface (minimal)

### In scope (exactly three units)

| # | Surface | Source JSON path | Overlay hook | Mutation |
|---|---|---|---|---|
| 1 | Name field label | `fields[]` `attributes.name=full_name` → `settings.label` | `fluentform/rendering_field_data_input_text` | Guard `name===full_name`; set `settings.label` |
| 2 | Email field label | `fields[]` `attributes.name=email` → `settings.label` | `fluentform/rendering_field_data_input_email` | Guard `name===email`; set `settings.label` |
| 3 | Submit button text | `submitButton.settings.button_ui.text` | `fluentform/rendering_field_data_button` | Set `settings.button_ui.text` |

Primary visitor URL: https://dev.biopentra.eu/contact/ (page **3410**).

**Forbidden overlays:** `fluentform/rendering_field_html_*`, HTML scraping, output buffering, DOM rewrite.

### Explicitly deferred (not A.8)

- Other Fluent Forms forms (ids 1–4)
- Placeholders, help text, validation messages
- Select option labels (`reason_for_contact`, …)
- Confirmation HTML message
- Notifications / emails
- Conversational forms, payments, captcha chrome
- Admin / builder UI
- Dynamic `wc_related_order` field contents
- CookieYes, Age Gate, WooCommerce, Rank Math

---

## 5. Ownership model (frozen)

| Item | Value |
|---|---|
| Ownership class | `record-owned` ([Contract::OWNERSHIP_RECORD](../adr/0017-plugin-integration-framework-ownership-and-identity.md)) |
| Foreign canonical owner | Fluent Forms form row (`wp_fluentform_forms.id = 5`) + JSON `form_fields` |
| AIML owns | Store overlays only |
| Read method | Fluent Forms form API / table read of allowlisted field attributes |
| Output | Official Fluent Forms rendering filters only |
| Copy / duplicate | New form ID → new `owner_id`; old keys do not follow; history retained |
| Delete | Missing form → no extract; overlay source fallback; Store history retained |
| Fallback | Local failure → source string; continue |

**AIML must not** mutate `wp_fluentform_*` tables or rewrite form JSON as a translation mechanism.

---

## 6. Identity model (frozen)

Use Integration API v1 serializer only:

```text
p:<integration_id>:<owner_type>:<owner_id>:<field>[:<nested>...]
```

| Component | Value |
|---|---|
| `integration_id` | `fluentform` |
| `owner_type` | `form` |
| `owner_id` | `5` |
| Field / nested | see below |

Serializer component mapping:

| Key | integration_id | owner_type | owner_id | field | nested_id | `PluginIdentity::build(...)` |
|---|---|---|---|---|---|---|
| `p:fluentform:form:5:full_name:label` | `fluentform` | `form` | `5` | `full_name` | `label` | `build('fluentform','form','5','full_name','label')` |
| `p:fluentform:form:5:email:label` | `fluentform` | `form` | `5` | `email` | `label` | `build('fluentform','form','5','email','label')` |
| `p:fluentform:form:5:submit_text` | `fluentform` | `form` | `5` | `submit_text` | *(none)* | `build('fluentform','form','5','submit_text')` |

Key lengths: 36 / 32 / 32 — all ≤ 191. **Never** free-form concatenation.

| Invariant | Rule |
|---|---|
| Family | `p:` only for this integration |
| Source hash | Freshness only |
| Key length | ≤ 191; reject, do not truncate |
| Nested arity | Within `MAX_NESTED_COMPONENTS` |
| No new family | Forbidden |
| No fuzzy identity | Forbidden |

Verify key lengths at A81 (< 60 chars expected).

---

## 7. Extraction strategy

1. Compatibility must allow operation.
2. `extract_for_post( $post )`:
   - Detect whether `$post` embeds Fluent Form **5** (Elementor widget data / shortcode / known Contact fixture allowlist).
   - If not embedded: return `[]`.
   - If embedded: load form 5 definition; emit only the three allowlisted units.
3. No recursive “translate every string in JSON”.
4. Empty labels skipped.
5. Duplicate segment keys within one extract → hard failure / stop.

---

## 8. Output-hook strategy

1. `register_output_hooks( $resolve )` registers **only** when compatibility allows overlay.
2. Register exactly three verified Fluent Forms **6.2.9** field-data filters (see §4).
3. Resolver looks up `p:` keys via IntegrationFrontendBridge / Store for the queried post (Contact **3410**).
4. Overlay places **plain unescaped** strings into `$data` arrays; Fluent Forms owns final escaping (`fluentform_sanitize_html`).
5. **Forbidden:** `rendering_field_html_*`, HTML scraping, DOM rewrite, unscoped output buffering, mutating form JSON in DB.

Local miss / stale / error → leave source; continue. One field failure must not break the form.

---

## 9. Lifecycle / compatibility

| State | Behavior |
|---|---|
| Plugin missing | `unavailable` — no extract/overlay |
| Inactive | `unavailable` |
| Version below supported floor | `unsupported_version` |
| Required render filter missing | `missing_required_hook` |
| Integration disabled (AIML setting) | `disabled` — Store retained; no overlay |
| Compatible | extract + overlay |
| Form 5 deleted | no units; source fallback |
| Field removed / renamed | no overlay for missing identity; **no fuzzy rematch** |
| Reactivation | resume after compatibility PASS |
| Second published Form #5 embed | **STOP** A.8 — do not invent multi-embed Store semantics |

Supported fixture: Fluent Forms **6.2.9**. Runtime floor: **≥ 6.2.0** within the verified field-data filter family. Unknown/incompatible → source fallback. Do not claim broader compatibility without evidence.

---

## 10. Sanitization / security

| Field | Format | Rule |
|---|---|---|
| Labels / submit text | `plain` | Ingest: `IntegrationSecurity::sanitize_plain` (strip tags). Overlay: plain unescaped strings. Escaping owner: Fluent Forms. **No** pre-`esc_html` (avoids double-encoding). |

No HTML confirmation message in A.8. No secrets in diagnostics. PluginGuard unchanged.

---

## 11. Platform reuse

Must use existing:

- Store
- Workspace (`plugin_integration` surface meta already present)
- Review
- TM
- Glossary
- Jobs
- Integration diagnostics counters

No Fluent-specific Jobs pipeline. No second translation architecture.

---

## 12. Work packages (A80–A88)

### A80 — Candidate audit locked + baseline

| | |
|---|---|
| **Objective** | Lock selection matrix; open validation log; confirm form 5 + Contact embed |
| **Scope** | Docs / fixtures inventory |
| **Deps** | This plan merged / frozen |
| **Stop** | Form 5 missing; Fluent Forms inactive |
| **Commit** | `docs(integrations): open A.8 Fluent Forms baseline` |

### A81 — Admission/spec freeze

| | |
|---|---|
| **Objective** | Canonical admission record; exact hooks; key-length proof; version floor |
| **Scope** | Docs + failing-first tests listing expected keys |
| **Stop** | Identity exceeds 191; nested arity violated; scrape required |
| **Commit** | `docs(integrations): freeze A.8 Fluent Forms admission record` |

### A82 — Registration / compatibility

| | |
|---|---|
| **Objective** | `FluentFormsIntegration` implements `PluginIntegrationInterface`; register on `aiml_register_integrations` |
| **Likely files** | `src/Integration/FluentForms/*`; `src/Plugin.php` wiring |
| **Tests** | missing/inactive/version/disabled matrix |
| **Commit** | `feat(integrations): register Fluent Forms integration shell` |

### A83 — Identity + extraction

| | |
|---|---|
| **Objective** | Emit three `p:` units when post embeds form 5 |
| **Tests** | Unit extract; Contact fixture; non-Contact empty; duplicate guard |
| **Stop** | JSON universal walker introduced |
| **Commit** | `feat(integrations): extract Fluent Forms contact field labels` |

### A84 — Output overlay

| | |
|---|---|
| **Objective** | Apply translations via official Fluent Forms render filters |
| **Tests** | Integration overlay; source fallback; no DB mutation |
| **Stop** | HTML scrape / template string replace of full form HTML |
| **Commit** | `feat(integrations): overlay Fluent Forms contact labels` |

### A85 — Workspace / platform path

| | |
|---|---|
| **Objective** | Segments visible/editable; Review/TM/Glossary/Jobs smoke |
| **Commit** | `feat(integrations): expose Fluent Forms units in Workspace` |

### A86 — Lifecycle / security / diagnostics

| | |
|---|---|
| **Objective** | Disable/reactivate; delete form; bounded counters |
| **Commit** | `feat(integrations): harden Fluent Forms lifecycle diagnostics` |

### A87 — Browser / performance validation

| | |
|---|---|
| **Objective** | Live Contact EN/SV; FP=0; leakage=0; timing notes |
| **Commit** | `test(integrations): complete A.8 Fluent Forms acceptance` |

### A88 — Closure

| | |
|---|---|
| **Objective** | Final surface table; roadmap; tag prep |
| **Commit** | `docs(integrations): close A.8 Fluent Forms integration` |

---

## 13. Acceptance criteria (~36)

1. Integration ID `fluentform` registered via Integration API v1.
2. Only form **5** admitted.
3. Exactly three production units (name label, email label, submit text) unless a field is empty (then fewer).
4. Keys built only via `PluginIdentity`.
5. Keys match frozen grammar in §6.
6. Ownership class `record-owned`.
7. No Fluent Forms DB mutation for translation.
8. No HTML scraping.
9. No universal JSON string walker.
10. Compatibility matrix covers missing/inactive/unsupported/disabled/compatible.
11. Extract empty on posts without form 5.
12. Extract emits units on Contact (form 5 embed).
13. Overlay changes visitor labels when translations published.
14. Missing translation → source.
15. Workspace lists units under `plugin_integration`.
16. Review axis unchanged.
17. TM compatible.
18. Glossary compatible.
19. Jobs compatible for these segments.
20. Duplicate form → new owner id; old keys orphaned safely.
21. Deleted form → source fallback.
22. Schema TARGET remains 6.
23. No new identity family.
24. Gutenberg `b:` unaffected.
25. Elementor `e:` unaffected.
26. A.0 leaves unaffected.
27. A.1 reference fixture still contained (tests only).
28. Unit suite PASS.
29. Integration suite PASS.
30. PluginGuard PASS.
31. PHPCS PASS.
32. Live Contact EN/SV PASS.
33. Rendered FP = 0 on admitted labels.
34. Language leakage = 0.
35. Admission record disposition **Supported**.
36. Roadmap marks A.8 complete only after merge/tag.

---

## 14. Stop conditions

**Stop the candidate / choose runner-up path if implementation requires:**

- new Store schema
- new Integration API contract / ADR-0017 change
- generic HTML scraping
- fuzzy identity
- foreign persistence mutation
- plugin-specific translation or Jobs pipeline
- unrestricted JSON crawling
- site-scoped Store redesign (would re-open Age Gate instead under a separate plan)
- expanding beyond form 5 / beyond the three fields without a new plan revision

Candidate-local failure → defer that field. Milestone stops only if Integration API v1 itself is insufficient for any deterministic Fluent Forms overlay.

---

## 15. Risks

| Risk | Mitigation |
|---|---|
| Fluent Forms filter names differ by element type | A81 hook evidence matrix before coding |
| Form 5 field names change | Pin names in admission record; fail closed |
| Form embedded on multiple pages | Extract per embedding post; translations are per `source_id` (document limitation) |
| Plugin updates break filters | Version floor + compatibility `missing_required_hook` |
| Scope creep into all forms | Hard allowlist form id `5` |

---

## 16. Out of scope

- WooCommerce / A.7
- Age Gate / CookieYes production bridges
- Rank Math / A.SEO
- Full Fluent Forms catalog
- Email notification translation
- Confirmation HTML
- First-party Biopentra plugin bridges (separate later milestones)

---

## 17. Validation / browser matrix (minimum)

1. Contact EN source labels
2. Contact SV translated labels
3. Alternating EN↔SV
4. Non-Contact page without form 5 → no fluentform units
5. Plugin deactivated → source
6. Integration disabled → source; Store retained
7. Workspace edit/save
8. Review publish path
9. FP=0 / leakage=0 on admitted strings
10. Gutenberg + Elementor regression smoke

---

## 18. Documentation / roadmap (this planning task)

Create this plan + selection matrix. Update pointers only in:

- [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md)
- [../ROADMAP.md](../ROADMAP.md)

Record: A.8 plan exists; implementation not started; selected integration = Fluent Forms form 5.

---

## 19. Implementation sequencing (after plan freeze)

1. Merge planning branch to `main`.
2. Architecture review / freeze (no further planning cycle if unchanged).
3. Create `feature/a8-fluentforms-contact-integration`.
4. Begin **A80**.
5. Tag on closure only after A88.

---

## 20. Fast-track freeze

This plan introduces **no new architectural contract** beyond consuming ADR-0017 / Integration API v1.

Expected freeze verdict after review:

- **Status: Architecture Frozen**
- Implementation authorized on the dedicated branch
- **No further A.8 planning cycle** unless a stop condition forces candidate change

---

## 21. Rollback philosophy

- Per-WP commits; disable integration flag to stop overlay instantly
- Unregistering integration leaves Store history
- No schema rollback (TARGET stays 6)

---

## 22. ADR assessment

**Verdict: No new ADR required** provided A.8 remains:

- Integration API v1 only
- `p:` via `PluginIdentity`
- record-owned form id
- official Fluent Forms filters
- existing Store / Workspace / Jobs

If overlay evidence proves scraping is required, or site-scoped shared-definition Store resolution becomes mandatory: **STOP** and either switch to the next candidate or open a focused ADR — do not weaken ADR-0017 silently.

---

## Document control

| Item | Value |
|---|---|
| Canonical path | `docs/plans/A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md` |
| Selection matrix | `docs/plans/A8_INTEGRATION_CANDIDATE_SELECTION.md` |
| Planning branch | `feature/a8-first-production-integration-plan` |
| Implementation branch | `feature/a8-fluentforms-contact-integration` (after freeze) |
| Baseline | `main` @ `5d51a69ada67be7c2d7048aaf16e9a11d2ea789a` |
