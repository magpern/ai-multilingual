# A.SEOb Evidence — Language Relationship Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `a1e91f442`

---

## 1. What a language relationship is

For a given request / source object:

| Field | Meaning |
|---|---|
| language code | AIML language `code` |
| hreflang / locale | BCP47 from `locale` (`_` → `-`) |
| absolute URL | SA7 language-aware URL for the same unprefixed path / object |
| is_default | Languages `is_default` |
| is_current | Matches LanguageContext current |
| availability | Published only for public SEO graph |

## 2. Existing building blocks

| Primitive | Provides |
|---|---|
| `Languages::routable( false )` | Published language set (preview excluded for public SEO) |
| `LanguageResolver::is_routable` | ADR-0008 gates |
| `LanguageContext` | Current language |
| `Switcher::links` / `url_for` | Already builds alternate absolute URLs from unprefixed path + default/non-default rules |
| Router SA7 | `home_url` prefixing; default unprefixed |
| Store | Translation overlays for **content** — **not** required for URL graph under SA7 source-leaf model |

## 3. Gaps

- Switcher logic is UI-coupled; not a stable API for Rank Math / sitemaps / OG / diagnostics.
- No document emission of the graph.
- No shared validation of reciprocity.

## 4. Lifecycle

1. Resolve current language (LanguageContext) and unprefixed path / queried object.
2. Enumerate published languages (SB7/SB9).
3. For each language, compute absolute URL via SA7 rules (same path / permalink base).
4. Emit canonical for current; emit full hreflang set including x-default.
5. Downstream waves **read the same contract** (SB11) — they do not re-derive discovery independently.

## 5. Admission implication

**SB5 / SB6 / SB7** Supported as policies backed by existing primitives.  
**SB10** Supported as acceptance/guard validation in A.SEOb; full diagnostics UI remains A.SEOf.
