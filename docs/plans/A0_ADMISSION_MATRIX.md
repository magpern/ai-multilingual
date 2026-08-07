# A.0 — Candidate Admission Inventory

**Milestone:** A.0 Gutenberg Leaf Expansion  
**Parent plan:** [A0_GUTENBERG_LEAF_EXPANSION_IMPLEMENTATION_PLAN.md](A0_GUTENBERG_LEAF_EXPANSION_IMPLEMENTATION_PLAN.md)  
**Validation log:** [A0_GUTENBERG_LEAF_EXPANSION_VALIDATION_LOG.md](A0_GUTENBERG_LEAF_EXPANSION_VALIDATION_LOG.md)  
**WP parse evidence:** WordPress `parse_blocks()` on this VPS (2026-08-07)

---

## Admission record template

| Field | Content |
|---|---|
| Block name | |
| Field | |
| Ownership | |
| UUID / identity result | |
| Extraction strategy | |
| Rendering strategy | |
| Sanitization | |
| Workspace / Review / TM / Glossary / Jobs | Existing pipeline |
| Diagnostics | Bounded extraction/render events |
| Browser evidence | |
| EN/SV | |
| Rendered FP | |
| Language leakage | |
| Performance | |
| Limitations | |
| Disposition | Admitted / Partially admitted / Deferred / Unsupported |

---

## Wave 1 candidates

| Candidate | Field(s) | Parse shape | Identity hypothesis | Initial disposition |
|---|---|---|---|---|
| `core/quote` | `citation` | Host with `innerBlocks`; citation in `<cite>` in host markup (not child HTML) | Host UUID + `b:<uuid>:citation`; children keep own UUIDs | **Evaluate → Admit if PASS** |
| `core/details` | `summary` | Host; `<summary>` in host markup before null child slot | Host UUID + `b:<uuid>:summary` | **Evaluate → Admit if PASS** |
| `core/pullquote` | `content`, `citation` | True leaf (empty `innerBlocks`); body `<p>` + `<cite>` | Leaf UUID + fields | **Evaluate → Admit if PASS** |

---

## Wave 2 candidates

| Candidate | Candidate field(s) | Hard gate | Initial disposition |
|---|---|---|---|
| `core/table` | cell text / caption | Cells are query arrays — path/index identity required for cells | **Likely Deferred** (confirm in A03) |
| `core/image` | `caption` (figcaption only) | Block-local figcaption; never attachment alt/title/caption | **Evaluate → Admit caption if PASS** |
| `core/file` | `fileName`, `downloadButtonText` (attrs) | Block-owned attrs only | **Evaluate** |
| `core/audio` | `caption` | Block-local figcaption | **Evaluate** |
| `core/video` | `caption` | Block-local figcaption | **Evaluate** |

Media Library attachment metadata: **Unsupported** (hard deny).

---

## Wave 3

Bounded inventory: prefer zero admissions unless a clearly deterministic visitor-facing core leaf appears in A01 live inventory with stable UUID + explicit field.

**Default disposition:** zero Wave 3 admissions = PASS.

Hard defer remains: Navigation, Query, post-template, reusable/shared, synced patterns, dynamic bindings, Media Library ownership, parent list-item + innerBlocks ambiguity, wp-admin, email.

---

## Live inventory notes

Dev site uses Gutenberg extensively for product/content pages. Highest-leverage admissions are Wave 1 deferred A.4 fields (citation/summary/pullquote) and block-local media captions. Table cell translation is not promised.

Detailed per-candidate admission records are appended in the validation log as A02–A04 complete.
