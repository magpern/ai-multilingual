# AI Multilingual Platform v1.0.0 RC — OpenAI End-to-End Validation

**Verdict:** **PASSED**
**Date:** 2026-08-06
**Repo:** `/opt/biopentra/dev/ai-multilingual`
**Branch:** `main`
**Site:** `https://dev.biopentra.eu`
**Schema:** 6 / 6
**Harness:** `acceptance/rc/v1-openai-rc.php`
**Final run:** **56/56 PASS**

---

## Environment

| Item | Value |
|---|---|
| WordPress | live stack `dev.biopentra.eu` |
| Plugin | AI Multilingual (bind-mounted from repo) |
| Action Scheduler | available |
| Glossary / Review / Jobs | wired; tables present |
| Diagnostics | jobs / glossary / review endpoints OK |
| Fixture content | dedicated RC pages (`aiml-v1-rc-openai-validation`, long-doc companion) |

---

## Provider

| Item | Value |
|---|---|
| Provider | `openai` |
| Model | `gpt-5-mini` |
| API base | `https://api.openai.com/v1` |
| `ai_enabled` | `true` |
| `ai_api_key_encrypted` | non-empty (`aiml1:` ciphertext; never printed) |
| Conservative job budgets | `budget_max_requests ≤ 3`, `budget_max_tokens ≤ 8000` on create fixtures |

---

## Prerequisite checkpoint

| Check | Result |
|---|---|
| `ai_enabled` / `openai` / model / encrypted key | **PASS** |
| Schema 6 | **PASS** |
| AS healthy | **PASS** |
| Minimal `test-connection` | **PASS** |

---

## Defects found and fixed

| Defect | Severity | Fix |
|---|---|---|
| `gpt-5-mini` rejects `temperature: 0.2` (`Only the default (1) value is supported`) | Blocker for live translate | `OpenAIProvider` omits `temperature` for `gpt-5*` and `o*` models; unit tests added |
| Earlier RC stop: missing `model.request` scope | Ops | Resolved by updated API key permissions (operator) |

No architecture redesign. Public contracts unchanged.

---

## Test matrix

| # | Area | Result | Notes |
|---|---|---|---|
| 1 | Manual translation | **PASS** | Store `machine_translated`; ~7 s |
| 2 | Glossary | **PASS** | Create + fragment contains terms; translate with fragment |
| 3 | Translation Memory | **PASS** | Human-edit → approve → TM `origin=human` write-back |
| 4 | Review Workflow | **PASS** | submit / approve / reject / resubmit |
| 5 | Background Jobs | **PASS** | create / pause / resume / sync execute / retry endpoint / cancel |
| 6 | Budget handling | **PASS** | create rejected `workload_limit_exceeded` under tiny budget |
| 7 | Rate-limit | **PASS** | isolated simulated 429 → WP_Error |
| 8 | Provider outage | **PASS** | isolated simulated 503 → WP_Error |
| 9 | Invalid API key | **PASS** | isolated provider → WP_Error |
| 10 | Network timeout | **PASS** | isolated timeout → WP_Error |
| 11 | Long document | **PASS** | ~34.5 s; tokens in=335 out=2536 |
| 12 | Multiple languages | **PASS** | en + sv configured on site |
| 13 | Diagnostics | **PASS** | jobs / glossary / review; no secret shapes |
| 14 | Audit logging | **PASS** | 6 job audit events; no secret shapes |
| 15 | CLI | **PASS** | `wp eval-file` harness |
| 16 | REST | **PASS** | providers/active, jobs health/diagnostics, workspace translate/review/jobs |
| 17 | Workspace UI | **PASS** | prior admin session: Translate / Review / Jobs tabs present |
| 18 | Browser / public render | **PASS** | `/sv/` HTTP 200; fatal FP count 0 |

---

## Performance (final successful run)

Measured values only (no invented targets):

| Metric | Value |
|---|---|
| Paid request count (harness counter) | **5** |
| Observed OpenAI HTTP calls | **7** (includes connection/models + isolated invalid-key probe) |
| Total prompt tokens (captured completions) | **593** |
| Total completion tokens | **3901** |
| `test_connection` | 20014 ms |
| Manual translate | 7063 ms |
| Glossary-influenced translate | 16742 ms |
| Suggest | 4130 ms |
| Job sync execute | 8410 ms |
| Long document | 34538 ms |
| Average latency (recorded ops) | **15150 ms** |
| p95 latency (recorded ops) | **34538 ms** |
| Job throughput | 1 selected-segment job completed sync in ~8.4 s |

---

## Security

| Check | Result |
|---|---|
| API key never printed / logged in evidence | **PASS** |
| Diagnostics contain no key/Bearer shapes | **PASS** |
| Audit payloads contain no secret shapes | **PASS** |
| Prompts / raw completions not archived in this doc | **PASS** |

---

## Compatibility

| Area | Result |
|---|---|
| Store / TM / Glossary / Review / Jobs tables | intact |
| Review ownership | unchanged (approve requires review cap + edit_post) |
| Glossary ownership | unchanged |
| TM write-back policy | unchanged (skips pure `machine_translated`; human edit required) |
| Render / UUID / Rollout | no regressions observed (`/sv/` 200, FP=0) |

---

## Remaining notes (non-blocking)

- Site has only `en` + `sv`; multi-language beyond that not configured.
- `gpt-5-mini` latency is high vs classic chat models; ops should budget timeouts (≥60 s already set on provider HTTP).
- Temperature omission for gpt-5/o-series is intentional compatibility; gpt-4* still sends `0.2`.

---

## Final recommendation

OpenAI live integration is validated on `dev.biopentra.eu` with schema 6 and the full RC matrix green after the temperature compatibility fix.

**AI Multilingual Platform v1.0.0 RC validation PASSED.**
