# A.R1 Permanent Deny-List (Elementor)

**Status:** Architectural research deliverable  
**Source of truth (narrative):** [docs/plans/AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md](../../docs/plans/AR1_ELEMENTOR_IDENTITY_RESEARCH_LOG.md) Appendix A  
**Recommendation context:** CONDITIONAL GO — deny-list is mandatory alongside any first surface  
**Not a production allow-list.** Items leave this list only through **new evidence** (and adapter graduation where applicable).

Source fallback: deny-listed values remain **untranslated (source)**.

---

## By reason

### Ownership ambiguity

- Global/template widgets without stable `template_id` (or equivalent)
- Theme/chrome widgets with unclear ownership (`biopentra_header_auth`, `mega-menu`, similar)

### Unstable identity

- Values without field/control-level identity
- Structural-path-only identities (Candidate C)

### Dynamic runtime

- Dynamic-tag settings (`__dynamic__`)
- `loop-grid` (and similar) query-generated cell content

### Third-party persistence

- WooCommerce Elementor widgets (`woocommerce-*`)
- `fluent-form-widget`
- Other unresearched third-party Elementor widgets

### Unsupported Elementor behavior

- `html` controls
- `shortcode` controls

### Architectural contract

- AIML identity embedded in Elementor persistence (Candidate B) under current governance
- HTML/DOM scraping as identity or primary render path

### Performance / cache / security-privacy

- No widget family deny-only for performance from A.R1 measurements; cache/language isolation remains a design gate for future support
- No translation of secrets or unsanitized customer PII

---

## Governance

Future milestones **remove** entries through evidence — they do not replace this deny-list with an unstructured allow-only policy.

---

## A.3 graduation records (evidence-backed removals / subset admissions)

Admitted to production allowlist via A.3 (`feature/a3-elementor-widget-coverage`); see [A3_ELEMENTOR_WIDGET_COVERAGE_VALIDATION_LOG.md](../../docs/plans/A3_ELEMENTOR_WIDGET_COVERAGE_VALIDATION_LOG.md).

| Family | Disposition | Notes |
|---|---|---|
| accordion (`tab_title`, `tab_content`) | Graduated | Nested `_id` identity; adapter strategy |
| toggle (`tab_title`, `tab_content`) | Graduated | Same model; missing `_id` → source |
| image (`caption` when `caption_source=custom`) | Graduated subset | Media Library alt/attachment caption remain denied |
| icon-list (`text`) | Graduated | Nested `_id` on `icon_list` |
| call-to-action (`title`, `description`, `button`) | Graduated | Flat document controls |

Still denied (unchanged): Theme Builder / globals, loop-grid cells, Woo Elementor widgets, Fluent Forms, html/shortcode, dynamic tags, Candidate B, HTML scrape.
