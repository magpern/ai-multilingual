# A.SEOf Evidence — Canonical / Hreflang / Relationships (SF2–SF4)

**Owner:** Rank Math (canonical filters) + AIML `DocumentSeoHead` (hreflang) + SB11 (graph)

## Contract validation (primary)

- Expected public set: `LanguageRelationshipService::for_path($path, false)` / `for_public_request()`
- Reciprocity / orphan / duplicate detection against SB11 URLs and hreflang tags
- x-default must point at default-language URL from SB11
- Preview languages must be absent from public expected set (ADR-0008)

## Emission validation (bounded)

Live `dev.biopentra.eu` (2026-08-09):

| URL | hreflang count | x-default | `/de/` leakage |
|---|---|---|---|
| EN page fixture | 3 | yes | 0 |
| SV page fixture | 3 | yes | 0 |
| EN product | 3 | yes | 0 |
| SV product | 3 | yes | 0 |

Canonical `<link rel="canonical">` often omitted under sitewide noindex / `blog_public=0`; `og:url` still present and language-correct. Diagnostics must prefer Rank Math filter / SB11 expected values when HTML canonical is absent — report honesty limitation, not false failure of AIML.

**Disposition:** SF2/SF3/SF4 **Supported** via SB11 + DocumentSeoHead / RM filter seams.
