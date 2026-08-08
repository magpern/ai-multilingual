# A.6 — WordPress Visitor Chrome — Implementation Plan

**Status:** **Complete** — merged to `main`; tag `a6-wordpress-visitor-chrome-complete`
**Milestone:** Program A — **A.6** Remaining WordPress visitor chrome
**Plan freeze:** Visitor-facing WordPress chrome **not** already owned by Gutenberg, Elementor, WooCommerce (A.7*), Fluent Forms (A.8), or SEO (A.SEO); per-surface admission via evidence; Supported = **N1** only unless new evidence upgrades Deferred without architecture violation; TARGET **6**
**ADR assessment:** **No new ADR required** for the admitted Supported set (N1). Site-global theme_mods / widget_block hosts / Age Gate-style shared definitions remain Deferred — pursuing them requires a **focused ADR**, not silent Store redesign.
**Roadmap parent:** [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md) — §6.1 / A.6 family
**Planning branch:** `feature/a6-wordpress-visitor-chrome-plan` (merged)
**Implementation branch:** `feature/a6-wordpress-visitor-chrome`
**Validation log:** [A6_VALIDATION_LOG.md](A6_VALIDATION_LOG.md)
**Baseline (plan authoring):** `main` @ `063a5d1bc40c7a6b46c0856c173199b77b2e37c2`
**Implementation baseline:** `main` @ `8db7d5c67fd0f78314232c8730000fa2ff9abe55`
**Depends on:** A.7a–A.7d complete; A.8 complete; A.SEO parent architecture present; ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted**; Integration API v1; schema TARGET **6**
**Evidence:** [a6-evidence/ownership-inventory.md](a6-evidence/ownership-inventory.md); [a6-evidence/admission-matrix.md](a6-evidence/admission-matrix.md); [a6-evidence/theme-analysis.md](a6-evidence/theme-analysis.md); [a6-evidence/widget-analysis.md](a6-evidence/widget-analysis.md); [a6-evidence/shortcode-analysis.md](a6-evidence/shortcode-analysis.md); [a6-evidence/gettext-analysis.md](a6-evidence/gettext-analysis.md); [a6-evidence/visitor-chrome-inventory.md](a6-evidence/visitor-chrome-inventory.md)
**Product direction:** [PRODUCT_PRIORITIES.md](../PRODUCT_PRIORITIES.md)

**Operational success:** Merchants can translate **custom navigation menu item titles** through existing Store / Workspace / Review / TM / Glossary / Jobs paths in SV (and other non-default languages), without stealing theme/Elementor/Woo/Fluent/SEO ownership and without Store redesign.

**This plan remains the canonical implementation contract for A.6.** Supported surface shipped: **N1** only.

---

## 1. Purpose

A.6 closes the remaining **visitor-facing WordPress chrome** gaps before SEO implementation begins.

A.6 is **not**:

- Gutenberg document content (already covered)
- Elementor document content (already covered)
- WooCommerce catalog / archive / journey / emails (A.7*)
- Fluent Forms (A.8)
- SEO / Rank Math / canonical / hreflang (A.SEO)
- Merchant / admin UI
- Blanket theme gettext capture
- HTML scraping of header/footer/DOM

---

## 2. Preconditions (verified at plan authoring)

| Precondition | Status |
|---|---|
| A.7a complete / tagged `a7a-woocommerce-product-catalog-complete` | **Pass** |
| A.7b complete / tagged `a7b-woocommerce-archive-chrome-complete` | **Pass** |
| A.7c complete / tagged `a7c-woocommerce-customer-journey-complete` | **Pass** |
| A.7d complete / tagged `a7d-woocommerce-customer-emails-complete` | **Pass** |
| A.8 complete / tagged `a8-fluentforms-contact-integration-complete` | **Pass** |
| A.SEO parent architecture on `main` | **Pass** |
| ADR-0001 / 0002 / 0007 / 0008 / 0013 / 0016 / 0017 / 0018 **Accepted** | **Pass** |
| Integration API v1 present | **Pass** |
| Migrator `TARGET` = **6** | **Pass** |
| No prior `docs/plans/A6_*` plan | **Pass** |
| Baseline HEAD `063a5d1bc…` | **Pass** |

If any precondition regresses before coding: **STOP**.

---

## 3. Frozen architecture (carry forward — do not reopen)

| Contract | Rule |
|---|---|
| ADR-0001 / ADR-0007 | Overlay; no foreign persistence ownership |
| ADR-0002 / ADR-0008 | Routing / language state unchanged |
| ADR-0013 / ADR-0016 | Gutenberg `b:` / Elementor `e:` unchanged |
| ADR-0017 | Integration API + `p:` via `PluginIdentity` when plugin-owned |
| ADR-0018 | Woo transactional language context unchanged (emails) |
| Integration API v1 | Reuse; N1 does **not** require a new integration |
| Store / Workspace / Review / TM / Glossary / Jobs | Reuse unchanged model |
| Schema TARGET | **6** — no bump |
| Identity | **No new identity family**; N1 uses existing post field `post_title` |

**Forbidden:**

- new identity family / serializer
- Store / schema redesign
- second translation pipeline
- HTML scraping / unscoped buffering / DOM rewrite as primary strategy
- path identity / fuzzy identity
- ownership theft from Gutenberg / Elementor / Woo / Fluent / Rank Math / Blocksy persistence
- inventing site-global Store ownership inside A.6
- gettext msgid capture

**PluginIdentity:** Reuse remains available for future Deferred upgrades that are truly plugin-owned with declared seams. N1 does not mint `p:` keys.

---

## 4. Ownership model (frozen)

| Party | Owns |
|---|---|
| **WordPress** | `nav_menu_item` posts (custom titles); core gettext UI |
| **Blocksy** | Header/footer builder theme_mods; theme templates; breadcrumbs/pagination/404/search/offcanvas chrome |
| **Gutenberg** | Post/page/product block documents; block markup inside `widget_block` options |
| **Elementor** | Document controls (including header-auth widget settings) |
| **WooCommerce** | A.7* surfaces + residual widgets |
| **Fluent Forms** | A.8 surfaces |
| **biopentra-storefront / loop-card** | First-party shortcode/card gettext |
| **Rank Math** | SEO lane |
| **AIML** | Store overlays for **admitted** surfaces only |

---

## 5. Live ownership inventory (summary)

Full matrix: [a6-evidence/ownership-inventory.md](a6-evidence/ownership-inventory.md).

**Theme:** Blocksy Child — BioPentra; builder header text “Free shipping over €200”; copyright “Copyright © {current_year} - Biopentra”.
**Menus:** Main Menu 34 — one custom title (**Home** / item 3474); three object-title items already covered by page titles.
**Widgets:** Almost entirely `widget_block_*` Gutenberg-in-options + one Woo products widget.
**First-party:** storefront/loop-card shortcodes gettext-only.
**Fluent / Woo / Elementor documents:** no remaining A.6 admissions.

---

## 6. Admission matrix (frozen)

Full table: [a6-evidence/admission-matrix.md](a6-evidence/admission-matrix.md).

### Supported

| ID | Surface | Overlay |
|---|---|---|
| **N1** | Custom `nav_menu_item` titles | Remove `nav_menu_item` skip in `Renderer::filter_title`; extract/store `post_title` for menu items |

### Deferred (selected)

D1–D19 including Blocksy builder strings, theme gettext, block widgets, storefront/loop-card gettext, Age Gate/Cookie, Woo Deferred leftovers.

### Unsupported

U1–U7 scrape / fuzzy gettext / ownership theft / Store redesign / new identity family / second pipeline.

---

## 7. Identity + Store model (frozen)

| ID | Key / field | Host |
|---|---|---|
| N1 | Existing segment field `post_title` (Extractor `FIELD_TITLE`) | `source_id` = `nav_menu_item` post ID |

- No `p:` keys for N1.
- No path identity.
- Empty custom title → WordPress resolves object title via object ID; those labels remain **Already covered** (AC1) — do not duplicate Store rows on the menu item.
- Custom title present → extract/overlay on the menu item post only.

### Workspace

Today `WorkspaceService::SUPPORTED_POST_TYPES` = `post`, `page`, `product`. Implementation **must** admit `nav_menu_item` for title-only workspace access (list/open/translate N1) without inventing a new workflow product.

### PluginIdentity

No N1 usage. Future plugin-owned Deferred upgrades may use `PluginIdentity` under Integration API v1 without a new identity family.

---

## 8. Extraction / overlay strategy

1. **Extract:** On `nav_menu_item` save / stale detection, extract **title only** when `post_title` is non-empty. Skip block/Elementor body paths for this post type.
2. **Overlay:** `the_title` applies for `nav_menu_item` when language ≠ default; Store miss → source title; errors isolated.
3. **Menus without custom titles:** unchanged; object-title path continues to use page/product translations.
4. **No HTML scrape** of `wp_nav_menu` output.
5. Sanitization: existing plain title rules.

---

## 9. Platform reuse

Unchanged: Store, Suggestions, Review, TM, Glossary, Jobs, Integration diagnostics, LanguageContext.

Workspace presentation for N1:

- surface = post title (existing)
- human label e.g. “Menu item: Home”
- `source_subtype` / post_type = `nav_menu_item`
- no theme-chrome second pipeline

---

## 10. Compatibility / lifecycle

| State | Behavior |
|---|---|
| Menu item deleted | Store rows retained per existing orphan policy; overlay inert |
| Custom title cleared | Stop extracting N1 for that item; frontend uses object title path (AC1) |
| Custom title edited | Stale detection / re-extract via existing save_post path |
| Theme swap | N1 remains WP-owned; Blocksy Deferred surfaces stay Deferred |
| Woo / Elementor / Fluent present | No ownership theft; regression gates required |
| TARGET drift | STOP |

---

## 11. Work packages (A6.0 – A6.8)

### A6.0 — Baseline + inventory refresh

| | |
|---|---|
| **Objective** | Open validation log; reconfirm TARGET=6; freeze live ownership evidence on impl branch start |
| **Scope** | Docs |
| **Dependencies** | This plan on `main` |
| **Likely files** | `docs/plans/A6_*_VALIDATION_LOG.md`; refresh `a6-evidence/` if live drift |
| **Validation** | Preconditions table still Pass; menus/widgets/theme still match evidence |
| **Rollback** | Revert docs |
| **Stop conditions** | TARGET ≠ 6; A.7d/A.8 tags missing |
| **Commit boundary** | `docs(wordpress): establish A.6 implementation baseline` |

### A6.1 — Admission matrix freeze

| | |
|---|---|
| **Objective** | Confirm Supported = N1 only unless new WP-owned evidence appears |
| **Scope** | Docs / admission records |
| **Dependencies** | A6.0 |
| **Likely files** | `a6-evidence/admission-matrix.md` |
| **Validation** | No Blocksy/Elementor/Woo/Fluent/storefront rows marked Supported without new seams |
| **Rollback** | Revert docs |
| **Stop conditions** | Desire to admit theme_mods or widget_block without ADR |
| **Commit boundary** | `docs(wordpress): freeze A.6 admission matrix` |

### A6.2 — Identity + Workspace post-type contract

| | |
|---|---|
| **Objective** | Freeze N1 = `post_title` on `nav_menu_item`; extend Workspace supported types; unit-test title extract gates |
| **Scope** | Docs + failing-first tests; minimal Workspace allowlist design |
| **Dependencies** | A6.1 |
| **Likely files** | `src/Workspace/WorkspaceService.php`; tests; plan notes |
| **Validation** | No new identity family; PluginIdentity unused for N1 |
| **Rollback** | Revert allowlist |
| **Stop conditions** | Proposal for site-scoped Store or `p:wordpress:…` without need |
| **Commit boundary** | `docs(wordpress): freeze A.6 identity and workspace contract` |

### A6.3 — Extraction (N1)

| | |
|---|---|
| **Objective** | Extract non-empty `nav_menu_item` titles into Store via existing Extractor title field |
| **Scope** | Extractor / save_post stale path guards for `nav_menu_item` |
| **Dependencies** | A6.2 |
| **Likely files** | `src/Translation/Extractor.php`; `src/Plugin.php` (if gates); unit tests |
| **Validation** | Empty custom titles emit no N1 unit; custom titles emit exactly one title segment |
| **Rollback** | Feature flag / revert extract gate |
| **Stop conditions** | Extracting widget_block or theme_mods |
| **Commit boundary** | `feat(wordpress): extract A.6 nav menu item titles` |

### A6.4 — Overlay (N1)

| | |
|---|---|
| **Objective** | Overlay translated custom menu titles on the frontend |
| **Scope** | Remove/narrow `nav_menu_item` skip in `Renderer::filter_title`; ensure EN untouched / SV applied |
| **Dependencies** | A6.3 |
| **Likely files** | `src/Translation/Renderer.php`; `docs/HOOKS.md`; tests |
| **Validation** | Item 3474 SV label; Shop/News/Contact still follow object titles; no menu HTML scrape |
| **Rollback** | Restore skip |
| **Stop conditions** | Overlay requires DOM rewrite |
| **Commit boundary** | `feat(wordpress): overlay A.6 nav menu item titles` |

### A6.5 — Deferred chrome documentation (no production code)

| | |
|---|---|
| **Objective** | Confirm D1–D19 remain Deferred; record Age Gate/Cookie/widget_block ADR triggers |
| **Scope** | Docs + optional negative tests |
| **Dependencies** | A6.4 |
| **Likely files** | `a6-evidence/*`; validation log |
| **Validation** | No Supported creep |
| **Rollback** | Revert docs |
| **Stop conditions** | Implementing Deferred without new evidence |
| **Commit boundary** | `docs(wordpress): record A.6 deferred visitor chrome` |

### A6.6 — Workspace / lifecycle / diagnostics

| | |
|---|---|
| **Objective** | Menu items discoverable/translatable in Workspace; Review/TM/Glossary/Jobs smoke; diagnostics bounded |
| **Scope** | Workspace list filters / deep links as needed for `nav_menu_item` |
| **Dependencies** | A6.4 |
| **Likely files** | `src/Workspace/*`; REST view models if required |
| **Validation** | Translator can open item 3474; no PII; no second pipeline |
| **Rollback** | Revert Workspace allowlist |
| **Stop conditions** | Theme-chrome workspace redesign |
| **Commit boundary** | `feat(wordpress): connect A.6 nav titles to workspace` |

### A6.7 — Acceptance

| | |
|---|---|
| **Objective** | Full gates + live EN/SV for N1; FP=0; leakage=0; compatibility suite |
| **Scope** | Tests + validation log |
| **Dependencies** | A6.6 |
| **Likely files** | `docs/plans/A6_*_VALIDATION_LOG.md`; PHPUnit |
| **Validation** | See §12 (~50 ACs) |
| **Rollback** | — |
| **Stop conditions** | FP>0 or ownership theft |
| **Commit boundary** | `test(wordpress): complete A.6 visitor chrome acceptance` |

### A6.8 — Docs closure

| | |
|---|---|
| **Objective** | Supported/Deferred final; roadmap next pointer; tag prep |
| **Scope** | Docs only |
| **Dependencies** | A6.7 PASS |
| **Likely files** | This plan status → Complete; `POST_V1_PLATFORM_ROADMAP.md`; `docs/ROADMAP.md` |
| **Validation** | Plan/log/roadmap agree; no PRODUCT_PRIORITIES edit required beyond factual next |
| **Rollback** | — |
| **Stop conditions** | Implementation incomplete |
| **Commit boundary** | `docs(wordpress): close A.6 WordPress Visitor Chrome implementation` |

---

## 12. Acceptance criteria (50)

### Ownership & admissions (1–8)

1. Only N1 is Supported unless evidence upgrades a Deferred row.
2. Gutenberg document ownership unchanged.
3. Elementor document ownership unchanged.
4. WooCommerce A.7* Supported/Deferred tables unchanged by A.6 code.
5. Fluent Forms A.8 surfaces unchanged.
6. Rank Math / SEO not translated by A.6.
7. Blocksy theme_mods not written by AIML.
8. No duplicate ownership of the same visible string under two Store keys.

### Identity & Store (9–16)

9. No new identity family.
10. N1 uses `post_title` field only.
11. `source_id` = menu item post ID.
12. PluginIdentity not required for N1.
13. No path identity.
14. No fuzzy msgid identity.
15. TARGET remains **6**.
16. No Store / schema redesign.

### Workspace / Review / TM / Glossary / Jobs (17–24)

17. `nav_menu_item` is Workspace-supported for title translation.
18. Translator can load N1 segments for a custom-titled item.
19. Review workflow accepts N1 segments.
20. TM suggestions available via existing path.
21. Glossary applies via existing path.
22. Background jobs can process N1 segments.
23. No second translation pipeline.
24. Workspace does not claim theme_mod ownership for Deferred chrome.

### Overlay & LanguageContext (25–32)

25. Default language (EN) menus unchanged when no translation.
26. SV overlays custom title when Store has approved/applicable translation.
27. Store miss → source title.
28. Isolated overlay failure does not blank the menu.
29. Non-custom items still follow object-title translations (AC1).
30. LanguageContext / routing unchanged (ADR-0002 / 0008).
31. No language leakage into admin menus unintentionally (admin inert per Renderer gates).
32. Re-entrancy guards remain safe.

### Visitor chrome & false positives (33–40)

33. Live Main Menu custom “Home” translates under `/sv/…` (or active SV prefix).
34. False positives = 0 on Supported surface.
35. No translation of option values / URLs / CSS.
36. Header “Free shipping…” remains untranslated unless future ADR admits it (Deferred).
37. Footer copyright remains Deferred.
38. Block widget footer demo copy remains Deferred.
39. storefront search placeholders remain Deferred.
40. Age Gate / Cookie banner remain Deferred / out of lane.

### Compatibility (41–50)

41. Blocksy header/footer builder still renders.
42. Woo catalog/archive/journey/email Supported surfaces regress = 0.
43. Elementor Contact/Header documents regress = 0.
44. Gutenberg leaf/nested Supported surfaces regress = 0.
45. Fluent Forms #5 regress = 0.
46. Unit + integration tests cover N1 extract/overlay.
47. PluginGuard / PHPCS clean for touched PHP.
48. Diagnostics contain no PII.
49. HOOKS.md documents `nav_menu_item` title overlay behavior.
50. Validation log records Supported≠Deferred exactly.

---

## 13. Stop conditions

**Candidate stop → Deferred:** ownership uncertain; gettext-only; site-global host; scrape required; wrong owner.

**Milestone STOP → focused ADR instead of coding:**

- Store redesign / schema bump
- new identity family
- theme ownership changes (AIML writing theme_mods)
- duplicate ownership
- HTML scraping as primary strategy
- second translation pipeline

---

## 14. Out of scope

SEO; canonical URLs; hreflang; Rank Math; OpenGraph; Twitter; XML Sitemap; WooCommerce emails; merchant/admin UI; translation workspace product redesign; provider improvements; SDK; Marketplace; Age Gate/Cookie production bridges (separate integration track).

---

## 15. ADR assessment

**Verdict:** **No new ADR** for Supported **N1**.

Recommend a **future focused ADR** (not part of A.6) only if product prioritizes:

- site-scoped Store hosts for Blocksy theme_mods / Age Gate messages, or
- widget_block / option-hosted Gutenberg extraction hosts.

---

## 16. Architecture verdict

**Complete** for Supported **N1** only (validated on implementation branch).

Deferred D1–D20 remain explicitly out of implementation scope until evidence + (if needed) ADR.

**Next:** A.SEO (or next Product Priorities target). A.6 Deferred surfaces remain Deferred.

---

## 17. Roadmap pointers

Update only:

- [POST_V1_PLATFORM_ROADMAP.md](POST_V1_PLATFORM_ROADMAP.md)
- [docs/ROADMAP.md](../ROADMAP.md)

Do not renumber milestones. Do not modify `PRODUCT_PRIORITIES.md` in this planning commit.
