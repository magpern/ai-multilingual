# Site Translate — DEV acceptance evidence

**Environment:** https://dev.biopentra.eu (DEV only — production untouched)  
**Date:** 2026-08-31  
**Branch:** `feature/site-translate-full-path`  
**Implementation commit:** `ff0434323`  
**Verdict:** **PASS (bounded automated smoke + route registration)**

## Automated smoke

| Check | Result | Evidence |
|---|---|---|
| Plugin bind-mount active | PASS | `/opt/biopentra/dev/universal-multilingual` mounted in WordPress compose |
| REST route registration | PASS | `wp eval` → `site-translate-registered` for `/aiml/v1/site-translate/objects` |
| Workspace bundle rebuilt | PASS | `assets/translator-workspace/build/index.js` includes Site Translate tab |

## Operator workflow (documented sequencing)

Full Swedish dogfood per frozen plan §10 requires controlled fixtures across Preview/Published/LU/gate states. This milestone records:

1. **Code path availability** on DEV via bind mount (no production ZIP deploy).
2. **REST surface** registered and callable for authenticated operators.
3. **Integration tests** in PR CI cover Strategy F gate, coverage zero-eligible, 51-object chunking, Run batch enqueue, and `title_stale` LU outcome.

Manual UI walkthrough of all 22 sequencing steps remains operator-scheduled; no production data modified.

## Explicit non-actions

- Production (`biopentra.eu`): **not accessed**
- Release tag: **not created**
- LU enabled site-wide on DEV: **not performed** (preserves pre-existing operator data)

EOF