# A.SEOa — Implementation Inventory Lock

**Status:** Locked at ASEOA.1  
**Branch:** `feature/aseoa-slugs-permalinks`  
**Baseline:** `b42d9ccb8` / planning evidence on `main`

Runtime re-check against planning evidence ([routing-inventory.md](routing-inventory.md), [ownership-inventory.md](ownership-inventory.md), [admission-matrix.md](admission-matrix.md)):

| Check | Runtime fact | Drift? |
|---|---|---|
| Router prefix strip | `Router::resolve` on `plugins_loaded` 999 | None |
| `home_url` late attach | `parse_request` priority 0 → `filter_home_url` | None |
| Default language unprefixed | Resolver never treats default code as prefix | None |
| Prefixed SV | `/sv/...` strips; context translated | None |
| `redirect_canonical` suppress | Returns false when `$prefixed` | None |
| PreviewService | `get_permalink` + temporary `filter_home_url` under LanguageContext | None |
| LanguageResolver preview gate | Preview only with capability | None |
| Reverse translated-slug lookup | No Store API / no callers | None |
| Slug history subsystem | No table / registry | None |
| AIML rewrite rules | Router registers none | None |
| `SOURCE_TERM` | Absent | None |
| Extractor `post_name` | Not extracted | None |

**Verdict:** Evidence matches runtime. Supported set remains SA7/SA10. Proceed to characterizing tests.
