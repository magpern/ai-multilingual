# A.SEOb Evidence — Hreflang Analysis

**Status:** Planning freeze evidence  
**Baseline:** `main` @ `a1e91f442`

---

## 1. Current state

| Emitter | Document `<link rel="alternate" hreflang>` | Notes |
|---|---|---|
| AIML | **Absent** | Switcher adds `hreflang` on **UI anchors** only ([`Switcher.php`](../../../src/Frontend/Switcher.php)) |
| Rank Math | **Absent** (live) | No multilingual alternate tags observed |
| WordPress core | **Absent** | No core hreflang |
| Theme / Elementor | **Absent** | — |

Live EN/SV pages: RSS/oEmbed `rel="alternate"` only — not language alternates.

## 2. Correct hreflang model (architecture)

For each indexable document in a **published** language:

- Emit one `rel="alternate" hreflang="{BCP47}"` per published language, pointing at that language’s SA7 absolute URL for the **same source object / path**.
- Emit `hreflang="x-default"` per frozen **SB4** policy (default language URL).
- Reciprocity: every language page in the set must declare the same full alternate set.
- Preview languages: **never** included (ADR-0008 / SB9).
- No fuzzy matching; no inventing alternate URLs for objects that do not resolve under SA7.

## 3. Emission mechanism

Official: `wp_head` (or Rank Math head coordination if Rank Math later adds competing tags — cooperation rule: AIML owns multilingual relationship emission; suppress/coordinate duplicates without scraping).

Values come from the **SB11 language-relationship contract**, not from Switcher HTML.

## 4. Admission implication

**SB3** Supported — document hreflang generation is a net-new AIML overlay using existing language/URL primitives.  
**SB4** Supported — `x-default` = default language absolute URL (Languages `is_default`).  
**SB8** Supported — canonical URL for current language must equal that language’s entry in the hreflang set.
