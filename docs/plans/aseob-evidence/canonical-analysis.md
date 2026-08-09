# A.SEOb Evidence — Canonical Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `a1e91f442`  
**Code:** [`src/Routing/Router.php`](../../../src/Routing/Router.php)  
**Related:** A.SEOa SA7 (language-aware permalinks)

---

## 1. Current state

| Behavior | Today |
|---|---|
| Document `<link rel="canonical">` from AIML | **None** |
| Rank Math canonical | Owner when active; removes WP `rel_canonical`; builds from `get_permalink` (+ optional per-post override) |
| Live dev site | Global `noindex,nofollow` → Rank Math **omits** canonical tag (env artifact; not architecture) |
| `og:url` (Rank Math) | Language-aware (EN unprefixed / SV prefixed) via SA7 `home_url` |
| AIML `redirect_canonical` | Blind `return false` when request was language-prefixed |

## 2. Problem statement

Blind suppress prevents WP from stripping language prefixes (good for loop safety) but does **not** emit a correct language-aware canonical document relationship. A.SEOb must:

1. Define the **correct absolute canonical URL** per published language using SA7 URL identity (source leaf + prefix rules).
2. Replace blind suppress with a **language-aware redirect policy**: never redirect a prefixed URL to an unprefixed equivalent; only allow redirects that preserve language identity.
3. Cooperate with Rank Math / WP / storefront filters — not scrape HTML.

## 3. Official integration points (candidates for Supported implementation)

| Hook / API | Owner | Use |
|---|---|---|
| `get_canonical_url` | WordPress | Language-aware absolute URL when AIML/WP path active |
| `rel_canonical` | WordPress | Only when Rank Math inactive |
| `rank_math/frontend/canonical` / Paper canonical filters | Rank Math | Overlay language-aware URL when Rank Math owns emission |
| `redirect_canonical` | WordPress | Language-preserving policy replacing blind suppress |
| `get_permalink` + Router `filter_home_url` | WP + AIML SA7 | Source of absolute language URLs |

## 4. Non-goals

- Per-post Rank Math canonical meta translation → A.SEOc / not reinvent Store fields here without evidence
- Translated leaf slugs in canonical → Deferred with A.SEOa SA1+
- Guessing canonical from content similarity or HTML scrape → Forbidden

## 5. Admission implication

**SB1** (canonical generation) and **SB2** (override policy) are implementable inside existing hooks + SA7 without Store/schema/TARGET change, provided override policy **respects foreign owners** when they supply an explicit override URL (then still apply language prefix rules only when the override is site-local and language-ambiguous — exact policy frozen in wave plan; no guessing across hosts).
