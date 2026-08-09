# A.SEOf Evidence — Duplicate / Conflict Detection (SF10)

## Definitions (freeze carefully)

| Signal | Meaning | AIML responsibility |
|---|---|---|
| Duplicate hreflang locales/URLs | SB11 emission conflict | Validate / report AIML head |
| Contradictory SB11 vs emitted alternates | Emission drift | Report |
| Multiple AIML social overlays for same property | Integration bug | Report |
| Duplicate sitemap xhtml alternates | A.SEOe overlay bug | Report |
| Dual `<title>` on products | Pre-existing Rank Math/theme | **Report with foreign ownership attribution** — do not annex |

## Live

Product EN/SV: `title_count=2` (known pre-existing). Page fixtures: `title_count=1`.

Diagnostics must attribute ownership; not every duplicate HTML node is an AIML defect (FP control).

**Disposition:** SF10 **Supported** with ownership-aware severity.
