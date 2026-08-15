# MSEO.5.0 Characterization — SEO consumers & architecture inventory

**Baseline:** `ce29dbf20cae6b744f8c0d0309ff173a55e02e6e`  
**TARGET:** 8 · **ADR-0023:** sufficient · **Migration:** none  

## Authority inventory (unchanged)

| Concern | Owner |
|---|---|
| Outbound effective URL | `EffectiveUrlService` |
| Inbound recognition | `Router` (current → history → WP) |
| Candidate slug | Store `FORMAT_SLUG` via `SlugCandidateService` |
| Active route | `SlugRouteRepository` / `RoutePublicationService` |
| Discoverability | `ObjectLanguagePublicEligibility` |
| Capability admit | `RoutingCapabilityAdmission` (epoch 2) |
| Hierarchy paths | `HierarchyPathBuilder` (MSEO.3 Supported ancestor leaves) |
| Woo `%product_cat%` | `WooProductPathBuilder` + fingerprint |
| SEO graph | `LanguageRelationshipService` (SB11) |
| Sitemap | Rank Math Model A (`RankMathSitemapOverlay`) |

## Debt dispositions

| Item | Disposition |
|---|---|
| Pretty `%product_cat%` harness skip | Test debt (non-blocking) |
| 1k product proof | Already satisfied (algorithmic) |
| Translated rewrite bases / endpoints / variations / layered-nav | Post-MSEO Deferred/Unsupported |

## SEO consumer characterization (M5R27)

| Consumer | Mechanism | Case | Outcome |
|---|---|---|---|
| Document canonical | `DocumentSeoHead` → SB11 | A | **VERIFIED_EXISTING** |
| hreflang / x-default | `DocumentSeoHead` → SB11 | A | **VERIFIED_EXISTING** |
| Language switcher | `Switcher` → SB11 | A | **VERIFIED_EXISTING** |
| Sitemap xhtml:link | `RankMathSitemapOverlay` → SB11 | A | **VERIFIED_EXISTING** |
| `home_url` / ordinary permalinks | `Router::filter_home_url` → EffectiveUrl | A | **VERIFIED_EXISTING** |
| `term_link` | `Router::filter_term_link` | A | **VERIFIED_EXISTING** |
| Rank Math `og:url` | `reinforce_og_url` → SB11 `current_public()` | A | **VERIFIED_EXISTING** |
| Woo / theme breadcrumbs | Use `get_permalink` / `get_term_link` (AIML filters) — no AIML breadcrumb clone | A | **VERIFIED_EXISTING** |
| Schema Product / BreadcrumbList `url` / `@id` | Rank Math builds via WP permalink APIs; AIML intentionally does not overlay machine URL props (A.SEOc); localization occurs via filtered permalink/`home_url` | A | **VERIFIED_EXISTING** |

**M5R27 closure:** **VERIFIED_EXISTING** for all investigated consumers.  
**No Case B production defect requiring plan amendment.**  
**No Case C architecture/capability gap.**  
**No new SEO hook authorized or introduced.**
