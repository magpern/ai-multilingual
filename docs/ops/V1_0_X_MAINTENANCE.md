# Platform v1.0.x — standing maintenance cadence

How routine fixes enter after P1 without roadmap churn.

## In cadence (no roadmap revision)

- Security patches
- Provider compatibility quirks (e.g. model parameter differences) confined to provider classes
- Action Scheduler / host cron operational fixes
- Documentation drift repairs (HOOKS, ops runbooks)
- Acceptance harness bugfixes under `acceptance/p1/`
- Dependency pin bumps that do not break PluginGuard / PHP 8.1 floor

Ship as **v1.0.x** when production code changes; docs-only may land on `main` without a tag.

## Out of cadence (needs roadmap milestone)

- Elementor / nested identity / Woo coverage / SDKs / Intelligence features / Workspace UX programs
- Schema TARGET > 6
- Breaking F10/F11 REST or frozen platform principles → ADR + typically v2.0

## Verification before merge

- Tier 0 green
- Relevant `acceptance/p1/*` harnesses PASS on designated environment
- OpenAI RC baseline when AI behaviour changes
