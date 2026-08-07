# A.4 — Nested Gutenberg — Validation Log

**Milestone:** A.4 Nested Gutenberg  
**Implementation branch:** `feature/a4-nested-gutenberg`  
**Plan:** [A4_NESTED_GUTENBERG_IMPLEMENTATION_PLAN.md](A4_NESTED_GUTENBERG_IMPLEMENTATION_PLAN.md) (**Architecture Frozen**)  
**ADR:** [0013-gutenberg-segment-identity.md](../adr/0013-gutenberg-segment-identity.md) (**Accepted** — no new ADR)  
**Evidence:** [A4_NESTED_GUTENBERG_IDENTITY_RESEARCH_LOG.md](A4_NESTED_GUTENBERG_IDENTITY_RESEARCH_LOG.md) (**CONDITIONAL GO**; F5 **PASS**)  
**Baseline main (pre-plan merge):** `39f41ebdd335725b9e74f534670d101f601728f8`  
**Plan merge commit:** `7ea2aed7ec566618762328d573584ed9cfccb87a`  
**Research tag:** `ar2-nested-gutenberg-identity-research-complete`  
**Closure status:** In progress  

---

## A4.0 — Baseline / F5 contract verification

**Status:** PASS (docs / baseline only — no production behavior change at A4.0)

### Environment

| Item | Value |
|---|---|
| Host | https://dev.biopentra.eu |
| Grammar | `b:<uuid>:<field>` |
| UUID attr | `aimlBlockId` |
| Production leaves | paragraph, heading, button, list-item, preformatted, verse, code |
| Walker | `BlockTreeWalker` DFS `innerBlocks` |
| F5 | PASS for bounded surface (A.R2) |

### Contract verification

| Check | Result |
|---|---|
| F5 CONDITIONAL GO evidence present | PASS |
| `b:` grammar unchanged (`Contract::SEGMENT_KEY_GRAMMAR`) | PASS |
| `BlockTreeWalker` recursion intact | PASS |
| BlockRegistry / AdapterRegistry ownership intact | PASS |
| Seven production leaves unchanged | PASS |
| No nested container adapters in production | PASS |
| Elementor A.2/A.3 surface untouched at A4.0 | PASS |
| No A.4 production `src/` changes at A4.0 start | PASS |

### Baseline quality gates (A4.0)

| Gate | Result |
|---|---|
| Unit | 491 tests, 1182 assertions — OK (2 skipped) |
| Integration | _pending A4.0 close / recorded below_ |
| PluginGuard | _pending_ |
| PHPCS | _pending_ |
| `git diff --check` | PASS (clean tree at branch open) |

---

## A4.1 — Eligibility / structural transparency

**Status:** pending

---

## A4.2 — List / list-item admission

**Status:** pending

---

## A4.3 — Structural container child traversal

**Status:** pending

---

## A4.4 — Host container child traversal

**Status:** pending

---

## A4.5 — Diagnostics + regression hardening

**Status:** pending

---

## A4.6 — Performance / render safety

**Status:** pending

---

## A4.7 — Full Tier 0 + targeted acceptance

**Status:** pending

---

## A4.8 — Closure

**Status:** pending
