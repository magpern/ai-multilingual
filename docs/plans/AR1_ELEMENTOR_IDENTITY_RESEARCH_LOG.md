# A.R1 — Elementor Identity Research Log

**Status:** Research execution complete  
**Charter:** [AR1_ELEMENTOR_IDENTITY_RESEARCH_SPIKE.md](AR1_ELEMENTOR_IDENTITY_RESEARCH_SPIKE.md)  
**Research branch:** `feature/ar1-elementor-identity`  
**Baseline main (plan merge):** `b310190c4766696e6c42982bde4ffc76d435322c`  
**Recommendation:** **CONDITIONAL GO**  
**A.2 / production Elementor translation:** Still **blocked** until ADR written and **Accepted**  
**Evidence root:** `research/ar1-elementor-identity/evidence/`

This log is the source of truth for A.R1 findings. Confidence labels follow the charter §14.1 model.

**Frozen research outcomes (documentation refinement; conclusions unchanged):**

- Canonical conceptual model: **Hybrid D** (§ Canonical conceptual identity contract)
- Ownership **precedence** rules (§ Ownership precedence)
- Permanent **deny-list** artifact: [research/ar1-elementor-identity/DENY_LIST.md](../../research/ar1-elementor-identity/DENY_LIST.md) and [Appendix A](#appendix-a--permanent-deny-list)
- **Adapter graduation** lifecycle (§ Adapter graduation)
- **Stability ≠ support** (§ Stability vs support vs ownership)

Recommendation remains **CONDITIONAL GO**. No production grammar is defined here.

---

## Canonical conceptual identity contract (Hybrid D)

Research prefers Candidate D and hereby promotes it to the **canonical conceptual model** for Elementor translation units. This remains **conceptual only** — not a production key grammar.

### Composition

```text
Owner Scope
    ↓
Owner Identifier
    ↓
Element Identifier
    ↓
Field / Control Identifier
    ↓
Nested Item Identifier   (optional)
    ↓
Responsive Variant       (optional)
```

| Layer | Role |
|---|---|
| **Owner Scope** | document-owned / shared-definition-owned / consuming-document-owned / unsupported |
| **Owner Identifier** | Post/document or definition ID that owns the overlay namespace |
| **Element Identifier** | Native Elementor element `id` (Candidate A substrate) |
| **Field / Control Identifier** | Widget setting/control key for one value |
| **Nested Item Identifier** | Repeater/tab/accordion item `_id` when applicable |
| **Responsive Variant** | Device-specific control suffix when Elementor stores a distinct value |

### Non-identity payload rules

| Item | Role |
|---|---|
| Source / stale **hash** | **Freshness only** — never part of identity |
| Source-language **value** | Overlay payload — not identity |
| Translated **value** | Overlay payload — not identity |

**Owner scope is mandatory.** An element ID without owner scope is not a safe translation-unit identity (cross-document collisions proven in ER0/ER1).

Native Elementor IDs (Candidate A) are the **element layer** inside Hybrid D. Structural paths (Candidate C) are rejected. Candidate B remains denied under current governance.

Confidence for promoting D: **supported by evidence** (unchanged from ER2/ER7).

---

## Stability vs support vs ownership

These three judgments are independent. Evaluation must not conflate them.

| Dimension | Question |
|---|---|
| **Stable identity** | Does a deterministic identity exist and survive normal edits? |
| **Ownership resolved** | Is owner scope known and collision-safe? |
| **Supportability** | May AIML translate this value under Hybrid D (direct / adapter / deny)? |

A value may have **stable identity** and still be **unsupported** (e.g. `html`, shortcode, dynamic tags, third-party opaque widgets).  
A value may be **supportable** only after ownership is resolved.  
Missing ownership resolution → treat as unsupported (leave source), even if the native element ID appears stable.

---

## Ownership precedence

Applies the ER5 matrix with explicit precedence so implementations cannot silently share or duplicate translations.

### Precedence rules (highest first)

1. **Unsupported / ambiguous ownership** — If reference semantics are unclear, **do not translate**. Leave source. Never invent silent sharing.
2. **Shared-definition-owned** — When Elementor provides a **stable reference** to a library / Theme Builder / global definition (`template_id` or equivalent), overlays bind to the **definition** owner identifier — not to each consuming page’s local wrapper element alone.
3. **Document-owned** — Ordinary page/post Elementor trees that are not live references: overlays bind to that document’s owner identifier.
4. **Consuming-document-owned (after copy)** — When content is **copied** / pasted / inserted as an independent tree (not a live reference), ownership moves to the **consuming document**. Overlays must **not** continue to share the definition’s translation set.

### Anti-patterns (forbidden)

- Silently duplicating shared definition translations onto unrelated consuming documents.
- Keying overlays only by native element ID without owner scope.
- Treating a copied tree as still definition-owned without a live reference.
- Sharing one overlay key across two posts that happen to reuse the same element ID (proven collision class).

Frozen defaults from the charter remain in force and are consistent with this precedence.

---

## Adapter graduation

Support classification is a **lifecycle**, not a permanent label for adapter cases.

```text
Unsupported
    ↓  (research + evidence)
Research
    ↓  (explicit adapter contract)
Adapter
    ↓  (adapter proven; identity/ownership/render safe)
Directly Supported
```

| Stage | Meaning |
|---|---|
| **Unsupported** | On the deny-list; source fallback |
| **Research** | Under investigation; not visitor-translated |
| **Adapter** | Translated only via an explicit widget/control adapter |
| **Directly supported** | Covered by the general Hybrid D model without a special adapter |

**Adapters are not permanent.** A successful adapter should graduate to **directly supported** once evidence shows the general model covers that control family safely. Graduation requires evidence; it does not remove the deny-list as an institution — items leave the deny-list through evidence, not by discarding the list.

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

| Candidate | Stable identity? | Ownership resolvable? | Supportability | Verdict |
|---|---|---|---|---|
| **A** Native Elementor ID | Yes (within doc; proven) | Only with explicit owner scope | Supportable through adapter / as D substrate | Viable only with owner + field + nested keys |
| **B** AIML ID in Elementor data | Potentially yes (probe) | Requires ADR ownership exception | Unsupported (architectural contract violation) | GO bar not met |
| **C** Structural path | No (reorder breaks paths) | N/A | Unsupported (unstable identity) | Rejected |
| **D** Hybrid | Yes when layers complete | Yes (owner scope mandatory) | Directly supportable (canonical conceptual model) | **Canonical conceptual contract** |
| **E** Adapter / unsupported | Case-by-case | Case-by-case | Adapter or deny | Correct for complex widgets |

**Candidate B probe** (`er4-hooks-candidate-b.json`): `_aiml_identity` survived meta roundtrip and Elementor `document->save()`; builder content still showed title. Confidence: **supported by evidence** for preservation on disposable fixtures only. Copy/import/update governance incomplete → **not** recommended for GO.

**Canonical conceptual model:** Hybrid **D** (see § Canonical conceptual identity contract). Confidence: **supported by evidence**.

---

## ER3 — Widget/control taxonomy and deny-list

Sources: `er3-taxonomy.json`, `er3-deny-list.json`, plus inventory frequency.

Tables below separate **stable identity**, **ownership**, and **supportability**.

### Directly supportable (first-surface candidates)

| Family | Stable identity? | Ownership | Supportability | Likely controls | Confidence |
|---|---|---|---|---|---|
| `heading` | Yes (native ID + `title`) | Document-owned (typical) | Directly supportable | `title` (+ responsive e.g. `title_mobile`) | Supported by evidence |
| `text-editor` | Yes | Document-owned (typical) | Directly supportable | `editor` | Supported by evidence |
| `button` | Yes | Document-owned (typical) | Directly supportable | `text` | Supported by evidence |

(`container` may have stable IDs but no visitor text controls — stability without a translatable value is not a support claim.)

### Supportable through adapter

| Family | Stable identity? | Ownership | Supportability | Notes | Confidence |
|---|---|---|---|---|---|
| `accordion`, `toggle` | Yes if `_id` + field keys used | Document-owned (typical) | Adapter | Repeater rows via `_id` + `tab_title` / `tab_content` | Supported by evidence |
| `image` | Yes for caption/alt keys | Document-owned (typical) | Adapter | Caption/alt need control mapping | Inferred |
| `loop-grid` | Element may be stable; cell content often not | Often ambiguous / dynamic | Adapter or deny | Query/template driven — high risk | Supported by evidence |
| `divider` / layout-only | Yes | Document-owned | Usually N/A (no text) | Usually no text | Supported by evidence |

### Deterministic deny-list (must not translate)

Permanent artifact: [Appendix A](#appendix-a--permanent-deny-list) and [`research/ar1-elementor-identity/DENY_LIST.md`](../../research/ar1-elementor-identity/DENY_LIST.md).

| Item | Stable identity? | Ownership resolved? | Supportability | Reason |
|---|---|---|---|---|
| `html` | May be stable at element level | Often document-owned | Unsupported | unsupported Elementor behavior |
| `shortcode` | May be stable at element level | Often document-owned | Unsupported | unsupported Elementor behavior |
| Dynamic-tag values (`__dynamic__`) | Unstable / runtime | N/A | Unsupported | dynamic runtime value |
| `loop-grid` query-generated cell content | Unstable | Ambiguous | Unsupported | dynamic runtime value |
| WooCommerce product widgets (`woocommerce-*`) | Opaque | Third-party | Unsupported | third-party opaque persistence |
| `fluent-form-widget` | Opaque | Third-party | Unsupported | third-party opaque persistence |
| `biopentra_header_auth`, `mega-menu`, theme chrome | Case-by-case | Often ambiguous | Unsupported | unsupported Elementor behavior / ownership ambiguity |
| Global/template widgets without stable `template_id` | Element ID alone insufficient | No | Unsupported | ownership ambiguity |
| Any value without field-level identity | No | N/A | Unsupported | unstable identity |

Source fallback unchanged: deny-listed values remain **source**. Confidence: **supported by evidence** (fixture + inventory); Woo/third-party rows **inferred** from widget presence without deep adapter research.

Future milestones may **remove** items from the deny-list only through new evidence (and adapter graduation where applicable) — not by discarding the deny-list.

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

See also § Ownership precedence (rules above the matrix). Referenced templates remain **definition-owned**; copies become **consuming-document-owned**; ambiguous cases stay **unsupported**.

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

### Translation-unit model (Hybrid D — canonical conceptual contract)

See § Canonical conceptual identity contract. Summary:

```text
Owner Scope
  → Owner Identifier
  → Element Identifier
  → Field / Control Identifier
  → Nested Item Identifier (optional)
  → Responsive Variant (optional)
```

Hash and translated/source values are **not** identity. Owner scope is **mandatory**. Confidence: **supported by evidence**.

### Identity comparison (summary)

**Canonical model: D (hybrid).** Reject **C**. Keep **B** denied unless future ADR explicitly accepts Elementor persistence mutation **and** copy/import/update evidence is completed. **A** is the element-ID substrate inside **D**.

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

1. Identity model = Hybrid D conceptual contract with mandatory owner scope and field/nested granularity.  
2. First implementation surface limited to the advisory set below.  
3. Deny-list enforced with source fallback (permanent artifact; removals require evidence).  
4. No HTML scraping.  
5. No Candidate B without separate Accepted ownership exception.  
6. Cache/language isolation designed before production render.  
7. Re-validate on Elementor upgrades.  
8. Adapter cases follow graduation lifecycle (not permanent adapters by default).

### Recommended first implementation surface (advisory only)

Does **not** authorize implementation.

| Include | Controls | Stable identity? | Ownership | Supportability |
|---|---|---|---|---|
| `heading` | `title` (+ responsive title variants when present) | Yes | Document-owned (typical) | Directly supportable |
| `text-editor` | `editor` | Yes | Document-owned (typical) | Directly supportable |
| `button` | `text` | Yes | Document-owned (typical) | Directly supportable |

| Exclude | Stable identity? | Supportability | Reason |
|---|---|---|---|
| `html`, `shortcode` | Often yes at element level | Unsupported | unsupported Elementor behavior |
| Repeaters (`accordion`/`toggle`) | Yes with `_id` | Adapter — defer | adapter graduation pending |
| `image` alt/caption | Likely yes | Adapter — defer | adapter graduation pending |
| `loop-grid`, Woo, Fluent, custom chrome | Mixed | Unsupported | deny-list |
| Dynamic tags | No | Unsupported | dynamic runtime value |
| Template/global ambiguous refs | Insufficient | Unsupported | ownership ambiguity |

**Known limitations:** Single environment (Elementor 4.2.1); Theme Builder definition-owned overlays not exercised end-to-end; no production render prototype registered.

**Rationale:** Smallest text-control set with clear settings keys, high frequency on Biopentra, stable native IDs under edit, and no repeater nesting. Confidence: **supported by evidence**.

### Recommended future ADR scope

**Title:** Elementor identity and ownership model  

Must cover:

- Hybrid D conceptual contract (owner → element → field → nested → responsive); hash ≠ identity  
- Ownership decision matrix **and precedence**  
- Permanent deny-list / source fallback / evidence-based removals  
- Adapter graduation lifecycle  
- Explicit separation of stability vs support vs ownership  
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
| Silent share of definition translations | High | Forbidden by ownership precedence |

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

---

## Appendix A — Permanent deny-list

Architectural deliverable of A.R1. Mirror: [`research/ar1-elementor-identity/DENY_LIST.md`](../../research/ar1-elementor-identity/DENY_LIST.md).

Items leave this list only through **new evidence** (and adapter graduation where applicable). The deny-list itself is not replaced by an allow-list.

### By reason

#### Ownership ambiguity

- Global/template widgets without stable `template_id` (or equivalent reference)
- Theme/chrome widgets with unclear document vs definition ownership (`biopentra_header_auth`, `mega-menu`, similar)

#### Unstable identity

- Any value lacking field/control-level identity (widget-only keys)
- Structural-path-only identities (Candidate C — rejected generally)

#### Dynamic runtime

- Dynamic-tag driven settings (`__dynamic__`)
- `loop-grid` (and similar) query-generated cell content

#### Third-party persistence

- WooCommerce Elementor widgets (`woocommerce-*`)
- `fluent-form-widget`
- Other third-party Elementor widgets not explicitly researched

#### Unsupported Elementor behavior

- `html` controls
- `shortcode` controls

#### Architectural contract

- Candidate B–style AIML metadata embedded in Elementor persistence (denied under current governance)
- HTML/DOM scraping as translation identity or primary render path

#### Performance / cache / security-privacy

- No specific widget family proven deny-only for performance in A.R1; cache/language leakage is a **design gate** for any future support (not a per-widget deny row yet)
- Security/privacy: fixtures/exports must remain sanitized; no translation of secrets or unrelated customer PII (operational deny)

Source fallback: all deny-listed values remain **source**.
