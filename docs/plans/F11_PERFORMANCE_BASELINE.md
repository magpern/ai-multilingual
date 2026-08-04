# F11 Performance Baseline (pre-F12)

Documentation-only architecture baseline for Strategy F F11 as shipped on
`feature/f11-translation-memory-ai` / tag `strategy-f-f11-tm-ai-complete`.

**Do not treat these as measured SLOs.** They are the expected production
measurement plan and architectural cost model for F12 comparison. No
optimization was performed in F11 for this document.

---

## 1. Purpose

F12 (limited rollout) will introduce cohort flags, operational telemetry, and
optional caching. Before changing runtime behaviour, capture:

| Dimension | Why |
|---|---|
| Workspace load | Translator time-to-edit |
| TM lookup | Reuse latency vs corpus size |
| AI suggestion | Provider RTT + validation |
| Save latency | QA + Store + TM write-back/usage |
| Preview latency | Production render path (unchanged F10) |
| Batch operations | Cap 50; partial success |
| Database queries | Store + `aiml_tm` |
| Memory usage | PHP workers under batch/suggest |

---

## 2. Architectural cost model (as implemented)

| Operation | Hot path | Expected dominant cost | Notes |
|---|---|---|---|
| Workspace load | extract → sync → assemble → attach `meta.suggestions` + `meta.qa` | N segments × (TM exact + optional fuzzy) + QA evaluate | Suggestions attached on GET; fuzzy capped (≤20 DB candidates) |
| TM exact lookup | `source_hash` + lang pair + context index | Single indexed read | Budget &lt;100ms/segment in plan §10 |
| TM fuzzy lookup | Candidate query + PHP similarity | Cap 20 candidates; score in PHP | Ambiguity gate for short strings |
| AI suggest | `AISuggestionProvider` → provider HTTP | Network RTT | No Store write; rate limit 30/min/user (plan) |
| AI translate | `TranslationService` → provider → Store | Network + Store write | **No** TM write-back on machine persist |
| Save segment | QAEngine → Store save → TM write-back/usage → reload + meta | QA checks (CPU) + Store write + TM upsert/usage | TM sync after Store success (F11.1) |
| Preview | `PreviewService` → public URL | HTTP fetch of production route | Unchanged from F10 |
| Batch save/translate/accept/QA | Loop ≤50 items | Linear in N; partial success | Sync only; async deferred |
| Provider settings | CredentialVault decrypt | Negligible vs network | Keys never in JS |

---

## 3. Recommended production measurements (F12)

Instrument (or manually sample on staging) after F11 merge:

| Metric | Suggested method | Capture |
|---|---|---|
| Workspace GET `segments` p50/p95 | Server Timing or WP-CLI `rest_do_request` + microtime | By segment count buckets (10 / 50 / 200) |
| TM exact hit latency | Log around `lookup_exact` (debug only) | Cold vs warm object cache |
| TM fuzzy latency | Same for `lookup_fuzzy` | Corpus size at sample time |
| Suggest RTT | Client Network panel + server span | Per profile; NullAI vs configured |
| Save latency | REST POST segment timing | With/without QA errors |
| Preview TTFB | `curl -sI` / browser | `/sv/{slug}/` |
| Batch 50 translate | REST translate | Success/partial/failed mix |
| SQL count | Query Monitor or `SAVEQUERIES` sample | Per workspace load |
| PHP memory peak | `memory_get_peak_usage` around batch | Worker size |

**Baselines to record once on `dev.biopentra.eu` post-merge** (operator fill-in):

| Metric | Environment | Value | Date | Commit/tag |
|---|---|---|---|---|
| Workspace load (~2 segs, post 6321) | | _TBD_ | | |
| Workspace load (~50 segs) | | _TBD_ | | |
| TM exact lookup | | _TBD_ | | |
| TM fuzzy lookup | | _TBD_ | | |
| AI suggest (if key configured) | | _TBD_ | | |
| Save segment | | _TBD_ | | |
| Preview TTFB | | _TBD_ | | |
| Batch 10 save | | _TBD_ | | |
| Peak RSS during batch | | _TBD_ | | |

---

## 4. Database / growth expectations

| Store | Growth driver | F11 policy |
|---|---|---|
| `aiml_translations` | Segment saves | Unchanged |
| `aiml_tm` | Eligible write-backs (once wired) | No auto-purge in F11; CLI stats still deferred |
| Object cache | Segment + language reads | Existing Cache collaborator |

F12 may add render result caching and operational metrics dashboards — **not** translator productivity metrics (§18 of F11 plan remain optional/diagnostics-only).

---

## 5. Non-goals

- No speculative indexes or query rewrites in this document
- No new APM vendor requirement
- No change to sync batch cap (50) without ADR
