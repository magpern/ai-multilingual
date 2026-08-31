# Site Translate — Full DEV operator acceptance (pending)

**Status:** **NOT YET COMPLETE**  
**Environment:** https://dev.biopentra.eu (DEV only — production forbidden)  
**Prerequisite:** Implementation milestone closed — PR #60 merged (`4d581a21f`), feature head `4fe809957`  
**Scope:** Operator-led walkthrough only — **no implementation** unless a defect is found  

This task completes the end-to-end operator confidence gap left by bounded implementation acceptance ([`SITE_TRANSLATE_DEV_ACCEPTANCE.md`](SITE_TRANSLATE_DEV_ACCEPTANCE.md)). It is **not** a reopening of the implementation milestone.

## Acceptance state (current)

| Layer | Verdict |
|---|---|
| Implementation milestone | **CLOSED — PASS** |
| Automated + bounded DEV verification | **PASS** |
| Full operator-led Swedish workflow | **PENDING** |
| Release readiness | **WAIT** until this walkthrough passes |

## Checklist (frozen plan §10)

Use reversible fixtures where needed. Do not destroy pre-existing operator data. Clean up disposable fixtures after.

1. [ ] Target `sv` in **Preview** while preparing
2. [ ] Strategy F incomplete: Gutenberg selection **blocked**; classic-only selection **allowed**
3. [ ] Create Site Translate batch from Workspace picker
4. [ ] >50 chunking (fixture or documented prior CI evidence) — optional if already satisfied by integration test record
5. [ ] **Run batch now** enqueues (does not synchronously translate entire batch in one HTTP request)
6. [ ] Jobs batch visibility / progress in Workspace
7. [ ] Translations remain **unpublished** after AI run
8. [ ] Promote `sv` to **Published** before anonymous acceptance
9. [ ] Localized URLs **OFF**
10. [ ] Anonymous `/sv/<source-slug>/` shows **source-language holdback** before segment Publish
11. [ ] Manual segment **Publish**
12. [ ] Swedish overlay visible after publish
13. [ ] Enable Localized URLs → state reaches **On**
14. [ ] Automatic route generation rejects **unpublished title**
15. [ ] Automatic route generation rejects **stale published title** (`title_stale`)
16. [ ] Valid current published title generates candidate
17. [ ] Route publication succeeds for eligible item
18. [ ] Localized Swedish **EffectiveUrl** works
19. [ ] Collision recovery path understandable (edit / clear / retry — no slug-2 auto-increment)
20. [ ] Rank Math literal SEO spot-check where fixture supports it
21. [ ] hreflang / canonical / og:url / switcher spot-check per existing architecture
22. [ ] Source/default routes remain correct; no anonymous-language cache regression

## When complete

Update this file (or replace with `SITE_TRANSLATE_DEV_OPERATOR_ACCEPTANCE.md` evidence) with date, operator identity, step results, and overall **PASS/FAIL**. Only then authorize a separate **release-readiness assessment**.

## Explicit non-actions

- Production (`biopentra.eu`): do not access or modify
- Release tag / GitHub Release: not part of this task
- Reopening PR #60 or the implementation milestone unless a defect requires a fix PR
