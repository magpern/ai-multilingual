# A.SEOf — SEO Diagnostics / Validation / Health — Implementation Plan

**Status:** **Implementation complete — review-ready** on `feature/aseof-seo-diagnostics` (not merged/tagged)
**Milestone:** Program A — **A.SEOf** (final wave of A.SEO)
**Plan freeze:** Evidence-driven admissions SF1–SF15; diagnostics observe A.SEOa–e contracts; no second SEO pipeline; SF13 read-only result model; SF14 thin UI over SF13/SF1; TARGET **6**; Supported = **SF1–SF14**; Partially Supported = **SF15** (advisory); SE11/SD12 remain Deferred upstream
**ADR assessment:** **No new ADR required** for the Supported set if Implementation stays on read-only diagnostics + SB11 + Integration API v1 + BlockHealth-like bounded scans without persistence/schema/TARGET changes
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.3 / A.SEO family
**Parent architecture:** [ASEO_PARENT_IMPLEMENTATION_PLAN.md](ASEO_PARENT_IMPLEMENTATION_PLAN.md) (authoritative; not redesigned)
**Dependency matrix:** [A_SEO_DEPENDENCY_MATRIX.md](A_SEO_DEPENDENCY_MATRIX.md)
**Evidence:** [aseof-evidence/](aseof-evidence/)
**Planning branch:** `feature/aseof-seo-diagnostics-plan`
**Implementation branch:** `feature/aseof-seo-diagnostics`
**Validation log:** [ASEOF_SEO_DIAGNOSTICS_IMPLEMENTATION_VALIDATION_LOG.md](ASEOF_SEO_DIAGNOSTICS_IMPLEMENTATION_VALIDATION_LOG.md)
**Baseline (plan authoring):** `main` @ `fbc719a78184098e9292aa78f2c90670bda57474`
**Depends on:** A.SEOa–A.SEOe **Complete** (`a-seoe-sitemaps-complete`); ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; Integration API v1; TARGET **6**; SB11

**Operational success (Supported):** Operators can run bounded SEO health checks (CLI/admin/REST as admitted) that validate A.SEOa–e contracts and report emission honesty — without mutating SEO ownership, without a second emitter pipeline, with preview/indexability honesty, and with a single shared SF13 result model.

**This plan is the frozen implementation contract for A.SEOf.** Do not widen Supported admissions without new evidence + ADR where gated. Do not open an implementation branch until this plan is independently reviewed and merged to `main`.

---

## 1. Purpose

Freeze how AIML provides **SEO diagnostics, validation, health, and verification readiness** over the completed A.SEOa–A.SEOe architecture — as observer tooling that reuses AIML Diagnostics conventions.

A.SEOf does **not**:

- redefine or reimplement SEO emitters
- invent SE11 SitemapDiscovery or SD12 SocialMeta
- fix Router/front-page redirect ownership
- annex Rank Math/theme dual-title ownership
- integrate Search Console APIs or store Google credentials
- become a site-wide uncontrolled crawler or SEO Jobs product

A.SEOf **does**:

- freeze SF1–SF15 admissions from evidence
- define contract-vs-emission validation philosophy
- define SF13 machine-readable results and SF14 thin UI rule
- define ASEOF.0–ASEOF.8 work packages for the admitted set

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| Working tree clean; `main` == `origin/main` | **Pass** (`fbc719a78`) |
| A.SEO parent plan + dependency matrix on `main` | **Pass** |
| A.SEOa–A.SEOe Complete + tags including `a-seoe-sitemaps-complete` | **Pass** |
| SB11 + DocumentSeoHead + RankMathIntegration + RankMathSitemapOverlay present | **Pass** |
| No pre-existing SEO diagnostics product class | **Pass** |
| TARGET = **6** | **Pass** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 Accepted | **Pass** |
| Integration API v1 unchanged | **Pass** |

---

## 3. Frozen upstream contracts

| Contract | A.SEOf usage |
|---|---|
| A.SEOa SA7 / SA10 | Expected language URLs; preview URL policy |
| A.SEOb SB11 | Sole language-relationship authority |
| A.SEOb DocumentSeoHead | Hreflang emission ownership (AIML) |
| A.SEOc/d RankMathIntegration | Title/meta/schema/OG/Twitter overlay seams |
| A.SEOe RankMathSitemapOverlay | Sitemap discovery honesty overlays |
| Integration API v1 / PluginIdentity | Rank Math compatibility; no new identity |
| Store / Router / LanguageContext / PreviewService | Reuse; no redesign |
| TARGET | **6** — no bump |
| Diagnostics conventions | BlockHealth / IntegrationDiagnostics / ops doc |

**Forbidden:** new identity family; Store/schema redesign; TARGET bump; second SEO pipeline; inventing SE11/SD12; Router fix milestone scope.

---

## 4. Implementation boundary

A.SEOf owns only:

- SEO health summary
- contract validators (canonical/hreflang/relationships/social/sitemap/robots/preview/conflicts/compat)
- bounded emission / redirect checks
- SF13 result model
- SF14 thin admin presentation
- CLI/REST consumers of SF13
- SF15 advisory readiness checklist

It must **not** mutate: slugs, canonical, hreflang, Rank Math meta, schema, OG/Twitter, sitemaps, robots, Store rows, language relationships.

---

## 5. Ownership model

| Actor | Owns |
|---|---|
| Rank Math / WP / Woo | SEO emission & persistence (unchanged) |
| AIML A.SEOa–e | Existing overlays/contracts (unchanged) |
| AIML A.SEOf | Read-only diagnostics/reporting over those contracts |
| Operator UI/CLI | Presentation / invocation only |

---

## 6. Diagnostics philosophy

1. **Observe** authoritative services and official filter seams
2. **Compare** expected contract state to bounded observations
3. **Report** with ownership attribution and honesty limitations
4. **Never** become a second semantic implementation of SEO rules
5. Prefer structured state over HTML scraping; scrape only as bounded emission proof

---

## 7. Contract validation vs emission validation

| Mode | Authority | Use |
|---|---|---|
| **Contract validation (primary)** | SB11, SA7, DocumentSeoHead expectations, Rank Math compatibility, sitemap overlay policy | Pass/fail of frozen architecture |
| **Emission validation (secondary)** | Bounded HTML/XML/HTTP observation | Drift detection; honesty flags when environment suppresses emission (`blog_public=0`, noindex) |

HTML/XML observation must not invent relationships or guess URLs.

---

## 8. Admission matrix (frozen)

See [aseof-evidence/admission-matrix.md](aseof-evidence/admission-matrix.md).

| Disposition | IDs |
|---|---|
| **Supported** | SF1, SF2, SF3, SF4, SF5, SF6, SF7, SF8, SF9, SF10, SF11, SF12, SF13, SF14 |
| **Partially Supported** | SF15 (advisory readiness; API/submit Deferred) |

### SF14 UI rule (hard)

The admin SEO health UI must be a thin presentation layer over the frozen machine-readable diagnostics contract.

It must not:

- independently evaluate canonical/hreflang/social/sitemap rules
- crawl URLs on its own
- maintain separate health state
- introduce separate thresholds or scoring semantics

All SEO health logic belongs in the shared diagnostics core. CLI, REST, and admin consumers must observe the same result model.

---

## 9. Diagnostics lifecycle

```text
Operator invokes CLI | admin | REST
  → Seo diagnostics core (SF13)
      → Contract validators (SF2–SF5, SF8, SF10–SF12)
      → Bounded emission / redirect / sitemap honesty (SF6–SF7, SF9)
      → Aggregate summary (SF1)
  → Immutable result snapshot
  → Consumer renders / prints / returns JSON
```

Missing Rank Math / inactive integration → skip RM-owned checks with `skip` status; never fatal.

---

## 10. Machine-readable diagnostics model (SF13)

Freeze a read-only snapshot (name at implementation discretion) with at least:

- `scan_mode` / scope options
- `checks[]`: `{ id, status, ownership, code, message, evidence? }`
- `summary`: pass/warn/fail/skip counts
- `limitations[]`: honesty flags (e.g. `blog_public_zero`, `html_canonical_omitted`)
- `generated_at`

No DB table. No option-backed crawl history required for Supported set. No new identity family.

SF13 is **not** SE11 and **not** SD12.

---

## 11. Admin / CLI / REST integration

| Consumer | Role |
|---|---|
| CLI (`wp aiml seo …`) | Invoke core; print human/JSON SF13 |
| Admin submenu under Multilingual | Thin SF14 presentation of SF13/SF1 |
| Optional `aiml/v1` SEO diagnostics route | Same SF13 payload; capability-gated |

Capabilities: align with existing operator caps (`manage_options` and/or established AIML caps) — exact cap freeze in ASEOF.5 without inventing public unauthenticated SEO scan APIs.

---

## 12. Performance and boundedness

| Limit | Policy |
|---|---|
| Default scope | Current object / small sample |
| Redirect depth | Hard max (implementation constant; e.g. ≤10) |
| HTTP fetches per scan | Hard max; prefer zero when contract-only |
| Full site crawl | Deferred unless BlockHealth-style sync sample suffices |
| SEO Jobs / AS workers | Not introduced in A.SEOf Supported set |
| Admin auto-refresh crawl | Forbidden |

---

## 13. Security / privacy

- No secrets, API keys, translation bodies, or PII dumps in results
- Capability gates on admin/REST/CLI
- No unauthenticated public SEO scan endpoint
- Preview detection must not expose preview content bodies

---

## 14. Failure behavior

| Condition | Behavior |
|---|---|
| Rank Math missing/inactive | Skip RM checks; report skip |
| SB11 empty / single language | Relationship checks skip or limited |
| `blog_public=0` | Honesty limitation; do not force sitemap language enrichment for green |
| Redirect loop detected | Report fail/warn; do not rewrite Router |
| Dual title foreign | Report with foreign ownership; not AIML fail |
| Validator exception | Capture as check error; never fatal PHP for operators |

---

## 15. Work packages (ASEOF.0–ASEOF.8)

### ASEOF.0 — Baseline / architecture lock

| | |
|---|---|
| **Objective** | Lock baseline SHAs, live honesty facts, upstream tags into validation log |
| **Scope** | Docs |
| **Deps** | Plan freeze on `main` |
| **Likely files** | validation log |
| **Validation** | Inventory notes |
| **Rollback** | Revert docs |
| **Stop** | A.SEOe incomplete / TARGET drift |
| **Commit** | `docs(seo): establish A.SEOf baseline` |

### ASEOF.1 — Evidence / admissions freeze

| | |
|---|---|
| **Objective** | Confirm SF dispositions; Deferred SE11/SD12 guards |
| **Deps** | ASEOF.0 |
| **Likely files** | `src/Seo/*` diagnostics stubs wiring plan only after freeze |
| **Validation** | Admission matrix tests/guards |
| **Stop** | Requires Store redesign |
| **Commit** | `feat(seo): lock A.SEOf diagnostics admissions` |

### ASEOF.2 — Diagnostics contract / core (SF13)

| | |
|---|---|
| **Objective** | Implement read-only result model + orchestration service |
| **Deps** | ASEOF.1 |
| **Likely files** | `src/Seo/` diagnostics snapshot/service (names TBD in impl) |
| **Validation** | Unit snapshot shape; no persistence |
| **Stop** | Persistence required for Supported claim |
| **Commit** | `feat(seo): add A.SEOf machine-readable diagnostics core` |

### ASEOF.3 — SEO contract validators (SF2–SF5, SF8, SF10–SF12)

| | |
|---|---|
| **Objective** | SB11/RM/DocumentSeoHead contract checks + ownership attribution |
| **Deps** | ASEOF.2 |
| **Validation** | Unit/integration EN–SV; preview exclusion; Rank Math inactive skip |
| **Commit** | `feat(seo): implement A.SEOf SEO contract validators` |

### ASEOF.4 — Bounded emission / redirect / discovery (SF6–SF7, SF9)

| | |
|---|---|
| **Objective** | Sitemap honesty, robots/indexability, bounded redirect-loop detection |
| **Deps** | ASEOF.3 |
| **Validation** | blog_public honesty; `/sv/` loop report; no Router mutation |
| **Commit** | `feat(seo): implement A.SEOf emission and routing health checks` |

### ASEOF.5 — Admin / CLI / summary (SF1, SF14, CLI; optional REST)

| | |
|---|---|
| **Objective** | Consumers of SF13; SF1 summary; enforce SF14 UI rule |
| **Deps** | ASEOF.2–4 |
| **Likely files** | `src/Cli.php`, `src/Admin/*`, optional REST |
| **Validation** | UI has no independent SEO rule engine; CLI JSON == core |
| **Commit** | `feat(seo): expose A.SEOf diagnostics via CLI and admin` |

### ASEOF.6 — Deferred guards / hardening

| | |
|---|---|
| **Objective** | Prove SE11/SD12/GSC-API/persistence not invented; caps/security |
| **Deps** | ASEOF.5 |
| **Commit** | `test(seo): guard A.SEOf Deferred surfaces` |

### ASEOF.7 — Acceptance / regression / live

| | |
|---|---|
| **Objective** | Full suites; live honesty; A.SEOa–e regression |
| **Deps** | ASEOF.6 |
| **Commit** | validation evidence updates |

### ASEOF.8 — Documentation closure

| | |
|---|---|
| **Objective** | Factual Complete status after merge/tag (later closure task) |
| **Deps** | ASEOF.7 |
| **Commit** | `docs(seo): close A.SEOf SEO diagnostics` (post-merge closure only) |

---

## 16. Architectural acceptance criteria

1. TARGET remains **6**.
2. Store schema unchanged.
3. Integration API v1 unchanged.
4. No new identity family.
5. No SEO emitter ownership stolen.
6. No second SEO pipeline.
7. SB11 consumed unchanged.
8. A.SEOa–e contracts unchanged.
9. SE11 remains Deferred / uninvented.
10. SD12 remains Deferred / uninvented.
11. SF13 is read-only (no diagnostics persistence table).
12. SF14 UI performs zero independent SEO evaluations.
13. CLI / REST / admin share one SF13 model.
14. SF1 summary derived only from SF13 checks.
15. SF2 Supported — canonical contract validation.
16. SF3 Supported — hreflang reciprocity.
17. SF4 Supported — language-relationship validation.
18. SF5 Supported — admitted social overlays.
19. SF6 Supported — sitemap discovery honesty validation.
20. SF7 Supported — robots/indexability honesty.
21. SF8 Supported — preview leakage detection.
22. SF9 Supported — bounded redirect-loop report; no Router fix.
23. SF10 Supported — ownership-aware conflict detection.
24. SF11 Supported — Woo product/product_cat validation path.
25. SF12 Supported — Rank Math compatibility health.
26. SF13 Supported — machine-readable snapshot.
27. SF14 Supported under UI rule.
28. SF15 Partially Supported — advisory only; no GSC API.
29. `blog_public=0` reported honestly; not overridden for green.
30. Preview languages absent from public expected sets.
31. Dual title attributed foreign when pre-existing.
32. `/sv/` home loop detectable without being “fixed”.
33. Redirect depth bounded.
34. HTTP fetches bounded / optional.
35. No site-wide uncontrolled crawl in Supported set.
36. No SEO-specific Jobs pipeline.
37. No secrets in diagnostics output.
38. Capability gates on admin/REST.
39. Rank Math inactive → skip, never fatal.
40. Missing hooks → skip surface.
41. FP for AIML-attributed failures = **0** in acceptance fixtures.
42. Preview leakage = **0**.
43. No URL guessing / fuzzy matching.
44. No parallel relationship graph.
45. HTML scrape not source of truth.
46. Unit suite green.
47. Integration suite green.
48. PluginGuard pass.
49. PHPCS pass on touched PHP.
50. `git diff --check` clean.
51. A.SEOa regression green.
52. A.SEOb / SB11 regression green.
53. A.SEOc regression green.
54. A.SEOd regression green.
55. A.SEOe regression green.
56. Woo / Gutenberg / Elementor / Fluent Forms / A.6 suites not weakened.
57. Validation log records baseline + final dispositions.
58. Roadmap pointers factual — no milestone renumbering.
59. Implementation boundary (observe-only) preserved.
60. SF14 UI rule preserved.

---

## 17. Regression strategy

- Keep A.SEOa–e unit/integration suites green
- Add Aseof* characterization + Deferred guards (no SitemapDiscovery/SocialMeta/persistence)
- Live observational checks respect `blog_public=0`
- PluginGuard structural invariants

---

## 18. Stop conditions

**Candidate-local defer** if: no safe contract seam; emission check requires unbounded crawl; media/ownership ambiguous; SF14 cannot stay presentation-only.

**Milestone STOP** if meaningful support requires: Store/schema/TARGET bump; new identity; second SEO pipeline; inventing SE11/SD12; persistent crawl subsystem; Search Console credentials architecture; Router redesign as part of A.SEOf.

Ordinary defects: fix, test, continue.

---

## 19. Out of scope

- Fixing `/sv/` homepage 301 self-loop
- Annexing dual `<title>` ownership
- Search Console API / OAuth / auto-submit
- Inventing SE11 / SD12
- SEO Jobs / AS workers
- PRODUCT_PRIORITIES structural edits
- Any A.SEOa–e emitter redesign

---

## 20. ADR assessment

**No new ADR required** for Supported SF1–SF14 + Partial SF15 if Implementation stays on:

- existing SB11 / DocumentSeoHead / RankMathIntegration / RankMathSitemapOverlay
- Integration API v1 compatibility
- BlockHealth-like read-only snapshots
- existing admin/CLI/REST capability patterns

Do not reopen ADR-0001 / 0002 / 0008 / 0017.

If persistence, public unauthenticated scan API, or external Google account storage becomes required → defer that surface or author a focused ADR **before** coding (not in this planning task).

---

## 21. Rollback strategy

| Layer | Rollback |
|---|---|
| Docs planning | Revert planning branch commits |
| Implementation (future) | Feature-flag / remove diagnostics registration; emitters remain intact |
| Bad check logic | Disable check id; SF13 continues |

Diagnostics are add-only; rolling back A.SEOf must not remove A.SEOa–e behavior.

---

## 22. Document control

| Version | Date | Notes |
|---|---|---|
| 0.1 | 2026-08-09 | Planning freeze on `feature/aseof-seo-diagnostics-plan`; baseline `fbc719a78`; SF1–SF15 dispositions frozen for review; SF14 UI rule hard-frozen |
| 0.2 | 2026-08-09 | Implementation complete — review-ready on `feature/aseof-seo-diagnostics`; validation log PASS; not merged/tagged |
