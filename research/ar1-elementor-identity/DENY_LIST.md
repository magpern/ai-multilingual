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
