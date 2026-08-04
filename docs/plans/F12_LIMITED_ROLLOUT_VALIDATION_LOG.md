# F12 Limited Rollout Validation Log

**Status:** In progress — WP7 performance baseline recorded; staging validation pending  
**Branch:** `feature/f12-limited-rollout`  
**Environment:** dev.biopentra.eu (staging validation WP10 pending)

---

## Tier 0 quality gates (implementation)

| Gate | Command | Latest |
|---|---|---|
| PHPUnit unit | `vendor/bin/phpunit -c phpunit.xml.dist` | Rollout subset PASS |
| PHPUnit integration | `vendor/bin/phpunit -c phpunit-integration.xml.dist` | Rollout subset PASS |
| PHPCS | `vendor/bin/phpcs` | Pending full run |
| F9 35-test suite | — | **Not run** (per F12 policy) |

---

## WP7 — Performance measurement plan (evidence fields)

Measurements to be captured on dev before cache activation GO. **No invented SLOs.**

| Surface | Cold median | Warm median | p95 | Query count | Memory delta | Sample size | Content size | Regression % allowed | Tech owner | PO owner | Evidence |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Frontend allow path | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | WP10 |
| Frontend deny path | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | WP10 |
| Shadow path | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | WP10 |
| Policy evaluation overhead | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | — | WP10 |
| Metrics flush/rollup | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | — | WP10 |
| Workspace load regression vs F11 | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | TBD | WP10 |

Reference baseline: [F11_PERFORMANCE_BASELINE.md](F11_PERFORMANCE_BASELINE.md)

---

## Cache activation decision

| Item | State |
|---|---|
| Cache implemented (WP8) | Yes — default **off** |
| Measured GO for activation | **Pending** — no PO approval yet |
| `render_cache_enabled` in production | **false** |

---

## Staging validation (WP10)

Reserved — populate after targeted F12 browser smoke on dev.biopentra.eu.

---

## F12 closure

**Not PASS** — real observation window and operator sign-off pending.
