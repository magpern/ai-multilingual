# A.6 — WordPress Visitor Chrome — Validation Log

**Milestone:** A.6 WordPress Visitor Chrome  
**Implementation branch:** `feature/a6-wordpress-visitor-chrome`  
**Plan:** [A6_WORDPRESS_VISITOR_CHROME_IMPLEMENTATION_PLAN.md](A6_WORDPRESS_VISITOR_CHROME_IMPLEMENTATION_PLAN.md)  
**Evidence:** [a6-evidence/](a6-evidence/)  
**Planning freeze on main:** `8db7d5c67fd0f78314232c8730000fa2ff9abe55`  
**Implementation baseline HEAD:** `8db7d5c67fd0f78314232c8730000fa2ff9abe55`

---

## A6.0 — Baseline

**Status:** PASS

### Preconditions

| Item | Result |
|---|---|
| `main` clean / synced at branch cut | **Pass** (`8db7d5c67…`) |
| A.6 plan Architecture Frozen on `main` | **Pass** |
| TARGET | **6** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 | **Accepted** |
| Integration API v1 | Present / unchanged |
| A.7d tag `a7d-woocommerce-customer-emails-complete` | Present |
| A.8 tag `a8-fluentforms-contact-integration-complete` | Present |
| A.6 production code (pre A6.2) | Absent |

### Live ownership re-check (dev.biopentra.eu)

| Item | Value |
|---|---|
| Theme | `blocksy-child` / parent Blocksy |
| Main Menu | term_id **34** |
| Custom title fixture | nav_menu_item **3474** — `post_title` = `Home` |
| Object-title items | Shop / News / Contact (empty custom `post_title`) |
| Widgets | `widget_block_*` + `woocommerce_products-2` (Deferred) |
| Blocksy header_text / copyright | Present (Deferred D1/D2) |
| Renderer `nav_menu_item` skip | Still present pre-A6.4 |

---

## A6.1 — Admission freeze

**Status:** PASS — Supported **N1** only. Records: [a6-evidence/a6-admission-records.md](a6-evidence/a6-admission-records.md).

---

## A6.2 — Identity + Workspace contract

**Status:** Pending

---

## A6.3 — Extraction (N1)

**Status:** Pending

---

## A6.4 — Overlay (N1)

**Status:** Pending

---

## A6.5 — Deferred chrome

**Status:** Pending

---

## A6.6 — Workspace / lifecycle

**Status:** Pending

---

## A6.7 — Full acceptance

**Status:** Pending

---

## A6.8 — Closure

**Status:** Pending
