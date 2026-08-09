# A.SEOd Evidence — Integration Analysis

| Item | Finding |
|---|---|
| Integration API v1 | Unchanged; Rank Math consumer already registered |
| `RankMathIntegration` | Title/description/schema/replacements only — extend for OG/Twitter filters within same integration |
| PluginIdentity | ADR-0017 `p:rankmath:{owner}:{id}:{field}` — reuse `title`/`description`; optional new fields only for Partially Supported explicit FB/Twitter text |
| SB11 | Injected into RankMathIntegration; consume unchanged for alternates |
| Second pipeline | Forbidden — no parallel AIML OG emitter competing with Rank Math tags |
| HTML rewrite | Forbidden |

## Extension preference

Add OpenGraph/Twitter overlay methods to existing `RankMathIntegration` (or a tightly coupled helper owned by it). Do not create a second plugin integration id for social tags.
