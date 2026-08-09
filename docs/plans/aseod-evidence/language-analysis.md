# A.SEOd Evidence — Language Analysis

| Mechanism | Effect on social metadata |
|---|---|
| `Router::filter_locale` | Changes `get_locale()` → Rank Math `og:locale` follows visitor language |
| A.SEOa `home_url` / prefix routing | Language-correct absolute URLs |
| A.SEOb canonical + `rank_math/frontend/canonical` | Feeds Rank Math `og:url` |
| SB11 `LanguageRelationshipService` | Published language graph + alternate URLs for locale alternates |
| A.SEOc title/description overlays | Cascade into OG/Twitter when Facebook/Twitter-specific meta empty |
| LanguageContext | Gates Store overlays |

## Locale format

Rank Math sanitizes to Facebook locale list (`en_US`, `sv_SE`, …).  
AIML language codes (e.g. `sv`) must map to Facebook-style locales when emitting alternates — use WordPress locale for that language / Rank Math allowlist, not invent unsupported codes.

## Live

EN → `og:locale=en_US`; SV content URLs → `og:locale=sv_SE`. Correct without A.SEOd code today.
