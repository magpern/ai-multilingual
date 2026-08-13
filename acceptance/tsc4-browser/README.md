# TSC.4 bounded browser smoke (local / non-CI)

Seven scenarios validating TSC.4 Gutenberg coverage on a live dev site with block flags enabled.

**Not CI-gated** per frozen TSC.4 plan. Run manually against `https://dev.biopentra.eu` or equivalent when block feature flags are ON.

## Prerequisites

1. Block flags enabled in AI Multilingual settings (registration, injection, extraction, frontend render).
2. At least one secondary language configured.
3. Test page with admitted post type (`page`).

## Scenarios

| # | Scenario | Pass criteria |
|---|---|---|
| 1 | Quote citation overlay | Visitor view shows translated `<cite>`; canonical post_content unchanged |
| 2 | Details summary overlay | Visitor view shows translated `<summary>` |
| 3 | Image caption overlay | Visitor view shows translated `<figcaption>` |
| 4 | File fileName + downloadButtonText | Both labels overlay; href unchanged |
| 5 | Nested gallery caption | Caption inside gallery renders; gallery wrapper not duplicated |
| 6 | Forged href rejection | Stored translation with `<a href="https://evil.test">` falls back to source href |
| 7 | Flag defaults OFF after plugin reinstall | Fresh options: all four block flags false |

## Notes

- Reuses Strategy F editor/overlay patterns from `docs/plans/STRATEGY_F_F9_BROWSER_ACCEPTANCE.md`.
- Does not author a separate Playwright project; manual or ad-hoc grep of rendered HTML is sufficient for milestone closure when CI environment lacks browser infra.
