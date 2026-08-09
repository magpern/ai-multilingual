# A.SEOf Evidence — Machine-Readable Diagnostics Contract (SF13)

## Question

Can a reusable diagnostics result model exist inside frozen architecture without inventing SE11/SD12 or persistence?

## Evidence

- `BlockHealthSnapshot::to_array()` proves read-only structured health works for CLI/admin
- No SEO diagnostics class today
- SE11 (SitemapDiscovery) and SD12 (SocialMeta) remain **Deferred** — A.SEOf must not invent them as dependencies
- Parent forbids new SEO Jobs/TM pipelines and Store redesign for diagnostics

## Safe SF13 shape

Read-only in-memory result model (working name: SEO health snapshot / diagnostics report), containing:

- scan options (object scope, sample vs bounded set)
- check results (id, status pass/warn/fail/skip, ownership attribution, message codes)
- summary counts
- limitations / honesty flags (`blog_public=0`, HTML canonical omitted, etc.)
- no secrets, no translation bodies, no unbounded ID dumps

**Not** a persistent health table. **Not** a new identity family. **Not** TARGET bump.

**Disposition:** SF13 **Supported** as lightweight read-only contract; distinct from Deferred SE11/SD12.
