# A.SEO Evidence — Routing & Redirect Baseline

**Status:** Initial architectural evidence (parent freeze)  
**Code reference:** [`src/Routing/Router.php`](../../../src/Routing/Router.php)  
**ADR:** [0002-prefix-strip-routing.md](../../adr/0002-prefix-strip-routing.md)

---

## 1. Current routing model

1. On `plugins_loaded` priority 999, AIML strips the language prefix from `REQUEST_URI` before `WP::parse_request()`.
2. Locale / `language_attributes` filters apply for the active language.
3. `home_url` prefixing attaches on `parse_request` (not earlier) to avoid truncating slugs that begin with a language code.
4. No AIML rewrite rules are registered; none are flushed (ADR-0002).

## 2. Current redirect behavior

| Behavior | Today | Parent freeze implication |
|---|---|---|
| Prefixed request + core `redirect_canonical` | Suppressed (`return false`) | A.SEOa/A.SEOb must replace blind suppress with correct language-aware canonical/redirect policy |
| Translated slug historical redirects | Not implemented | A.SEOa |
| Cross-language redirects | Not admitted | Require explicit admission; default deny |
| Redirect chains | Must never be created | Family stop condition |

## 3. ADR-0002 rewrite-base constraint

> Translated rewrite bases (`/sv/produkt/`) are not possible under this model and would reopen the decision.

**Parent freeze:** Translated rewrite bases = **Deferred / ADR-required**. A.SEOa may translate post/term slugs under prefix-strip; it must not silently claim rewrite-base translation.

## 4. Downstream consumers

| Consumer | Needs from A.SEOa |
|---|---|
| A.SEOb canonical/hreflang | Stable language-aware absolute URLs |
| A.SEOc Rank Math | Correct permalink assumptions |
| A.SEOe sitemaps | Published URL set + alternates inputs |
