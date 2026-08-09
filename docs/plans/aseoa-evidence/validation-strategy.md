# A.SEOa Evidence — Validation Strategy

**Status:** Architecture validation philosophy for Supported + Deferred gates  
**Admissions:** [admission-matrix.md](admission-matrix.md)

---

## 1. Applies to Supported surfaces (SA7, SA10)

| Category | Requirement |
|---|---|
| EN/SV | Prefixed SV URLs generate correctly; EN default remains unprefixed |
| Permalink generation | `get_permalink` / `home_url` under LanguageContext match Router rules |
| Preview | Preview language URLs routable only with `aiml_translate`; published unaffected |
| FP / leakage | Prefix-only changes must not alter rendered body language incorrectly |
| Regression | Gutenberg, Elementor, Woo, Fluent Forms overlays still resolve |
| Negatives | No rewrite-base translation; no second router; no schema/TARGET change |

## 2. Deferred surface gates (must remain true)

| Gate | Check |
|---|---|
| SA1–SA4 | No production code claims translated leaf slug inbound resolution |
| SA5/SA8/SA9 | No AIML uniqueness/reserved/collision engine shipped |
| SA6 | No URL-history table / redirect registry |
| ADR-0002 | No translated rewrite bases |

## 3. Validation order (when implementing Supported work)

1. Preconditions (TARGET 6, ADRs, parent freeze)  
2. Router prefix generation EN/SV  
3. Preview routing capability gate  
4. Regression suite (unit / integration / PluginGuard / PHPCS)  
5. Confirm Deferred candidates untouched  

## 4. Browser / live (Supported)

- `/sv/<source-slug>/` continues to resolve for known fixtures  
- Preview language URL pattern remains capability-gated  
- No expectation of `/sv/<translated-slug>/` until SA1 ADR + future wave work
