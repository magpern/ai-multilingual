# A.2 — Elementor Foundation — Validation Log

**Status:** **PASS — ready for review**  
**Plan:** [A2_ELEMENTOR_FOUNDATION_IMPLEMENTATION_PLAN.md](A2_ELEMENTOR_FOUNDATION_IMPLEMENTATION_PLAN.md)  
**ADR:** [0016-elementor-identity-and-ownership.md](../adr/0016-elementor-identity-and-ownership.md) **Accepted**  
**Implementation branch:** `feature/a2-elementor-foundation`  
**Plan merge on main:** `9e1530f46520f76897deff1846c47e47a8e429d1`  
**Baseline main (pre-plan merge):** `fbf8a553813172794bc37c3a84210de6e865e2cd`

---

## A20 — Baseline

| Check | Result |
|---|---|
| ADR-0016 Accepted | PASS |
| A.2 Architecture Frozen | PASS |
| No prior Elementor production services | PASS |
| Store additive `e:` keys (no schema bump) | PASS |
| Gutenberg `b:` untouched | PASS |

---

## Work package results

| WP | Status | Notes |
|---|---|---|
| A20 | PASS | Validation log opened |
| A21 | PASS | Compatibility + detector + registry (3 pairs only) |
| A22 | PASS | Hybrid-D `e:d:<owner>:<element>:<control>` + extractor |
| A23 | PASS | Store via existing APIs; Workspace SegmentAssembler meta |
| A24 | PASS | Hook `elementor/frontend/builder_content_data` (2nd arg = post ID) |
| A25 | PASS | Language isolation via disabling Elementor element-cache TTL + document cache bust + language unique_id |
| A26 | PASS | Diagnostics counters; Workspace surface meta |
| A27 | PASS | Elementor 4.2.2 / Pro 4.2.1; overlays fail closed when Elementor absent |
| A28 | PASS | Tier 0 gates + browser/HTTP matrix on fixture page 6403 |

---

## Compatibility boundary

`ElementorCompatibility` — supported family `4.2.x`. Live stack: Elementor **4.2.2**, Pro **4.2.1**. Overlays allowed only when available + supported. Elementor is never a hard dependency.

## Control registry

Allowlist only: `heading/title`, `text-editor/editor`, `button/text`.

## Identity

`e:d:<owner_post_id>:<element_id>:<control_key>` — hash freshness only.

## Rendering hook

`elementor/frontend/builder_content_data` — confirmed live; signature `(array $data, int $post_id)`.

## Cache / language isolation (A25 hard gate)

**Root cause of initial leak:** Elementor `_elementor_element_cache` stores language-agnostic rendered HTML/shortcodes.

**Mitigation (minimum necessary):**

1. `pre_option_elementor_element_cache_ttl` → `disable` while AIML Elementor frontend overlays enabled  
2. Delete `_elementor_element_cache` before builder content + on translation save  
3. Append language code to `elementor/element_cache/unique_id`  
4. Clear Elementor files_manager cache on translation save  

**Evidence (fixture `/a2-elementor-foundation-fixture/`, post 6403), alternating EN↔SV ×4–6:**

| Metric | Result |
|---|---|
| EN heading source | present |
| EN Swedish leak | **0** |
| SV heading target | present |
| SV English leak | **0** |
| Text editor EN/SV | present / translated |
| Button EN/SV | present / translated |
| Accordion unsupported | source both languages |
| Cross-language leakage | **0** |
| Rendered false positives | **0** |

---

## Frontend / browser acceptance matrix

| # | Case | Result |
|---|---|---|
| 1 | Heading translated | PASS |
| 2 | Text Editor translated | PASS |
| 3 | Button translated | PASS |
| 4 | Unsupported accordion source | PASS |
| 5 | Mixed page | PASS |
| 6 | Duplicate isolation (unit) | PASS |
| 7–9 | EN/SV cold/warm/alternating | PASS |
| 10 | Stale via sync_source (integration) | PASS |
| 11–13 | Elementor disable / Store rows / core | PASS (compatibility fail-closed; flags off leaves core) |
| 14 | Malformed data local-failure (unit) | PASS |
| 15 | Gutenberg unaffected (integration) | PASS |
| 16–20 | Workspace/Store/Review/TM paths | PASS (existing contracts + Store e: rows) |
| 21 | editor/wp-admin unaffected | PASS (admin short-circuit) |
| 22–23 | FP=0 / leakage=0 | PASS |

---

## Automated gates

| Gate | Result |
|---|---|
| PHPUnit unit (Elementor + full suite) | PASS (473 tests unit suite; 23 Elementor) |
| PHPUnit integration Elementor | PASS (9) |
| PluginGuard | PASS (17) |
| PHPCS Elementor paths | PASS |
| git diff --check | PASS |

---

## Limitations / debt

- Elementor document element-cache is disabled while AIML Elementor frontend overlays are on (merchant Elementor TTL setting overridden at runtime only).  
- A.2 allowlist only — no accordion/repeater/Theme Builder/shared templates.  
- AIML render-cache remains off.  
- Admin settings UI for Elementor flags not added (option keys exist; set via Settings API / seed script).  

## Merge readiness

Ready for independent review on `feature/a2-elementor-foundation`. **Do not merge/tag until review.** Do not begin A.3.
