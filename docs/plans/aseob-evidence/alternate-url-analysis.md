# A.SEOb Evidence — Alternate URL Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `a1e91f442`  
**Depends on:** A.SEOa SA7 / SA10

---

## 1. URL identity (frozen by A.SEOa)

| Language | URL shape |
|---|---|
| Default (EN) | Unprefixed absolute path — e.g. `https://dev.biopentra.eu/a4-nested-gutenberg-fixture/` |
| Non-default (SV) | Prefixed — e.g. `https://dev.biopentra.eu/sv/a4-nested-gutenberg-fixture/` |
| Leaf slug | **Source** `post_name` / term slug (translated leaf slugs Deferred) |
| Preview | Capability-gated; not part of public alternate graph |

Live verification: EN/SV page and product return HTTP 200 with matching source leaf; double prefix 404; no redirect loops.

## 2. How alternates are computed today (Switcher)

[`Switcher::url_for`](../../../src/Frontend/Switcher.php):

- Default language: `raw_home` + unprefixed path
- Other: `raw_home` + `{code}` + path
- Path = current request path after Router strip

This is sufficient for singular pages/products under SA7. Archive/query strings: preserve query only when policy admits (plan must freeze: preserve safe query args that do not encode language; never invent archives for unpublished languages).

## 3. Forbidden alternate constructions

- Reverse lookup of translated slugs (A.SEOa Deferred)
- Cross-language redirect guessing
- HTML scraping of switcher markup
- Independent per-wave recomputation that diverges from SB11

## 4. Admission implication

Alternate absolute URLs for the public graph are **Supported** via SA7 + published language enumeration — no new identity family.
