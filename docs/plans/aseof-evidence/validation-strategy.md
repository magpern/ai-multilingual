# A.SEOf Evidence — Validation Strategy

## Automated

- Unit: validators against SB11 fixtures; snapshot shape; preview exclusion; ownership attribution
- Integration: page/product EN–SV; Rank Math inactive skip; blog_public honesty; Deferred SE11/SD12 guards; SF14 does not embed SEO rules
- PluginGuard / PHPCS on touched PHP
- A.SEOa–e regression suites remain green

## Live / observational

- Respect `blog_public=0`
- Non-persistent overrides only when proving sitemap emission capability
- Confirm `/sv/` home loop still reported as pre-existing debt when detected
- FP = 0 for AIML-attributed failures; foreign dual-title must not count as AIML fail

## Philosophy

Contract validation primary; emission validation bounded secondary.
