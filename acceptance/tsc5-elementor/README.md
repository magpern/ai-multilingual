# TSC.5 bounded browser smoke (local / non-CI)

Seven scenarios validating TSC.5 Elementor coverage on a live dev site with Elementor flags enabled.

**Not CI-gated** per frozen TSC.5 plan. Run manually against `https://dev.biopentra.eu` or equivalent when Elementor feature flags are ON.

## Prerequisites

1. Elementor flags enabled in AI Multilingual settings (`elementor_extraction_enabled`, `elementor_frontend_rendering_enabled`).
2. At least one secondary language configured.
3. Test page built with Elementor (admitted post type `page`).
4. Elementor 4.2.x family installed.

## Scenarios

| # | Scenario | Pass criteria |
|---|---|---|
| 1 | Heading translation | Visitor view shows translated heading title; `_elementor_data` unchanged in DB |
| 2 | Text Editor HTML translation | Visitor view shows translated HTML body text; structural href/class unchanged |
| 3 | Button label translation | Visitor view shows translated button text |
| 4 | Nested Elementor container | Deep nested heading/button widgets overlay correctly |
| 5 | Source edit → stale → retranslate | Elementor save marks segment stale; retranslation works via OTL |
| 6 | Elementor editor/preview canonical | Editor and preview iframe show source language only |
| 7 | Link URL/structure unchanged | Translated Text Editor cannot forge href; source URL preserved |

## Execution status

| Run | Date | Result | Notes |
|---|---|---|---|
| Local manual | Not executed in CI | Pending operator run | PHPUnit/integration suites provide automated coverage; browser smoke is operator-local |

## Notes

- Reuses Strategy F / TSC.4 browser acceptance patterns.
- Does not author a separate Playwright project; manual HTML inspection is sufficient for milestone closure when CI lacks browser infra.
