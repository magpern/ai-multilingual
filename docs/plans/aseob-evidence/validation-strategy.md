# A.SEOb Evidence — Validation Strategy

**Status:** Planning freeze evidence  
**Applies to:** Supported SB1–SB11 only

---

## 1. Quality gates

| Gate | Requirement |
|---|---|
| Unit | Green |
| Integration | Green |
| PluginGuard | Green |
| PHPCS | Touched PHP clean |
| `git diff --check` | Pass |
| Live / HTTP | EN/SV canonical + hreflang reciprocity |

## 2. Functional matrix (minimum)

| Check | Pass criteria |
|---|---|
| EN canonical | Unprefixed absolute URL; matches SB11 current for default language |
| SV canonical | Prefixed absolute URL; matches SB11 current for SV |
| hreflang reciprocity | Every published language page emits the full alternate set |
| x-default | Points at default language absolute URL |
| Preview exclusion | Preview language absent from public hreflang; capability gating unchanged |
| Duplicate canonical | At most one document canonical (Rank Math **or** WP path — not both) |
| Duplicate hreflang | No conflicting duplicate alternate sets |
| Missing reciprocal | Detected by SB10 tests / diagnostics hooks (UI later) |
| Orphan language | Published language without URL → fail closed (omit or fail test; never guess) |
| Language leakage | EN body/lang vs SV body/lang; FP=0 |
| redirect_canonical | Prefixed never stripped to unprefixed; no loops |
| Woo product | EN/SV canonical + hreflang correct under SA7 |
| Rank Math active/inactive | Cooperation paths both covered in tests |
| A.SEOa regression | SA7/SA10 unchanged |
| Gutenberg / Elementor / Fluent / A.6 / Woo A.7* | Suite regression green |

## 3. SB11 consumer contract tests

Prove A.SEOc–A.SEOf **could** call the same API unchanged (even if those waves are not implemented): snapshot of relationship records stable for a fixture page; preview excluded; default unprefixed; no Store/schema side effects.

## 4. Performance

Observe head emission cost; no invented budgets; no relationship caching subsystem without ADR.

## 5. Environment note

dev.biopentra.eu may be globally `noindex` (Rank Math omits canonical while noindex). Validation must still assert **computed** canonical values via filters/APIs, and assert hreflang tags in HTML when emission is active.
