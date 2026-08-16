# AI Multilingual v1.6.0 — Release Closure

**Status:** **TAGGED / GITHUB RELEASE PUBLISHED**  
**Version:** 1.6.0  
**Schema TARGET:** **8** (unchanged)  
**Migration:** **NONE**  
**Release-ready commit (tagged):** `417df7a5b8df3121aedd5fff0b03ae79cc728290`  
**Annotated tag:** `v1.6.0`  
**Preparation branch:** `release/v1.6.0-preparation` (PR #50)  
**Release workflow:** `31938252543` SUCCESS  
**GitHub Release:** https://github.com/magpern/ai-multilingual/releases/tag/v1.6.0  
**Previous release:** `v1.5.1` @ `6298df08b3b1456e4875ecdb860b71506d5ae313` (unmoved)

## Preflight (tag time)

| # | Field | Value |
|---|---|---|
| 1 | Pre-release / docs-tip main HEAD | `b1dcc105925ad4c4efcb3d4a2a49bdccfc7723b4` |
| 2 | Release-ready commit | `417df7a5b8df3121aedd5fff0b03ae79cc728290` |
| 3 | Working tree | Clean; `main` == `origin/main` |
| 4 | Drift after release-ready | Docs-only (prep evidence tip commits) — no production-code drift |
| 5 | Version @ release-ready | **1.6.0** |
| 6 | `Migrator::TARGET` @ release-ready | **8** |
| 7 | Migration / `step_9` | Absent / **NONE** |
| 8 | Tag `v1.6.0` before task | Did not exist |
| 9 | Tag `v1.5.1` | Unchanged @ `6298df08b3b1456e4875ecdb860b71506d5ae313` |
| 10 | GitHub Release `v1.6.0` before task | Did not exist |
| 11 | Release-preparation review | **PASS** — [V1_6_0_RELEASE_PREPARATION_REVIEW.md](V1_6_0_RELEASE_PREPARATION_REVIEW.md) |
| 12 | Release PR | https://github.com/magpern/ai-multilingual/pull/50 |

## Tag

| # | Field | Value |
|---|---|---|
| 3–5 | Tag / type / message | `v1.6.0` · Annotated · `AI Multilingual v1.6.0` (+ body) |
| 4 | Tag target SHA | **`417df7a5b8df3121aedd5fff0b03ae79cc728290`** |
| | Tag object | `33c2be33e3b003697f657b9453b5ea7bda10adda` |
| | Push | SUCCESS — origin `refs/tags/v1.6.0` |

**Do not tag** docs tip `b1dcc1059…`. **Do not move** this tag for later docs-only closure commits.

## Release workflow

| # | Field | Value |
|---|---|---|
| 6–7 | Workflow run / result | `31938252543` · **SUCCESS** |
| | URL | https://github.com/magpern/ai-multilingual/actions/runs/31938252543 |
| | Built from | tag `v1.6.0` / commit `417df7a5b8df3121aedd5fff0b03ae79cc728290` |

## GitHub Release

| # | Field | Value |
|---|---|---|
| 8 | URL | https://github.com/magpern/ai-multilingual/releases/tag/v1.6.0 |
| | Title / tag | `v1.6.0` / `v1.6.0` |
| | Draft / prerelease | **false** / **false** |
| 20 | Authoritative notes | [v1.6.0.md](v1.6.0.md) (operator train; G4 EXPECTED OMIT scoped to DEV `blog_public=0`) |
| | Workflow body | Auto-generated compare `v1.5.1...v1.6.0` (PR list; no unsupported sitemap claims) |

## Published GitHub Release asset (source of truth)

Independently downloaded from the GitHub Release (not the local release-prep ZIP).

| # | Field | Value |
|---|---|---|
| 9 | Filename | `ai-multilingual-1.6.0.zip` |
| 10 | Byte size | **771505** |
| 11 | Entry count | **480** |
| 12 | SHA-256 (independent) | `be4f108a2ac9f10b9ca8f6c641450cc2980e6541b6884997740c038d415c0715` |
| 13 | Prepared ZIP SHA-256 | `af945dbb3bc4a9dd76ce30037cd5546542a6ef3795ca4de63ac1de3d5754ac4a` (771477 bytes, 480 entries) |
| 14 | Artifact identity | **Workflow-rebuilt** (not byte-identical to prep ZIP) |
| 15 | Package audit (`bin/audit-zip.sh`) | **PASS** |
| | Plugin header / `AIML_VERSION` | **1.6.0** |
| 17–18 | `Migrator::TARGET` / migration | **8** / **NONE** |
| | `assets/term-slug-admin/` | Present |
| | Secrets / tests / docs junk | None found |
| | Composer root reference | `417df7a5b8df3121aedd5fff0b03ae79cc728290` (`pretty_version` `v1.6.0`) |

### Difference vs release-preparation artifact

| | Release-prep (local) | Published (GitHub) |
|---|---|---|
| Size | 771477 | **771505** |
| SHA-256 | `af945dbb3bc4a9dd76ce30037cd5546542a6ef3795ca4de63ac1de3d5754ac4a` | `be4f108a2ac9f10b9ca8f6c641450cc2980e6541b6884997740c038d415c0715` |
| Entries | 480 | 480 |

**Expected:** CI rebuilds the archive on the annotated tag; `vendor/composer/installed.php` records tag version/reference. Same entry set and audited runtime identity. **Not a failure.**

## Programs / deployment

| # | Field | Value |
|---|---|---|
| 21 | Production-touch audit | **UNTOUCHED** — no SSH/WP-CLI/file/DB mutation of `biopentra.eu` |
| 22 | DEV deployment/install | **NOT PERFORMED** (bind-mount already serves repo code) |
| 23 | Production deployment | **NOT PERFORMED** |
| 24 | P0 | **COMPLETE** (shipped in 1.6.0) |
| 25 | P1 | **COMPLETE** (characterization; no new sitemap runtime) |
| 26 | P2 | **COMPLETE** (shipped in 1.6.0) |
| 27 | P3 | **NOT AUTHORIZED / NOT STARTED** |
| 19 | `v1.5.1` tag | Unmoved @ `6298df08b3b1456e4875ecdb860b71506d5ae313` |

## Tag vs closure

The tag **`v1.6.0` remains on `417df7a5b8df3121aedd5fff0b03ae79cc728290`** and is not moved for this closure documentation commit.

## Exact next step

**28:** DEV product use of the combined v1.6.0 feature train, then **fresh roadmap prioritization**. **P3 not authorized.**

## Closure commit

Docs-only closure tip includes `7defc715f3c3cd8d387284d42db6b0c11a09b95b` (primary) and tip `75affc6abd9b346d6665c5b961f783c326b99a1e`. **Does not move tag `v1.6.0`.**
