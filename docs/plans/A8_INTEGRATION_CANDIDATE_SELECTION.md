# A.8 — Integration Candidate Selection Matrix

**Status:** Selection locked for A.8 planning  
**Canonical implementation plan:** [A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md](A8_FLUENTFORMS_CONTACT_INTEGRATION_IMPLEMENTATION_PLAN.md)  
**Environment:** https://dev.biopentra.eu (live WP-CLI inventory)  
**Baseline:** `main` @ `5d51a69ada67be7c2d7048aaf16e9a11d2ea789a`

---

## 1. Selection principle

Prefer a plugin with:

- small bounded visitor-facing surface
- deterministic persistence and stable record IDs
- official hooks/filters
- low dynamic behavior
- no shared-definition ambiguity under current Store lookup
- no HTML scraping
- easy fixtures
- low operational risk

**Do not** use WooCommerce as the first Integration API v1 proof (A.7 family).

---

## 2. Candidates inspected (evidence-based)

| Plugin | Slug | Version (dev) | Visitor surface | Persistence | Notes |
|---|---|---|---|---|---|
| Fluent Forms | `fluentform` | 6.2.9 | Forms / fields / submit | `wp_fluentform_forms` + JSON `form_fields` | Form **#5** on Contact |
| Age Gate | `age-gate` | (active) | Headline / button / messages | Options (`age_gate_messages`) | Gate currently **disabled** (`age_gate_tools.disable_age_gate = 1`) |
| CookieYes | `cookie-law-info` | (active) | Cookie banner copy | Options + JS template / cloud | No content overlay filters found |
| WooCommerce | `woocommerce` | (active) | Products / carts / emails | CPT + meta | Explicitly out of A.8 |
| Rank Math | `seo-by-rank-math` | (active) | Titles / meta | Post meta | A.SEO lane |
| Elementor | `elementor` | (active) | Widgets | Post meta | Already A.2/A.3 |
| Biopentra storefront / loop-card | first-party | (active) | Theme/product UI | Plugin options / templates | First-party; not third-party proof |
| AIML reference fixture | `aiml_reference` | test | Synthetic | Test fixture | Not production |

---

## 3. Scoring matrix (1–10; higher = better for A.8)

Weights favor **Integration API v1 proof** over raw merchant value.

| Criterion | Fluent Forms | Age Gate | CookieYes | WooCommerce |
|---|---:|---:|---:|---:|
| Merchant value | 7 | 5 | 8 | 10 |
| Deterministic identity | 9 | 8 | 4 | 5 |
| Ownership clarity | 9 | 4* | 5 | 4 |
| Hook quality | 9 | 5 | 2 | 6 |
| Implementation complexity (inverse) | 6 | 8 | 3 | 2 |
| Lifecycle complexity (inverse) | 7 | 7 | 4 | 3 |
| Dynamic-content risk (inverse) | 7 | 8 | 3 | 2 |
| Testing ease | 8 | 6 | 3 | 3 |
| Compatibility risk (inverse) | 7 | 7 | 4 | 3 |
| Value as Integration API v1 proof | 9 | 6* | 3 | 4 |
| **Composite (avg)** | **7.8** | **6.4** | **3.9** | **4.2** |

\*Age Gate ownership/proof scores are capped because shared-definition options do not fit post-scoped Store overlay without a framework extension (stop-condition adjacent).

---

## 4. Recommendation

| Role | Choice |
|---|---|
| **Chosen** | **Fluent Forms** — Contact Form **#5** only |
| **Runner-up** | Age Gate — defer until shared-definition / site-scoped overlay is designed without weakening ADR-0017 |
| **Deferred** | CookieYes — wait for official content hooks or a dedicated ADR if scraping becomes unavoidable |
| **Rejected for A.8** | WooCommerce (+ BTCPay / multicurrency / inventory add-ons) |
| **Out of lane** | Rank Math (A.SEO), Elementor/Gutenberg (already covered) |
| **Deferred first-party** | biopentra-storefront / loop-card |

### Why Fluent Forms

1. Form ID is a stable record owner (`owner_type=form`, `owner_id=5`).
2. Official rendering filters exist for field data / submit button.
3. Fits existing `extract_for_post` + Store `(source_id = post_id)` without schema change.
4. Live Contact fixture with three deterministic labels.
5. Proves real registration, compatibility, `p:` identity, extract, overlay, Workspace, Review, TM, Glossary, Jobs — without commerce sprawl.

### Why not Age Gate first

Age Gate messages are **global shared-definition**. Overlay on arbitrary gated pages cannot safely resolve translations through current Store lookup without site-scoped resolution or per-post duplication. That conflicts with “no ADR-0017 / Store redesign” for the first bridge.

### Why not CookieYes

No official content overlay filters; visitor copy is JS/`#ckyBannerTemplate` / cloud-backed. Would force scraping or unscoped buffering — stop conditions.

### Why not WooCommerce

Roadmap and product scope place Woo under A.7. Too broad for a framework proof.

---

## 5. Frozen production surface (chosen)

See implementation plan §4–§6:

```text
p:fluentform:form:5:full_name:label
p:fluentform:form:5:email:label
p:fluentform:form:5:submit_text
```

Everything else deferred.

---

## 6. Stop-condition check (pre-implementation)

| Stop condition | Fluent Forms form 5 labels? |
|---|---|
| New Store schema | No |
| New Integration API / ADR-0017 change | No |
| Generic HTML scraping | No (official filters) |
| Fuzzy identity | No |
| Foreign persistence mutation | No |
| Plugin-specific translation/Jobs pipeline | No |
| Unrestricted crawling | No (allowlist 3 fields) |
| Broad shared-definition ownership | No (record-owned form) |
| Complex dynamic-runtime identity | No |

**Pass** — proceed to architecture freeze / A80 after plan merge.
