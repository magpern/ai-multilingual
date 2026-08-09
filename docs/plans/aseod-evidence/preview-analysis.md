# A.SEOd Evidence — Preview Analysis

**Contracts:** ADR-0008, SA10, SB9, A.SEOc SC12

| Component | Path | Social role |
|---|---|---|
| `PreviewService` | `src/Workspace/PreviewService.php` | Authorized preview URLs only; no meta emitter |
| LanguageContext | visitor vs preview capability | Gates overlays |
| SB11 | published/routable languages only for public discovery | Alternates must exclude preview langs |

## Requirements for A.SEOd

- Public `og:locale:alternate` (if Supported) lists only published/routable languages from SB11 — never preview-only languages
- Social text overlays on public requests use published language context only
- Authorized translator preview may show translated social metadata inside preview contract; must not become publicly indexable social variants
- Missing/inactive Rank Math or integration disabled → native Rank Math/WP; never fatal
