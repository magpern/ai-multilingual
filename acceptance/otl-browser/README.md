# Authoritative OTL browser suite

This is the **authoritative** operator-lifecycle Playwright suite for AI Multilingual
(`aiml-otl-browser`). Legacy `acceptance/otl1-browser` … `otl5-browser` packages are
historical archives only.

## Scope

Smoke selectors for:

- Operations tab + filters
- View detail (soft-skip if no rows)
- ConfirmDialog presence when a modal is open
- Review → Operations when the control is visible
- Jobs tab
- Bulk toolbar select-all when rows exist

No live AI calls. Soft-skips when data or capabilities are missing.

## Local / non-CI

Live runs are **local / non-CI**. They need:

- `WP_BASE_URL` (default `https://dev.biopentra.eu`)
- F9 auth cookies under `acceptance/f9-browser/artifacts/auth-cookies.json`
  (or `F10_AUTH_COOKIES` / `F10_AUTH_COOKIES_DIR`)

```bash
cd acceptance/otl-browser
npm install
npx playwright install chromium   # once
npm run test:smoke
```

Do not gate merge CI on this live suite.
