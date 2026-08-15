# AI Multilingual v1.5.0 — Production Deployment Gate A Acceptance

**Date (UTC):** 2026-08-15T15:06Z (deploy)  
**Site:** https://www.biopentra.eu (apex https://biopentra.eu → 301 www)  
**Repository:** magpern/ai-multilingual  
**Scope:** Gate A only — production deployment + immediate acceptance.  
**Gate B product observation:** NOT STARTED.

---

## 1. Release / artifact identity

| Field | Value |
|---|---|
| Tag | `v1.5.0` |
| Release commit | `03a3a09a7ee4e1a0d7624582dcfe07af90ce89d5` |
| Decision-record repo HEAD (docs tip at task start) | `bd759239aafad243eb3e72bdc389c45e218bc488` |
| Artifact | `ai-multilingual-1.5.0.zip` (GitHub Release; independently downloaded) |
| Expected SHA-256 | `cd380eb9513c9eb6b91d6ca67b0efc601fee573eceae6413a46b3d83c6eb89e6` |
| Verified SHA-256 | `cd380eb9513c9eb6b91d6ca67b0efc601fee573eceae6413a46b3d83c6eb89e6` |
| Size | 759714 bytes |
| Archive entries | 475 |
| `bin/audit-zip.sh` | **PASS** |
| Package version | 1.5.0 |
| Package TARGET | 8 |
| Deployed bootstrap SHA-256 (`ai-multilingual.php`) | `58fbdfed9abfbd6c454bfba4506c44dc0f8c02b8458e837fc33ede78135a48d1` (matches ZIP) |

**Deployment authority:** published Release ZIP only (not git mount, not rebuilt).

---

## 2. Production topology

| Item | Value |
|---|---|
| Host | Production VPS (`magpern@173.212.213.37:2222`) |
| Project | `~/woocommerce` (`docker-compose.yml`, project name `woocommerce`) |
| WordPress service | `wordpress` (`woocommerce-wordpress:php8.3-redis`) |
| WP-CLI | `~/woocommerce/wp` → `docker compose run --rm wpcli` |
| Plugin filesystem | Bind-mounted `./wp-content` → `/var/www/html/wp-content` (not a git bind-mount for AIML) |
| Established deploy convention | `scripts/deploy/deploy-plugin.sh` (GitHub ZIP + `wp plugin install --force --activate`) |
| This Gate A method | Same install mechanism, but from **host-verified local copy** of the published ZIP bind-mounted into wpcli |

**Plugin path:** `/home/magpern/woocommerce/wp-content/plugins/ai-multilingual`

---

## 3. Pre-deployment baseline

| Check | Result |
|---|---|
| AIML installed | **No** (directory absent; not in `plugin list`) |
| `aiml_db_version` | absent |
| `aiml_settings` | absent |
| AIML tables | none |
| Other multilingual plugins | none active |
| HTTP apex | 301 → www |
| HTTP www home | 200 |
| Shop / cart | 200 |
| Checkout (empty) | 302 → cart (expected) |
| wp-admin (anonymous) | 302 → wp-login |
| Sitemap index | 200 |
| Pre-existing AIML fatals | none (plugin absent) |

### Strategy F / Localized URLs (pre)

Not applicable — plugin not installed. No rollout options present.

---

## 4. Backup / rollback readiness

| Item | Value |
|---|---|
| DB backup | `/home/magpern/woocommerce/backups/aiml-gate-a/pre-v150-install_20260815_170528.sql` (mode 600, ~19MB; outside web root) |
| DB backup SHA-256 | `704c6c58cdac4f5cc28e16c02ae8132920ca43c8647f65fb7c81c251ae7615ae` |
| Active plugins snapshot | `.../active_plugins_pre_20260815_170528.json` |
| Rollback note | `.../ROLLBACK_20260815_170528.txt` |
| Verified ZIP retained | `~/woocommerce/backups/plugins/ai-multilingual-1.5.0_verified_20260815_170528.zip` |
| Installed tree archive | `~/woocommerce/backups/plugins/ai-multilingual_v1.5.0_installed_20260815_170528.tar.gz` |
| Prior plugin package | N/A (fresh install) |
| Migration risk | Fresh install → schema create to TARGET **8** (backup taken before install) |

**Rollback (blocking failure):** deactivate + delete plugin; for catastrophic DB issues restore the pre-install SQL in a maintenance window. No production hotfixes.

**PRE-DEPLOYMENT VERDICT: GO**

---

## 5. Deployment

| Step | Result |
|---|---|
| Method | `docker compose run --rm --no-deps -v .../tmp/aiml-v150:/aiml-release:ro wpcli wp plugin install /aiml-release/ai-multilingual-1.5.0.zip --force --activate` |
| Result | Success — plugin activated |
| Post version | **1.5.0** (active) |
| `aiml_db_version` | **8** |
| `Migrator::TARGET` | **8** |
| Migration / no-op | **Fresh schema create to TARGET 8** (not an upgrade no-op; no unexpected TARGET >8) |
| Settings | Defaults created (`localized_urls_state=off`, Strategy F / Elementor flags off, `auto_publication_mode=manual`) — expected for first install |
| Languages | Default `en` / `en_US` seeded (`language_id=1`, published, is_default) |
| Active routes | 0 |

Settings “preservation” N/A → **defaults established as designed** (not a regression).

---

## 6. Site health / HTTP (post)

| URL | Result |
|---|---|
| https://biopentra.eu/ | 301 → www |
| https://www.biopentra.eu/ | 200; no critical/fatal markup |
| /shop/ | 200 |
| /product/bacteriostatic-water/ | 200 |
| /product-category/growth-performance/ | 200 |
| /cart/ | 200 |
| /checkout/ | 302 → /cart/ (empty cart; expected) |
| /my-account/ | 200 |
| /sitemap_index.xml | 200 |
| /wp-login.php | 200 |
| /sv/ | 404 (no target language configured; Localized URLs off) |

Containers healthy; recent wordpress logs: **no AIML fatals**.

---

## 7. Translation acceptance

| Surface | Result |
|---|---|
| Source-language frontend | **PASS** (default en site loads) |
| Translated-language frontend | **NOT EXERCISED — NO SAFE EXISTING PRODUCTION FIXTURE** (no target language / no translation rows) |
| Title / Gutenberg / Elementor overlays | **NOT EXERCISED** — Strategy F + Elementor flags default **off**; no translated content |
| Strategy F preflight (post) | All block/Elementor extraction/render flags **false**; no separate rollout option rows |

No content was created solely to manufacture acceptance evidence.

---

## 8. WooCommerce critical acceptance

| Check | Result |
|---|---|
| Shop | 200 |
| Product PDP | 200 (`bacteriostatic-water`) |
| Category | 200 (`growth-performance`) |
| Cart reachable | 200 |
| Checkout entry | 302 → cart when empty (boundary: no paid test order placed) |
| Inventory / prices / orders | unchanged (not modified) |

No blocking commerce regression observed from AIML install at defaults.

---

## 9. SEO sanity (deployment regression only)

| Check | Result |
|---|---|
| Canonical (home) | `https://www.biopentra.eu/` |
| Canonical (product) | `https://www.biopentra.eu/product/bacteriostatic-water/` |
| hreflang | en-US + x-default present on home/product |
| Sitemap | 200 |
| Redirect loops | none observed on sampled URLs |
| Source slug mutation | none observed |
| Localized URL enabled-state | **NOT EXERCISED — FEATURE OFF IN PRODUCTION** (`localized_urls_state=off`, 0 routes) |

---

## 10. Log / error audit

| Class | Finding |
|---|---|
| DEPLOYMENT-INTRODUCED AIML fatals | **None** |
| PRE-EXISTING | Product-category URL `/product-category/peptides/` 404 (invalid slug; unrelated) |
| ENVIRONMENT / CONFIGURATION | Fresh AIML install; defaults inert for overlays/Localized URLs |
| EXPECTED LIMITATION | No SV language; `/sv/` 404; overlays not exercisable without fixtures/flags |

---

## 11. Unexercised areas (honest)

- Localized URLs enabled-state / routes / collisions  
- Target-language overlays  
- Gutenberg / Elementor render paths (flags off)  
- Jobs / stale-conflict operator flows  
- Paid checkout order  
- Gate B product observation  

---

## 12. Rollback decision

**Rollback required?** No.

Rollback assets remain available at paths in §4.

---

## 13. Gate A verdict

**GATE A PRODUCTION DEPLOYMENT ACCEPTANCE: PASS**

Criteria met: exact published artifact deployed and proven; plugin active at 1.5.0; TARGET/db 8 healthy; defaults established; site/Woo/SEO sanity OK; no new AIML fatals; unexercised areas identified; rollback available.

Gate A does **not** authorize Gate B, Program B, or any further development program.
