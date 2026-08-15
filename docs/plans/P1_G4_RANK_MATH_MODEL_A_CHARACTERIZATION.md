# P1 G4 / Rank Math Model A Characterization — Frozen Specification

**Status:** **FROZEN** (A1–A5) — characterization in progress  
**Frozen:** 2026-08-15  
**Baseline main:** `81e688f2652733c58d96a36116d9b1164194be2a`  
**Version:** **1.5.1** · **TARGET:** **8** · **Migration:** NONE  
**P0:** COMPLETE  
**Environment:** DEV read-only only (`https://dev.biopentra.eu`) · Production **FORBIDDEN**  
**Product code:** **NO IMPLEMENTATION** · **NO RELEASE** · **NO DEPLOYMENT**

---

## 1. Purpose

Determine whether G4 (Rank Math sitemap / Model A) is:

- **A** NO SUPPORTED-CONTRACT DEFECT  
- **B** BOUNDED SUPPORTED-CONTRACT DEFECT  
- **C** DOCUMENTATION / TEST GAP ONLY (or residual INCONCLUSIVE evidence)  
- **D** ARCHITECTURE DECISION REQUIRED  

Final freeze of CASE A requires the A1–A5 bounded G4b DEV probe.

---

## 2. Frozen Model A contract (do not reopen)

Authorities: ADR-0023 §17, MSEO parent §4.9, MSEO.2 §22, ASEOE.3, v1.5.1 notes.

| Surface | Contract |
|---|---|
| **G4a / primary `<loc>`** | Rank Math **default/source** language only. **EXPECTED CONTRACT** that localized `/sv/...` primary locs are absent. |
| Localized primary locs | **NOT** part of Model A (forbidden redesign). |
| Sitemap xhtml | AIML overlay on Rank Math-owned `<url>` when `blog_public`, ≥2 public languages, SB11 SEO set ≥2 (discoverability omit). |
| Canonical emission | Rank Math / WP |
| Canonical value | AIML filters when invoked |
| Hreflang | AIML / SB11 / EffectiveUrl |
| x-default | Source/default absolute URL |

### Ownership map

| Artifact | Owner |
|---|---|
| Sitemap index / type XML / primary `<loc>` | Rank Math |
| `xhtml:link` + sitemap `x-default` | AIML `RankMathSitemapOverlay` → SB11 → EffectiveUrl |
| Document hreflang | AIML `DocumentSeoHead` |
| Canonical tag emission | Rank Math / WP |
| Canonical value filter | AIML |
| og:url reinforce | AIML via Rank Math hooks |

### Rank Math compatibility (relevant)

| Surface | Status |
|---|---|
| Meta text overlays | SUPPORTED |
| Document hreflang | SUPPORTED |
| Canonical value correction | SUPPORTED |
| Canonical tag emission | PARTIAL (emitter-owned) |
| og:url / locale | SUPPORTED |
| Primary loc localization | UNSUPPORTED (Model A) |
| Sitemap xhtml | SUPPORTED (requires RM entry) |
| Competing providers / loc rewrite | UNSUPPORTED |

### Object-type matrix

Same Model A for overlay types: `page`, `post`, `product`, `product_cat`, `product_tag`, `category`, `post_tag`, `author` — primary loc = Rank Math default; xhtml when entry + discoverable SB11 set.

---

## 3. G4 questions

| ID | Question | Pre-probe classification |
|---|---|---|
| **G4a** | Absence of localized primary `<loc>`? | **EXPECTED CONTRACT** |
| **G4b** | For RM-included eligible object, is xhtml present with EffectiveUrl? | **PENDING LIVE PROBE** |
| **G4c** | When xhtml emitted, identity = EffectiveUrl? | Expected YES (suite); confirm on probe if PASS |

Canonical sparse-tag: **EXPECTED / OWNER ABSENT** unless probe proves Supported-contract failure.

---

## 4. A1–A5 amendment (authoritative)

- One bounded **read-only** DEV G4b characterization  
- No DEV mutation; no production access  
- Model A frozen  
- CASE A only after evidence  
- Docs-only closure  

### G4b decision rules

| Outcome | Rule |
|---|---|
| **PASS** | RM includes object; qualifying discoverable target exists; xhtml present; href == EffectiveUrl; x-default OK |
| **EXPECTED OMIT** | RM includes object; target fails frozen discoverability → omission correct |
| **DEFECT** | RM includes object; qualifying discoverable target exists; xhtml absent or wrong URL |
| **INCONCLUSIVE** | No existing entry can satisfy conditions without mutation |

### CASE A freeze rule

Freeze **NO SUPPORTED-CONTRACT DEFECT** only if G4a EXPECTED and G4b PASS or EXPECTED OMIT and no other Supported defect.

DEFECT → **BOUNDED SUPPORTED-CONTRACT DEFECT FOUND** (boundary only; no fix).  
INCONCLUSIVE → **NO SUPPORTED-CONTRACT DEFECT PROVEN** (retain gap; prefer not block P2).

---

## 5. No-release policy

`MILESTONE CLOSURE != RELEASE CLOSURE`. Version stays **1.5.1**. No tag/release/deploy.

---

## 6. Probe / closure status

| Step | Status |
|---|---|
| Spec freeze | THIS DOCUMENT |
| G4b DEV probe | PENDING |
| Final verdict | PENDING |
| ROADMAP / PRODUCT_PRIORITIES | PENDING |
| Docs PR merge | PENDING |
