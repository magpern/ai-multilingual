# A.7d — Deferred surfaces (A7D.4 / A7D.5)

**Status:** Explicit Deferred (Architecture Frozen — not Supported)

## A7D.4 — Body labels / fragments

| Surface | Reason |
|---|---|
| Template gettext body sentences (`Hi %s,`, intros) | No dedicated Woo string filter; scrape forbidden |
| Global email header/footer option text | Shared-definition Store ownership risk |
| Filter-unproven body tokens | Require new evidence before admission |

First Supported set closes without body chrome.

## A7D.5 — Non-order emails

| ID | Surface | Reason |
|---|---|---|
| CE7 | New Account | ADR-0018 is order-backed only |
| CE8 | Reset Password | ADR-0018 is order-backed only |

These do **not** block CE1–CE6 / CE9–CE10.
