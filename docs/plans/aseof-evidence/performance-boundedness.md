# A.SEOf Evidence — Performance / Boundedness

## Rules

- Diagnostics must not become an uncontrolled crawler
- Prefer current-object checks + small samples
- Redirect follow depth capped
- Optional HTTP fetches capped per scan
- Admin refresh must not trigger site-wide crawl
- No SEO-specific Action Scheduler / Jobs product unless a future ADR freezes it (prefer Deferred)

## Reuse

`BlockHealthScanOptions` sample-size / full-scan pattern is the template. Site-wide full SEO crawl requiring persistent scan state → **defer**.

**Disposition recommendation:** Freeze hard caps in implementation plan; SF1–SF14 Supported work stays synchronous/bounded.
