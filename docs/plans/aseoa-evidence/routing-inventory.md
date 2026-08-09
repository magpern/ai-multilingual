# A.SEOa Evidence — Routing Inventory

**Status:** Investigation complete (planning)  
**Code:** [`src/Routing/Router.php`](../../../src/Routing/Router.php), [`src/Language/LanguageResolver.php`](../../../src/Language/LanguageResolver.php), [`src/Workspace/PreviewService.php`](../../../src/Workspace/PreviewService.php)  
**ADR:** [0002-prefix-strip-routing.md](../../adr/0002-prefix-strip-routing.md), [0008-language-state-model.md](../../adr/0008-language-state-model.md)

---

## 1. Language prefixes

| Behavior | Current fact |
|---|---|
| Inbound | First path segment matching a routable language code is stripped from `REQUEST_URI` before `WP::parse_request()` |
| Default language | Never treated as a prefix (unprefixed URLs = default) |
| Outbound | `home_url` filter adds prefix after `parse_request` when context is a translated language |
| Exclusions | `/wp-admin`, `/wp-login.php`, REST paths not prefixed |

## 2. Prefix stripping (ADR-0002)

- Hook: `plugins_loaded` priority **999**
- No AIML rewrite rules registered; none flushed
- After strip, WordPress resolves the **remaining path using source slugs / rewrite rules**

## 3. URL generation

| Generator | Behavior today |
|---|---|
| `home_url` / `get_permalink` under translated context | Language prefix + **source** path |
| `Switcher::url_for` | Swaps prefix on current unprefixed path — no Store slug overlay |
| `PreviewService::preview_url` | `get_permalink` + temporary `filter_home_url` under `LanguageContext::with` |

## 4. Preview routing (ADR-0008)

| Status | Routable? |
|---|---|
| `published` | Yes (public) |
| `preview` | Only if `current_user_can( aiml_translate )` |
| `disabled` | Never |

Preview languages are excluded from future hreflang/sitemaps (A.SEOb/A.SEOe) — not A.SEOa emission.

## 5. Redirect handling today

| Case | Behavior |
|---|---|
| Prefixed request + core `redirect_canonical` | Suppressed (`return false`) when `$this->prefixed` |
| Translated-slug historical redirect | **Not implemented** |
| Cross-language redirect | **Not implemented** (must remain non-heuristic) |

## 6. Implication for translated slugs

Inbound path after strip is matched by WordPress against **source** `post_name` / term slugs. A translated slug in the path does **not** resolve unless AIML maps it to the canonical object **before** or **during** parse — Store currently has **no reverse lookup by `translated_text`**.
