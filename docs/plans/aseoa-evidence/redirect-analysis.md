# A.SEOa Evidence — Redirect Analysis

**Status:** Investigation complete (planning)
**Code:** [`src/Routing/Router.php`](../../../src/Routing/Router.php)
**Stop condition:** No persistent URL-history DB / redirect registry without ADR

---

## 1. Current AIML redirect behavior

| Mechanism | Present? | Behavior |
|---|---|---|
| Prefixed `redirect_canonical` suppress | Yes | Returns `false` when request was language-prefixed — prevents core stripping the prefix and looping |
| Language-aware canonical redirect | No | Deferred to cooperation with A.SEOb |
| Translated-slug → current-slug redirect | No | Not implemented |
| Cross-language redirects | No | Not admitted; must not guess |
| Redirect chains | N/A | Family rule: never create chains |

## 2. WordPress built-in old-slug behavior

| Mechanism | Owner | Applies to AIML translated slugs? |
|---|---|---|
| `_wp_old_slug` + `wp_old_slug_redirect` | WordPress | **Only when `post_name` changes in `wp_posts`** |
| AIML Store overlay of slug | AIML | Changing a Store slug value does **not** update `post_name` and does **not** create `_wp_old_slug` |

Therefore WordPress old-slug redirects **cannot** be reused as a translated-slug history mechanism without either mutating WP persistence (forbidden by ADR-0001) or inventing an AIML registry (forbidden without ADR).

## 3. Frozen redirect policy (evidence-based)

| Rule | Disposition |
|---|---|
| Keep prefix-loop protection until replaced by correct language-aware policy | Carry forward (Router) |
| No heuristic language guessing | Frozen |
| No arbitrary cross-language redirects | Frozen |
| No redirect chains | Frozen |
| No new global redirect registry / URL-history DB in A.SEOa | Frozen stop |
| Historical translated-slug redirects (SA6) | **Deferred** — requires ADR-authorized history mechanism or proven reuse of an existing owner |

## 4. Failure modes

| Failure | Safe behavior |
|---|---|
| Unknown prefixed path | WordPress 404 after strip — do not invent redirects |
| Ambiguous translated slug | Fail closed → source path / 404 — never guess object |
| Missing Store overlay | Use source slug in generated URLs |
