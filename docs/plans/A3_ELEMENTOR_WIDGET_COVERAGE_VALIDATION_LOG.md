# A.3 — Elementor Widget Coverage — Validation Log

**Milestone:** A.3 Elementor Widget Coverage
**Implementation branch:** `feature/a3-elementor-widget-coverage`
**Plan:** [A3_ELEMENTOR_WIDGET_COVERAGE_IMPLEMENTATION_PLAN.md](A3_ELEMENTOR_WIDGET_COVERAGE_IMPLEMENTATION_PLAN.md) (**Architecture Frozen**)
**ADR:** [0016-elementor-identity-and-ownership.md](../adr/0016-elementor-identity-and-ownership.md) (**Accepted**)
**Baseline main (pre-plan merge):** `adb39f7183be695a36793a3a0c293b79848c0263` (`a2-elementor-foundation-complete`)
**Plan merge commit:** `22b0907ff3b77ff0fd6348464402949d6687de28`

---

## A30 — Baseline, inventory, admission matrix

**Status:** PASS (docs / evidence only — no production support added)

### Environment

| Item | Value |
|---|---|
| Host | https://dev.biopentra.eu |
| Elementor | 4.2.2 |
| Elementor Pro | 4.2.1 |
| A.2 surface (unchanged) | `heading/title`, `text-editor/editor`, `button/text` |
| Nested grammar (A.3 reserved) | `e:d:<owner>:<element_id>:<control_key>:<nested_item_id>` — not production-enabled at A30 |
| Cache strategy | Matches A.2 (element-cache TTL disable while overlays on; document cache bust; language-aware unique_id) |

### A.2 regression baseline

| Check | Result |
|---|---|
| Production `src/Elementor` surface still A.2-only at A30 start | PASS |
| Registry allowlist count = 3 | PASS (pre-admission) |
| No Accordion/Toggle/Image adapters in production tree at A30 start | PASS |
| A.R1 deny-list present | PASS — `research/ar1-elementor-identity/DENY_LIST.md` |

### Candidate inventory (dev.biopentra.eu — published `_elementor_data`)

Sampled from postmeta documents (counts are approximate across inventory walk):

| Widget family | Observed | Wave | Notes |
|---|---|---|---|
| heading | high (224+) | A.2 retained | Directly supported |
| image | high (160+) | Wave 1 / A34 | Mostly media-only settings; caption ownership gated |
| button | high (151+) | A.2 retained | Directly supported |
| text-editor | medium (77+) | A.2 retained | Directly supported |
| icon | medium | out of scope unless A35 | Icon glyph, not primary text |
| icon-list | present (e.g. 3825+) | A35 candidate | Repeater `icon_list` with `_id` + `text` |
| call-to-action | present (3520+) | A35 candidate | Flat `title`, `description`, `button` |
| accordion | present (4124+, FAQ pages) | Wave 1 / A32 | Repeater `tabs` with `_id`, `tab_title`, `tab_content` |
| toggle | present (4616, 4617) | Wave 1 / A33 | Same control shape as accordion; **live rows often lack `_id`** |
| loop-grid / Woo / html / fluent-form | present | excluded | Deny-list / out of scope |

### Wave 1 candidates (frozen)

1. Accordion — `tabs` / `tab_title` / `tab_content` / `_id`
2. Toggle — same nested model; legacy missing `_id` → source until editor assigns IDs
3. Image — ownership gate A34 (Elementor-owned only)

### A35 bounded pool (max 3; from inventory)

1. Icon List (`icon-list` / `icon_list` / `text` / `_id`)
2. Call to Action (`call-to-action` / `title`, `description`, `button`)
3. *(third slot reserved after A34 — evaluate only if low-risk; zero admissions OK)*

### Admission matrix (canonical — updated through A38)

| Candidate | Prior state | Owning WP | Identity | Ownership | Controls | Evidence | Unit | Integration | Browser | Cache/lang | Perf | Limitations | Disposition |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| heading/title | A.2 Direct | A.2 | `e:d:…:title` | document | title | A2 log | PASS | PASS | PASS | PASS | OK | none new | Directly Supported (retained) |
| text-editor/editor | A.2 Direct | A.2 | `e:d:…:editor` | document | editor | A2 log | PASS | PASS | PASS | PASS | OK | HTML kses | Directly Supported (retained) |
| button/text | A.2 Direct | A.2 | `e:d:…:text` | document | text | A2 log | PASS | PASS | PASS | PASS | OK | none new | Directly Supported (retained) |
| accordion | Research | A32 | nested `_id` | document | tab_title, tab_content | A32 log + fixture | PASS | PASS | PASS | PASS | OK | missing `_id` → source | Directly Supported |
| toggle | Research | A33 | nested `_id` | document | tab_title, tab_content | A33 log + fixture | PASS | PASS | PASS | PASS | OK | legacy no `_id` | Directly Supported |
| image | Research | A34 | flat caption | document (custom only) | caption@custom | A34 log | PASS | PASS | PASS | PASS | OK | Outcome C subset | Adapter subset |
| icon-list | Research | A35 | nested `_id` | document | text | A35 log | PASS | PASS | PASS | PASS | OK | — | Directly Supported |
| call-to-action | Research | A35 | flat A.2 grammar | document | title, description, button | A35 log | PASS | PASS | PASS | PASS | OK | — | Directly Supported |

### Evidence paths (A30 seeds)

- Inventory commands: WP-CLI eval over `_elementor_data` on dev
- Accordion sample post `4124` — tabs with unique `_id`
- Toggle sample posts `4616`/`4617` — tabs **without** `_id` (source-fallback risk documented)
- Icon-list sample `3825` — `_id` + `text`
- CTA sample `3520` — `title` / `description` / `button`
- Image sample `3423` — attachment settings only (no custom caption)
- AR1: `research/ar1-elementor-identity/evidence/er1-stability.json`, `er0-widget-frequency.json`

### A30 validation

| Gate | Result |
|---|---|
| No production support added | PASS |
| Candidate inventory recorded | PASS |
| Admission matrix scaffolded | PASS |
| Deny-list baseline verified | PASS |
| Versions recorded | PASS |

---

## A31 — Nested identity extension

**Status:** PASS

- Additive grammar `e:d:<owner>:<element>:<control>:<nested_item_id>` implemented
- Missing/empty `_id` → no extract; duplicate `_id` → repeater extract abort + `duplicate_nested_id`
- No array-index identity; A.2 five-segment keys unchanged
- Strategy dispatch: `settings_string`, `repeater_field`, `image_custom_caption` (factory ready; admissions gated by registry)

## A32 — Accordion admission

**Status:** PASS — **Adapter → Directly Supported (allowlisted)**

| Field | Value |
|---|---|
| Candidate | accordion |
| Prior state | Research / unsupported |
| Owning WP | A32 |
| Identity | nested `_id` on `tabs` |
| Ownership | document |
| Controls | `tab_title` (plain), `tab_content` (html) |
| Evidence | unit AccordionAdmissionTest; live posts 4124+; fixture seed |
| Unit | PASS |
| Integration | *(A38)* |
| Browser | *(A37 matrix)* |
| Cache/lang | *(A37)* |
| Perf | nested extract O(rows); acceptable vs A.2 |
| Limitations | legacy rows without `_id` stay source; duplicate `_id` denies whole repeater |
| Disposition | **Directly Supported** (adapter-backed repeater strategy) |

## A33 — Toggle admission

**Status:** PASS — **Directly Supported** (adapter-backed; same repeater strategy as Accordion)

| Field | Value |
|---|---|
| Candidate | toggle |
| Prior state | Research |
| Owning WP | A33 |
| Identity | nested `_id` on `tabs` |
| Ownership | document |
| Controls | `tab_title`, `tab_content` |
| Evidence | ToggleAdmissionTest; live 4616/4617 lack `_id` → source; fixture rows with `_id` translate |
| Unit | PASS |
| Limitations | Legacy content without `_id` remains source until Elementor assigns IDs in editor |
| Disposition | **Directly Supported** |

## A34 — Image ownership decision

**Status:** PASS — Outcome **C** (documented subset)

| Field | Value |
|---|---|
| Candidate | image |
| Prior state | Research |
| Owning WP | A34 |
| Ownership analysis | Alt/title from Media Library attachment meta (`_wp_attachment_image_alt` / post_title). Attachment caption via `caption_source=attachment`. Elementor-owned only when `caption_source=custom` + `caption` string in widget settings. |
| Controls admitted | `caption` when `caption_source === custom` |
| Controls denied | attachment alt, attachment caption, title, dynamic tags |
| Identity | A.2 flat `e:d:…:caption` |
| Disposition | **Adapter / Directly Supported subset** (Outcome C) |
| Unit | PASS ImageAdmissionTest |

## A36 — Workspace, diagnostics, compatibility consolidation

**Status:** PASS

- Nested units flow through existing Extractor → Store → Workspace assembler (`nested_item_id` in meta)
- Diagnostics extended: `nested_unit_extracted`, `missing_nested_id`, `duplicate_nested_id`, `adapter_failure`
- No redesign of Review / TM / Glossary / Jobs / conflict handling
- Compatibility remains `ElementorCompatibility` 4.2.x boundary
- Deny-list updated with A.3 graduation records

## A37 — Cache, language, performance hardening

**Status:** PASS

| Matrix cell | Result |
|---|---|
| cold EN / cold SV | PASS |
| warm EN / warm SV | PASS |
| EN → SV → EN | PASS |
| SV → EN → SV | PASS |
| mixed A.2 + A.3 page (fixture 6416) | PASS |
| unsupported/missing `_id` toggle legacy | source fallback (unit) |
| cross-language leakage | **0** |
| rendered false positives | **0** |

Performance (HTTP wall-clock, anonymous, fixture page): cold EN ≈ 1244 ms, cold SV ≈ 1638 ms (includes full page + Elementor). Unit extract count = 17. No candidate denied for performance.

## A38 — Full validation and closure

**Status:** PASS — implementation complete on branch (not merged)

| Gate | Result |
|---|---|
| PHPUnit unit | PASS — 491 tests (2 skipped) |
| PHPUnit integration | PASS — 507 tests (2 skipped) |
| PluginGuard | PASS — 17 tests / 8054 assertions |
| PHPCS (`src/Elementor`, SegmentAssembler) | PASS |
| git diff --check | PASS |
| Browser fixture EN/SV matrix | PASS — leakage 0, FP 0 |
| A.2 controls retained | PASS |
| Gutenberg unaffected | PASS (no Gutenberg code path changes) |

### Final supported-surface table

| Surface | Controls | Notes |
|---|---|---|
| **Retained A.2** | `heading/title`, `text-editor/editor`, `button/text` | Directly Supported |
| **A.3 nested** | `accordion` → `tab_title`, `tab_content`; `toggle` → `tab_title`, `tab_content`; `icon-list` → `text` | Adapter (`repeater_field`); `_id` required |
| **A.3 flat** | `call-to-action` → `title`, `description`, `button` | Directly Supported |
| **A.3 image subset** | `image/caption` when `caption_source=custom` | Adapter; Outcome C |
| **Evaluated / denied or out of scope** | Media Library image alt/caption; loop-grid; Woo widgets; html; fluent-form; Theme Builder | Remain on deny-list |
| **Known limitations** | Legacy toggle rows without `_id` → source; duplicate `_id` → whole repeater denied; nested-of-nested deferred; responsive deferred | |

### Closure

- Do **not** merge / tag / release from this agent pass.
- Suggested future tag after independent review + merge: `a3-elementor-widget-coverage-complete`
- Exact next step: independent review → merge to `main` → tag → then A.4 planning/implementation may begin.
