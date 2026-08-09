# A.SEOf Evidence — Existing Diagnostics Architecture

**Baseline:** `main` @ `fbc719a78`

---

## Patterns to reuse

| Component | Path | Relevance |
|---|---|---|
| `BlockHealthService` + `BlockHealthSnapshot` | `src/Block/` | Best template: read-only scan → immutable snapshot → `to_array()` for CLI/admin |
| `IntegrationDiagnostics` | `src/Integration/` | Request-scoped closed counters; no bodies/secrets |
| Jobs / Glossary / Review diagnostics REST | `aiml/v1/*` | Capability-gated JSON health |
| `wp aiml block status` | `src/Cli.php` | Sample / full-scan / JSON CLI precedent |
| Ops doc | `docs/ops/DIAGNOSTICS_AND_HEALTH.md` | "Do not invent a second diagnostics product" |
| Admin menus | Multilingual (`SettingsPage::MENU_SLUG`) | Submenu placement for thin SEO health UI |

## SEO diagnostics today

`src/Seo/` contains only:

- `LanguageRelationshipService` / `LanguageRelationship` (SB11)
- `DocumentSeoHead`

**No** `SeoDiagnosticsService`, SEO admin page, or SEO REST route exists.

## Parent freeze

A.SEOf reuses AIML Diagnostics conventions — bounded health/verification — not a parallel SEO product or Jobs pipeline.

**Disposition recommendation:** Model SF13 on BlockHealth snapshot semantics; expose via CLI/REST/admin consumers of one core.
