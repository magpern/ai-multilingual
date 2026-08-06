# A.R1 — Elementor Identity Research Log

**Status:** Research execution complete  
**Charter:** [AR1_ELEMENTOR_IDENTITY_RESEARCH_SPIKE.md](AR1_ELEMENTOR_IDENTITY_RESEARCH_SPIKE.md)  
**Research branch:** `feature/ar1-elementor-identity`  
**Baseline main (plan merge):** `b310190c4766696e6c42982bde4ffc76d435322c`  
**Recommendation:** **CONDITIONAL GO**  
**A.2 / production Elementor translation:** Still **blocked** until ADR written and **Accepted**  
**Evidence root:** `research/ar1-elementor-identity/evidence/`

This log is the source of truth for A.R1 findings. Confidence labels follow the charter §14.1 model.

---

## ER0 — Baseline, environment, and fixture inventory

### Verified environment (proven by experiment)

| Fact | Value |
|---|---|
| WordPress | 7.0.2 |
| PHP | 8.4.23 |
| Elementor slug / version | `elementor` / **4.2.1** |
| Elementor Pro slug / version | `elementor-pro` / **4.2.1** |
| Active theme | `blocksy-child` 1.1.0 (parent Blocksy) |
| AI Multilingual | 1.0.0 |
| `elementor_experiment-container` | active |
| `elementor_experiment-nested-elements` | default |
| `elementor_experiment-e_optimized_markup` | active |
| `elementor_cpt_support` | post, page |

Source: `er0-environment.json`.

### Inventory (proven by experiment)

- 36 Elementor builder documents inventoried (pages + `elementor_library`).
- Top widgets: `text-editor` (198), `heading` (179), `image` (53), `button` (40), `shortcode` (15), `html` (13), `loop-grid` (8), `accordion` (5), plus WooCommerce/Theme/Fluent/custom widgets.
- **16 cross-document element ID collisions** observed (same `id` in ≥2 posts), including intentional shared IDs such as `bp_root`, `bp_card` across library templates.
- 8 sanitized fixtures exported under `research/ar1-elementor-identity/fixtures/` with provenance + Elementor version recorded.

**Implication:** Any identity lacking **owner scope** is unsafe. Confidence: **proven by experiment**.

---

## ER1 — Native Elementor identity stability

Disposable private fixtures only (`_ar1_disposable=1`); trashed after experiments. Source: `er1-stability.json`.

| Experiment | Result | Confidence |
|---|---|---|
| Edit text only | All native IDs retained | Proven by experiment |
| Reorder siblings | All IDs retained; **5/6 structural paths changed** | Proven by experiment |
| Duplicate widget (new ID) | Original IDs retained; new ID appears | Proven by experiment |
| Duplicate page (copy meta) | **Identical IDs** in second post | Proven by experiment |
| Elementor document API resave | IDs retained | Proven by experiment |
| Import sanitized fixture | IDs preserved from payload | Proven by experiment |

**Findings:**

1. Native IDs are stable across normal text edits and Elementor API resave.  
2. Structural paths are **not** stable under reorder → Candidate C rejected.  
3. Page duplicate / import create **cross-document ID identity** without owner scope → collisions.  

Repeater item `_id` values are present on accordion `tabs` in disposable fixtures and must be part of translation-unit identity. Confidence: **proven by experiment**.

---

## ER2 — Identity candidates

Source: `er2-candidates.json`.

| Candidate | Classification | Verdict |
|---|---|---|
| **A** Native Elementor ID | Supportable through adapter | Viable only with owner + field + nested keys |
| **B** AIML ID in Elementor data | Unsupported (architectural contract violation) | Probe preserved metadata on disposable fixture; **GO bar not met** |
| **C** Structural path | Unsupported (unstable identity) | Failed reorder experiment |
| **D** Hybrid (owner + native ID + field + nested + hash≠identity) | Directly supportable (research direction) | Preferred |
| **E** Adapter / unsupported | Supportable through adapter / deny | Correct for complex widgets |

**Candidate B probe** (`er4-hooks-candidate-b.json`): `_aiml_identity` survived meta roundtrip and Elementor `document->save()`; builder content still showed title. Confidence: **supported by evidence** for preservation on disposable fixtures only. Copy/import/update governance incomplete → **not** recommended for GO.

**Recommended primary model:** Candidate **D**. Confidence: **supported by evidence**.

---

## ER3 — Widget/control taxonomy and deny-list

Sources: `er3-taxonomy.json`, `er3-deny-list.json`, plus inventory frequency.

### Directly supportable (first-surface candidates)

| Family | Likely controls | Confidence |
|---|---|---|
| `heading` | `title` (+ responsive variants e.g. `title_mobile`) | Supported by evidence |
| `text-editor` | `editor` | Supported by evidence |
| `button` | `text` | Supported by evidence |

(`container` classified direct but has no visitor text controls.)

### Supportable through adapter

| Family | Notes | Confidence |
|---|---|---|
| `accordion`, `toggle` | Repeater rows via `_id` + `tab_title` / `tab_content` | Supported by evidence |
| `image` | Caption/alt need control mapping | Inferred |
| `loop-grid` | Query/template driven — high risk | Supported by evidence |
| `divider` / layout-only | Usually no text | Supported by evidence |

### Deterministic deny-list (must not translate)

| Item | Reason |
|---|---|
| `html` | unsupported Elementor behavior |
| `shortcode` | unsupported Elementor behavior |
| Dynamic-tag values (`__dynamic__`) | dynamic runtime value |
| `loop-grid` query-generated cell content | dynamic runtime value |
| WooCommerce product widgets (`woocommerce-*`) | third-party opaque persistence |
| `fluent-form-widget` | third-party opaque persistence |
| `biopentra_header_auth`, `mega-menu`, theme chrome widgets | unsupported Elementor behavior / ownership ambiguity |
| Global/template widgets without stable `template_id` | ownership ambiguity |
| Any value without field-level identity | unstable identity |

Source fallback unchanged: deny-listed values remain **source**. Confidence: **supported by evidence** (fixture + inventory); Woo/third-party rows **inferred** from widget presence without deep adapter research.

---

## ER4 — Rendering integration research

Source: `er4-hooks-candidate-b.json`.

### Hooks of interest (presence)

| Hook | Listeners observed |
|---|---|
| `elementor/frontend/builder_content_data` | 2 |
| `elementor/widget/render_content` | 5 |
| `elementor/document/before_save` | 2 |
| `elementor/document/after_save` | 10 |
| `elementor/element/after_add_attributes` | 1 |
| `elementor/element/parse_css` | 1 |

`elementor/frontend/widget/before_render` / `after_render` were **not** registered in this boot (may still exist as apply_filters sites when Elementor renders). Confidence: **supported by evidence** for listed tags; **assumption requiring validation** that `builder_content_data` is the best overlay injection point — must be proven in A.2 spike/tests before production use.

**HTML post-render string replacement:** Rejected as primary architecture. Confidence: **architectural judgement** aligned with charter (not newly experimented).

**Requirements for any future renderer:** Elementor source unchanged; request/language scoped overlays; deterministic source fallback; editor/admin separated; language-scoped caches. Confidence: **inferred** from Platform v1 contracts + ER6 cache timing.

---

## ER5 — Template and reusable-content ownership

Source: `er5-ownership.json`.

- 8 `elementor_library` templates inventoried (header, kits, loop card, brand templates, etc.).
- 0 explicit `template`/`global` widget refs found in the sampled publish/private pages (many pages embed copied trees instead). Confidence: **supported by evidence** for this sample.

### Ownership decision matrix

| Content kind | Ownership scope | Collision rule | Confidence |
|---|---|---|---|
| Ordinary page widgets | document-owned | Include owning post ID | Proven by experiment |
| Saved library template definition | shared-definition-owned | Overlay against library post when referenced | Inferred |
| Theme Builder header/footer/etc. | shared-definition-owned | One overlay set for definition | Supported by evidence |
| Template widget with stable `template_id` | shared-definition-owned | Do not key only by consuming element ID | Supported by evidence |
| Copied/pasted template content | consuming-document-owned | Independent overlays; no silent share | Inferred |
| Ambiguous global without clear ref | explicitly unsupported | Leave source | Assumption requiring validation |
| Dynamic-tag values | explicitly unsupported | Leave source | Supported by evidence |

---

## ER6 — Compatibility, cache, performance

Source: `er6-performance.json`.

| Metric | Value | Confidence |
|---|---|---|
| Walk 40 headings | ~0.15 ms | Proven by experiment |
| First Elementor builder render | ~606 ms | Proven by experiment |
| Second builder render | ~12 ms | Proven by experiment (cache effect) |
| Simulated overlay map lookups | ~0.04 ms / 41 keys | Proven by experiment |

**Version matrix:** Current Biopentra env verified. Earlier Elementor versions **not** tested on this shared host (downgrade impractical). Confidence: **assumption requiring validation** for cross-version claims.

**Cache/language:** Second-render speedup implies Elementor caching — any overlay design **must** use language-scoped cache keys or bypass shared HTML cache. Confidence: **supported by evidence**.

---

## ER7 — Synthesis, containment, recommendation

### Containment audit

Source: `er7-containment-audit.json` + `acceptance/ar1-elementor/containment-audit.php`.

| Check | Result |
|---|---|
| Research/acceptance dirs present | PASS |
| No `Plugin.php` / main plugin AR1 references | PASS |
| No AR1 Elementor REST routes | PASS |
| Experimental README present | PASS |
| No schema migrations in research | PASS |
| ZIP exclusion policy documented | PASS (`ZIP_EXCLUSION.md`) |
| Delete research dirs → runtime unchanged | PASS (not bootstrapped) |

**Containment audit: PASS.** Confidence: **proven by experiment**.

### Translation-unit model (recommended)

Conceptual composition (illustrative — not a shipped grammar):

```text
owner_scope + owner_id
  + element_id
  + control_key
  + [repeater_item_id]
  + [responsive_suffix]
```

Plus: source-language value in Store overlay; source/stale hash for freshness only (never identity).

Must distinguish multiple fields on one widget and repeated rows sharing a control name. Confidence: **supported by evidence**.

### Identity comparison (summary)

Prefer **D (hybrid)**. Reject **C**. Keep **B** denied unless future ADR explicitly accepts Elementor persistence mutation **and** copy/import/update evidence is completed. **A** is the ID substrate inside **D**.

### GO / CONDITIONAL GO / NO-GO

## Recommendation: CONDITIONAL GO

**Rationale:**

- Native element IDs are stable enough for ordinary document-owned widgets (**proven**).
- Cross-document collisions require owner scope (**proven**).
- Structural paths are unsafe (**proven**).
- Supported Elementor hooks exist for request-time data/render interception research (**supported by evidence**).
- A bounded first surface (heading / text-editor / button text controls) is identifiable.
- Complex/dynamic/third-party/html/shortcode must stay on the **deny-list**.
- Candidate B does **not** clear governance.
- Multi-version Elementor matrix incomplete → condition the GO.

**Conditions for proceeding to ADR + A.2 planning:**

1. Identity model = hybrid D with mandatory owner scope and field/nested granularity.  
2. First implementation surface limited to the advisory set below.  
3. Deny-list enforced with source fallback.  
4. No HTML scraping.  
5. No Candidate B without separate Accepted ownership exception.  
6. Cache/language isolation designed before production render.  
7. Re-validate on Elementor upgrades.

### Recommended first implementation surface (advisory only)

Does **not** authorize implementation.

| Include | Controls |
|---|---|
| `heading` | `title` (+ responsive title variants when present) |
| `text-editor` | `editor` |
| `button` | `text` |

| Exclude | Reason |
|---|---|
| `html`, `shortcode` | unsupported Elementor behavior |
| Repeaters (`accordion`/`toggle`) | adapter required — defer |
| `image` alt/caption | adapter — defer |
| `loop-grid`, Woo, Fluent, custom chrome | deny-list |
| Dynamic tags | dynamic runtime value |
| Template/global ambiguous refs | ownership ambiguity |

**Known limitations:** Single environment (Elementor 4.2.1); Theme Builder definition-owned overlays not exercised end-to-end; no production render prototype registered.

**Rationale:** Smallest text-control set with clear settings keys, high frequency on Biopentra, stable native IDs under edit, and no repeater nesting. Confidence: **supported by evidence**.

### Recommended future ADR scope

**Title:** Elementor identity and ownership model  

Must cover:

- Hybrid identity grammar (owner + element + field + nested + responsive)  
- Ownership decision matrix  
- Deny-list / source fallback  
- Explicit rejection of HTML scraping and fuzzy rematch  
- Explicit rejection (or gated exception) of Candidate B  
- Coexistence with Gutenberg `b:<uuid>:<field>`  
- Cache/language isolation requirements  
- First-surface allowlist as non-normative appendix  

ADR may inherit only conclusions labelled **proven by experiment** or **supported by evidence**.

### Risk register

| Risk | Severity | Notes |
|---|---|---|
| Cross-doc ID collisions / intentional shared IDs | High | Owner scope mandatory |
| Path-based identity | High | Rejected |
| Elementor cache language leakage | High | Design gate for A.2 |
| Candidate B update stripping | High | Denied for now |
| Third-party widgets | Medium | Deny by default |
| Version drift | Medium | Re-test on upgrades |
| Accidental prototype promotion | Medium | Containment PASS; ZIP exclude |

---

## Experiments not fully executed (documented)

| Item | Status | Why |
|---|---|---|
| Earlier Elementor version matrix | Not executed | Shared dev host; downgrade impractical without dedicated env |
| Editor UI copy/paste via browser | Simulated via data-plane duplicates | No browser automation in this spike; data-plane results sufficient for ID collision proof |
| Live Theme Builder reference overlay end-to-end | Not executed | No production renderer; ownership matrix inferred from library meta + refs scan |
| Exhaustive third-party widget dissection | Partial | Inventory + deny-by-default |

These gaps condition the GO; they do not invalidate measured ID/collision/path findings.

---

## Exact next step

1. Review this research log and CONDITIONAL GO.  
2. If accepted, draft ADR *Elementor identity and ownership model* (do not implement A.2 yet).  
3. Only after ADR **Accepted**, open A.2 Elementor Foundation planning for the advisory first surface.

**Do not begin A.2 coding in this milestone.**
