# A.SEOb Evidence — WordPress Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `a1e91f442`

---

## 1. Canonical infrastructure

| API | Role |
|---|---|
| `get_permalink` / `get_canonical_url` | Absolute singular URLs |
| `rel_canonical` on `wp_head` | Core emitter (removed by Rank Math when active) |
| `redirect_canonical` | Corrective redirects; AIML currently blind-suppresses when prefixed |

## 2. Language awareness gap

Core canonical/redirect APIs are **not** multilingual. After AIML prefix-strip, core “forgets” the language prefix — hence suppress. A.SEOb must restore correct language-aware behavior without registering rewrite rules or reopening ADR-0002.

## 3. Head emission

`wp_head` remains the official AIML emission point for hreflang when Rank Math does not own that surface.

## 4. Admission implication

WordPress remains owner of permalink + redirect infrastructure. AIML overlays policy only.
