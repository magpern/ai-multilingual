# MSEO browser acceptance (local / non-CI)

Authoritative **checklist** for MSEO.5 Gate A (MSEO5.3). Live Playwright is optional and **not** a CI gate.

## Environment

- Base URL: `https://dev.biopentra.eu` (or local equivalent)
- AIML active; languages include a published secondary (e.g. `sv`)
- `localized_urls_state` exercisable via Settings → Localized URLs
- Auth: reuse F9 cookies under `acceptance/f9-browser/artifacts/` when using Playwright helpers

## Strategy F preflight (before Gutenberg overlay judgments)

Record from WP options / CLI:

- `aiml_rollout_config`
- `rollout_render_enabled`
- `general_rollout_enabled`
- `allowed_post_ids`
- `rollout_stage`

Classify overlay misses as product defect **or** expected rollout denial.

## Checklist

| # | Scenario | Pass? | Notes |
|---|---|---|---|
| 1 | Flat post/page: publish slug route → localized URL 200 when ON | | |
| 2 | Hierarchical page: ancestor leaf + own leaf localized path | | |
| 3 | Category / tag archive localized path (admitted taxonomies) | | |
| 4 | `product_cat` / `product_tag` archive when admitted | | |
| 5 | Woo plain product localized URL | | |
| 6 | Woo `%product_cat%` product URL when admitted + fingerprint matches (else document harness/config skip) | | |
| 7 | Source-slug language URL → one 301 to localized when ON + active | | |
| 8 | Historical localized path → one 301 to current localized when ON | | |
| 9 | Language switcher URLs agree with hreflang set | | |
| 10 | Document canonical uses effective localized URL when discoverable | | |
| 11 | hreflang / x-default present and reciprocal | | |
| 12 | Rank Math sitemap: default `<loc>` unchanged; xhtml:link alternates present (Model A) | | |
| 13 | Stale / unpublished / draft: no public localized advertisement | | |
| 14 | Cart/checkout/account endpoints not localized | | |
| 15 | Disable generation: old localized paths 302 to source-slug language URL | | |

## Optional Playwright

Not required for Gate A merge. If added later, keep under this directory and document soft-skips for missing fixtures.

## Result log

Record date, operator, environment, Strategy F snapshot, and checklist outcomes in Gate A evidence / dogfood report as applicable.
