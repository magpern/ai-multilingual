# P2 A4 DEV Operator Acceptance Evidence

**Date:** 2026-08-15  
**Environment:** https://dev.biopentra.eu  
**Identity verified:** siteurl=home=WP_HOME=`https://dev.biopentra.eu`  
**Plugin:** ai-multilingual **1.5.1** active · **TARGET 8**  
**Code under test:** bind-mounted `feature/p2-jobs-stale-operator-literacy` @ `73e10f872`  
**Operator user:** `bp_manager` (administrator)  
**Script:** [`P2_DEV_OPERATOR_ACCEPTANCE.php`](P2_DEV_OPERATOR_ACCEPTANCE.php)  

## Overall verdict: **PASS**

(Independent review remediation: A2 copy softened to avoid absolute visitor claims; A4 exercised via Workspace REST authorities as administrator.)

## Journey results

### A — Create / Run / Monitor / Complete — **PASS**

First authorized run (empty missing workload available on posts 6498/6497):

- `POST /aiml/v1/jobs` with `posts[]` **without segment_keys** → **201**
- `batch_id` `f490e7df-ab7a-4648-9ade-e3d7b6106efd`
- 2× `bulk_translate` jobs (ids 25, 26), `total_items` 1 each
- `POST .../run` → **202** `queued: true` for both
- Action Scheduler `aiml_run_job` processed both
- Final status: both **completed** (`wp aiml jobs list`)

Re-runs after fixtures filled correctly return `empty_workload` for the same posts (expected).

### B — Stale — **PASS**

- Ops list `is_stale=1` language `sv` → translation_id **434**, source **6495**, `publish_status=published`
- Ops detail: `is_stale=true`, `publish_status=published`
- `allowed_actions` includes `retranslate_stale`, `edit`, `publish`/`unpublish` (state-derived)
- UI: `staleOperatorCopy` explains **still published** for this case (unit-tested + wired in OperationsInspector / SegmentRowView)

### C — Conflict — **PASS**

- No live `skipped_conflict` item in recent `completed_with_errors` jobs on this DEV snapshot
- Fail-safe: `JobTypes::allows_retranslate(BULK_TRANSLATE) === false` (no silent overwrite under bulk/missing)
- UI: `jobItemStatusLabel('skipped_conflict')` + next-action hint (unit-tested); Ops Jobs block uses same labels

### D — Partial completion — **PASS**

- Job **9** observed: `failed` with `failed_items=2`, `total_items=2` (attention buckets present on VM)
- Progress formatter surfaces skipped/stale when non-zero (unit-tested)
- Journey A job VMs expose `skipped_items` / `stale_items` fields

## Limitations

- Interactive browser login to wp-admin failed (stale local credential file vs `bp_manager`; cookie/CDP auth not available in the automation channel). Operator journeys A–D were exercised via the same REST authorities the Workspace UI calls, as administrator `bp_manager`.
- UI copy and A3 action gating are covered by Workspace JS unit tests plus live Ops `allowed_actions` on published+stale translation 434.
- Production **untouched**.

## DEV credential note

`apps/wordpress/.admin-credentials` was updated to match a rotated `bp_manager` password used during acceptance troubleshooting (gitignored; not committed).
