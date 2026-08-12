# TSC.2 Registered Meta Translation Surfaces — Planning Freeze Validation Log

**Status:** Planning freeze in progress (docs branch)
**Authoritative plan:** [TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md](TSC2_REGISTERED_META_SURFACES_IMPLEMENTATION_PLAN.md)
**Parent:** [TSC_PARENT_IMPLEMENTATION_PLAN.md](TSC_PARENT_IMPLEMENTATION_PLAN.md)
**ADR:** **None**

## Freeze record

| Field | Value |
|---|---|
| Planning baseline main HEAD | `2d51bd2def983bf6a8078ea1ada8fbea7ef3f0ba` |
| Baseline drift | None; `main` == `origin/main` at branch creation; version **1.3.0**; TARGET **7** |
| Planning branch | `docs/tsc2-registered-meta-surfaces-planning-freeze` |
| Plan source | Externally reviewed amended Cursor plan · verdict **TSC.2 PLAN REVIEW: FREEZE** (ten amendments) |
| Materialization commit | `f7169cd53fe89afdc3c5846da905e2b3d0e99013` |
| Final reviewed planning HEAD | `e56348ed65df6bfcd512591946854f21867c260f` (pin tip); review content `e54f4780b7b81ef72ced15697adcdba82b19b6a9` |
| External freeze review | **FREEZE** · STATE A · TARGET 7 |
| Independent planning review | **PASS** |
| Planning PR | *(pending)* |
| Planning CI | *(pending)* |
| Freeze merge | *(pending)* |
| Fresh main CI | *(pending)* |
| Closure commit | *(pending)* |
| Plugin version | **1.3.0** (unchanged) |
| TARGET | **7** (unchanged) |
| Schema / migration | None (STATE A) |
| New ADR | **None** |
| Production implementation | **NOT STARTED** |
| Tag | No new tag; existing `v1.3.0` unchanged |

## External amendments incorporated

1. Registry subordinate to SurfaceCapability (field catalog only)
2. No generic production `filter:{hook}` overlay engine
3. Hardened native `m:` identity / collision / mode-switch rules
4. No artificial production ceiling of 32 (O(R) characterization only)
5. CASE A/B/C lifecycle; `retain_segment_keys` on `Store::sync_source`
6. Distinct `extract_store_capable` / `provider_allowed` / `overlay_capable`
7. Rank Math definition module single source of truth for six SEO keys
8. Explicit TSC.1 term Jobs regression contract
9. `field_key=_meta`; identity on `segment_key`
10. Honest product-value claims (no generic custom-field / ACF)

## STATE A reasoning

- Meta units are segments under existing `post` / `term` BIGINT host identities — Store uniqueness admits them without migration.
- Optional `$retain_segment_keys` on `sync_source` is a behavioral API extension, not a schema change.
- No durable registration table; inactive definitions remain code-catalog entries with `activation=false`.
- No `SOURCE_META`; no TARGET bump.

## TARGET / schema verdict

**STATE A · TARGET 7 · no migration · no durable registration table.**

## ADR verdict

**No new ADR.** Direct application of parent §16, ADR-0001/0005/0007, TSC.0 Surface spine, ADR-0017 (`p:` for Rank Math), ADR-0021 (term host).

## Matrices frozen

| Matrix | Count |
|---|---|
| RM | RM1–RM34 |
| AC | AC1–AC32 |
| WP | TSC2.0–TSC2.7 |

## Consistency checks (materialization)

| Check | Result |
|---|---|
| CASE A/B/C consistency | Active empty → orphan; inactive → retain untouched; code removal → intentional orphan |
| SurfaceCapability ownership | Catalog does not decide admission/auth/publicness/Jobs/publish/OTL mutate |
| Rank Math ownership | Catalog owns six keys + invalidation/security facts; Integration owns `p:`/literal/social/overlay/sitemap |
| provider_allowed ownership | Deny-by-default fact; TI.6 consumes; no second Jobs policy engine; sibling segments unaffected |
| Term Jobs regression | Eight explicit regressions frozen (RM21 / AC22–25) |
| STOP audit | No redesign; STATE A holds |

## Lightweight checks performed (documentation-only)

| Check | Result |
|---|---|
| `git fetch` / `main` == `origin/main` | PASS @ `2d51bd2de…` |
| Version / TARGET | **1.3.0** / **7** |
| Plan path / parent links | Present |
| RM1–RM34 contiguous count | **34** |
| AC1–AC32 contiguous count | **32** |
| TSC2.0–TSC2.7 count | **8** |
| Symbol references (Store, SurfaceCapability, RankMathIntegration, PluginIdentity) | Match repository |
| No opportunistic Deferred/Unsupported broadening | Confirmed vs external FREEZE |
| Full PHP unit / integration / quality / build / browser suites | **Not run locally** — documentation-only materialization; repository CI at PR/merge gates applies |

**Explicit policy:** Expensive production implementation suites were **not** required locally for documentation-only planning materialization.

## Independent planning review

**Verdict:** `TSC.2 PLANNING FREEZE REVIEW: PASS`

Independent review of the materialized repository plan (not a rubber-stamp of the earlier external FREEZE). Falsified against current Store / Surface / Rank Math / Jobs / field_key conventions on baseline `2d51bd2de…`.

### Checklist

| ID | Challenge | Result |
|---|---|---|
| 1 | RegisteredMetaRegistry accidentally a second Surface registry? | **PASS** — authority capped to field catalog; SurfaceCapability owns admission/auth/publicness |
| 2 | retain_segment_keys preserve inactive rows without corrupting CASE A/C? | **PASS** — missing∩retain = skip orphan; missing∉retain = orphan; present in segments = normal sync |
| 3 | CASE B rows genuinely untouched (not partial mutate)? | **PASS** — freeze requires no status/error_code/updated_at/source-hash mutation on retain path |
| 4 | Inactive Rank Math → retained `p:` identities deterministic? | **PASS** after tighten-up — PluginIdentity rebuild for direct keys + existing Store∩rankmath family for host-emitted term SEO |
| 5 | Permanent code-definition removal honest? | **PASS** — CASE C; code deletion is retirement signal; no durable table |
| 6 | provider_allowed consumed by TI.6 without second Jobs policy engine? | **PASS** — fact only; ItemProcessor-style skip analogous to allow_provider=false |
| 7 | Provider-disallowed coexist with eligible siblings in one job? | **PASS** — segment-level skip; AC14 |
| 8 | Term Jobs preserves TSC.1 adoption/authority? | **PASS** — RM21 eight regressions; Rank Math term stays adopt/host |
| 9 | Rank Math key ownership single-source? | **PASS** — definition module owns six keys; adapters derive; drift tests |
| 10 | `_meta` field_key consistent with Store/TM/semantic? | **PASS** — family bucket parallel `_plugin`/`_elementor`; identity on segment_key |
| 11 | `m:` collision/rename deterministic? | **PASS** — bootstrap reject; no silent rename/mode-switch |
| 12 | Production frontend avoids generic meta interception? | **PASS** — Integration overlays + reference adapters only; no filter:{hook} engine |
| 13 | STATE A needs no schema/TARGET change? | **PASS** — optional sync_source arg only |
| 14 | Product claims broader than delivery? | **PASS** — honest Rank Math + architecture proof; AC32 |

### Defects found

One non-blocking documentation gap: CASE B retain computation for Rank Math originally under-specified host-emitted `p:rankmath:term:*` rows on shop/`page_for_posts`. Clarified retain formula (PluginIdentity ∪ Store∩family). CASE B “untouched” semantics made explicit (no orphan-branch column writes).

### Fixes applied (docs branch)

1. §14 retain_keys computation table for `native_m` vs `external_p` (including host-emitted Rank Math term SEO).  
2. Explicit “genuinely untouched” / no orphan-branch mutations for CASE B.  
3. Independent review status set to **PASS** on plan header.

No production code changes. STATE A remains valid — no redesign.

## Planning closure

*(Filled after merge to main.)*
