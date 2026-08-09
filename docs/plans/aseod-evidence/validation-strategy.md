# A.SEOd Evidence — Validation Strategy

## Automated

- Unit: OG/Twitter filter overlays; identity reuse; locale alternate building from SB11; preview exclusion; Rank Math inactive paths
- Integration: Rank Math hooks present; cascade from A.SEOc title; no duplicate AIML tag emission
- PluginGuard + PHPCS + `git diff --check`

## Live (observational acceptance — not implementation)

Representative EN/SV **page** and **product** (avoid broken `/sv/` home loop until fixed separately):

| Check | Pass rule |
|---|---|
| `og:title` / `og:description` | Language-correct when Store has A.SEOc SEO translation and no conflicting explicit FB override without translation |
| `og:url` | Matches language canonical |
| `og:locale` | Matches visitor language Facebook locale |
| `og:locale:alternate` | Published languages only; no preview; no duplicates; correct format |
| Twitter text | Mirrors admitted OG/SEO path when use_facebook |
| AIML duplicate tags | 0 extra `og:*` / `twitter:*` emitted by AIML outside Rank Math `tag()` |
| FP / leakage | 0 |
| Regression | A.SEOa/b/c, SB11, Gutenberg, Elementor, Woo A.7*, Fluent Forms A.8, A.6 |

## Performance

Observe only. Do not invent budgets.
