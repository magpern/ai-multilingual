# A.SEOf Evidence — Admin / CLI Feasibility (SF14 + CLI)

## CLI

Precedent: `wp aiml block status` (sample / full-scan / JSON).

Expected: `wp aiml seo status` (or equivalent under `aiml` namespace) calling shared diagnostics core → same SF13 result model.

## Admin (SF14)

Feasible as Multilingual submenu or Workspace panel consuming SF13 JSON/array.

### SF14 UI rule (hard — freeze)

The admin SEO health UI must be a thin presentation layer over the frozen machine-readable diagnostics contract.

It must not:

- independently evaluate canonical/hreflang/social/sitemap rules
- crawl URLs on its own
- maintain separate health state
- introduce separate thresholds or scoring semantics

All SEO health logic belongs in the shared diagnostics core. CLI, REST, and admin consumers must observe the same result model.

**Disposition:** SF14 **Supported** under the UI rule; CLI **Supported** as peer consumer of SF13.
