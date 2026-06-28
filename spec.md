# Specification — `wp-lrob-cookie-consent`

WordPress cookie-consent plugin (GDPR / ePrivacy). A lean, opinionated replacement for Complianz, for LRob and client managed-hosting sites. Built to match existing LRob plugin conventions.

This spec is written for Claude Code. It assumes you have read this file plus the two reference repos: `LRob-FR/wp-lrob-email-toolkit` (structure, GitHub auto-update, `release.sh`, naming discipline) and `LRob-FR/wp-age-gate` (single-purpose plugin shape: `includes/` + `views/`, JSON presets, live admin preview, cookie + bot-bypass front gate). The technical reference for the blocking engine is the Complianz 7.5.0 source, summarized in §3–§4.

---

## 0. Design principles

- **Lean.** No privacy-policy generator, no packaged third-party integrations, no A/B testing, no US/DNSMPD logic. Keep the **GDPR core only**. A **lightweight local cookie/resource scan** (anonymous crawl that *suggests* rules) is included; a heavier headless **remote scan** is a future, optional LRob-hosted provider (behind a provider seam), not part of core.
- **Opinionated.** Opt-in consent by default (strict EU model), immutable built-in categories plus optional custom ones, **no consent-per-service** (v1).
- **Self-contained.** No Composer, no React, no build pipeline. Plain PHP 8.2+, vanilla JS, server-rendered admin. Only outbound network call is the GitHub auto-updater. (Mirrors email-toolkit's "no library bloat" stance.)
- **Single source of truth for version**: plugin header `Version:` + `LROB_CC_VERSION` constant, bumped together.
- Target total plugin size well under Complianz's ~23 MB. Front JS < 15 KB unminified, front CSS < 10 KB.

## 1. Identity & naming — MANDATORY

Follow the same per-plugin prefix discipline email-toolkit enforces (several LRob plugins coexist; bare `lrob_` collides). This plugin's token is **`cc`** (= "cookie consent").

| Layer | Prefix | Examples |
|---|---|---|
| Plugin slug | `lrob-cookie-consent` | text domain identical |
| PHP namespace | `LRob\CookieConsent\` (PSR-4, match email-toolkit) | `LRob\CookieConsent\Frontend\Banner`, `LRob\CookieConsent\Blocking\Blocker`, `LRob\CookieConsent\Consent\LogRepository`, `LRob\CookieConsent\AutoUpdate\Updater` |
| Functions | `lrob_cc_` | `lrob_cc_get_options()` |
| Hooks | `lrob_cc_` | `lrob_cc_enable_category`, `lrob_cc_blocked_script_tags` |
| Constants | `LROB_CC_` | `LROB_CC_VERSION`, `LROB_CC_PATH`, `LROB_CC_GITHUB_URL` |
| Option | `lrob_cc_options` (single serialized array, age-gate style) | + `lrob_cc_db_version` |
| DB table | `{wpdb->prefix}lrob_cc_` | `wp_lrob_cc_consent_log` |
| REST namespace | `lrob-cc/v1` | `/wp-json/lrob-cc/v1/log` |
| Capability | `manage_lrob_cc` | granted to `administrator` on activate |
| Cookies | `lrob_cc_consent`, `lrob_cc_status` | |
| CSS / JS globals | `lrob-cc-` / `lrobCc` | `lrob-cc-banner`, `window.lrobCc` |

Anything added — option key, table, hook, CSS class — **must** follow these prefixes.

> Architecture decision (REVISED): use **email-toolkit's organization** — hand-rolled **PSR-4 autoloader** (`LRob\CookieConsent\Foo\Bar` → `src/Foo/Bar.php`), a tiny **Container**, `Plugin`/`Activator`/`Deactivator` lifecycle, `final` classes, `declare(strict_types=1)`, constructor property promotion, minimal comments. This professional, collision-free namespacing is the point. No `ModuleManager` (this plugin is single-purpose). Borrow from **age-gate** only: the **back-office live-preview** of the front render, the **appearance/customization settings** model, and (maybe) its **cookie storage** approach. Borrow from **email-toolkit**: the **GitHub updater**, the **`release.sh`** build script + release gate, the **naming discipline**, and the **IP-anonymization** + **UTC-epoch bucket** helpers.

## 2. Project structure (email-toolkit PSR-4 shape)
wp-lrob-cookie-consent/

├── lrob-cookie-consent.php       # header, constants, PSR-4 autoloader, lifecycle hooks, boot

├── src/

│   ├── Plugin.php                # singleton boot; wires services into the Container

│   ├── Container.php             # tiny set()/get()/has() service locator

│   ├── Activator.php             # grant cap + seed options + dbDelta log table

│   ├── Deactivator.php           # clear cron (none v1; keep ready)

│   ├── Admin/

│   │   └── SettingsPage.php      # settings page, Settings API, AJAX

│   ├── Frontend/

│   │   ├── Banner.php            # render banner, shortcode

│   │   └── Assets.php            # enqueue + localize front JS/CSS

│   ├── Blocking/

│   │   └── Blocker.php           # output buffer, script/iframe blocking

│   ├── Consent/

│   │   ├── Schema.php            # log table name + dbDelta create

│   │   ├── LogRepository.php     # proof of consent storage + CSV export

│   │   └── RestController.php    # /wp-json/lrob-cc/v1/log

│   ├── Support/

│   │   ├── Options.php           # single lrob_cc_options array: defaults/get/sanitize

│   │   ├── Ip.php                # anonymise_ip (v4→/24, v6→/48; port email-toolkit)

│   │   └── Bots.php              # is_bot UA pattern list

│   └── AutoUpdate/

│       └── Updater.php           # GitHub release auto-update (port from email-toolkit)

├── views/

│   ├── banner.php                # front banner markup (placeholder tokens)

│   ├── manage-link.php           # [lrob_cc_manage] shortcode output

│   └── admin-settings.php        # admin page shell (tabs)

├── assets/

│   ├── js/

│   │   ├── consent.js            # front consent state machine

│   │   └── admin-preview.js      # live preview in settings (age-gate pattern)

│   └── css/

│       ├── banner.css            # front banner styles (all colors via CSS vars)

│       └── admin.css

├── languages/

│   └── lrob-cookie-consent-fr_FR.po/.mo

├── release.sh                    # port from email-toolkit (lint, pot/po/mo, dead-css, zip)

├── uninstall.php                 # drop table + options + cap (prefix scan, belt-and-suspenders)

├── CLAUDE.md                     # always-loaded technical guideline layer

└── README.md

Activation grants `manage_lrob_cc` to administrator + seeds default `lrob_cc_options` + `dbDelta` the log table. Deactivation clears any cron (none planned v1; keep the hook empty/ready). `uninstall.php` drops `{prefix}lrob_cc_*` tables + `lrob_cc_*` options + the capability.

## 3. Script-blocking engine (`class-blocker.php`)

The GDPR core. Mechanism ported and **simplified** from Complianz `class-cookie-blocker.php` → `replace_tags()`, `set_javascript_to_plain()`, `replace_src()`.

### 3.1 Output buffering
- `ob_start()` with a filter callback on `template_redirect` (high priority); flush on `shutdown`.
- **Skip buffering** when: `is_admin()`, REST request, AJAX, cron, feed, sitemap, POST request, or bot (UA match — port Complianz's `cmplz_is_bot()` pattern list).

### 3.2 Script transformation
For each `<script>` matching a configured block pattern:
- `type="text/javascript"` / `type="module"` / no type → rewrite to `type="text/plain"` (keep `data-script-type="module"` when it was a module). Port `set_javascript_to_plain()` exactly — it handles the three cases.
- Add `data-category="{preferences|statistics|marketing}"` and optional `data-service="{name}"`.
- For `src` scripts: move `src` → `data-src` (port `replace_src()`).

### 3.3 Iframes / embeds
- Matching `<iframe src="…">` → `src` to `data-src`, add class `lrob-cc-blocked` + `data-category`.
- Insert a clickable **placeholder** (notice text + "Accept" button) in place. Port the front-side container logic from Complianz `cmplz_set_blocked_content_container()`.
- Preserve aspect ratio from the iframe's width/height.

### 3.4 What to block — admin-declared, no built-in service DB
A textarea in settings, one rule per line: `pattern | category | service_name`. No pre-filled Complianz-style services database — the admin declares what to block. Seed defaults as **placeholder text only** (not active):
google-analytics.com | statistics | Google Analytics
googletagmanager.com  | statistics | Google Tag Manager
connect.facebook.net  | marketing  | Facebook
youtube.com/embed     | marketing  | YouTube

Expose `apply_filters('lrob_cc_block_rules', $rules)` so client sites can add rules in code. To spare admins blind typing, the Blocking tab offers a **quick-add list of common services** (GA, GTM, Matomo, Hotjar, Meta Pixel, Google Ads, YouTube, Vimeo, Maps, LinkedIn — `lrob_cc_common_services` filter): one click appends the matching rule line. This is an authoring convenience, **not** a hidden auto-block database — a rule only takes effect once added.

### 3.5 Admin-declared inline scripts
A settings field to paste an inline script (e.g. GA4, Matomo) + pick a category. Plugin injects it as `text/plain` `data-category` so the admin never edits the theme.

### 3.6 Performance
- Cache the compiled rule set per request. Cache the parsed block list in a transient (`lrob_cc_block_rules`, 30 min) — invalidate on settings save. Mirror Complianz's transient approach but lighter.

## 4. Front consent engine (`assets/js/consent.js`)

State machine ported from Complianz `complianz.js` but stripped to the core (target < 15 KB vs 83 KB). Port these functions specifically: `cmplz_has_consent`, `cmplz_set_consent`, `cmplz_accepted_categories`, `cmplz_run_script`, `cmplz_enable_category`, `cmplz_set_category_as_body_class`, `cmplz_trap_focus`.

### 4.1 Categories (functional fixed; optional admin-managed)
`functional` is hardcoded, always-on and **force-accepted** (covers WordPress login/cart/CSRF cookies that can't be blocked without breaking the site). Admins can also add **functional rules to *reference* necessary cookies** (own site, payment gateways like Stripe/PayPal) — documented in the version snapshot + audit, but **never blocked**. The **optional** categories are **admin-managed** (stored in `lrob_cc_options['categories']`), seeded with defaults `preferences`, `statistics`, `marketing`, `embed` (External content — embeds aren't inherently marketing), `security`. Admins can **rename, reorder, remove, or add their own** categories (slug + label + description); default slugs keep translatable labels, custom ones use admin text. No consent-per-service in v1.

### 4.2 Storage
- Cookie `lrob_cc_consent` = JSON `{ functional:true, preferences:bool, statistics:bool, marketing:bool, ts, version }`. `SameSite=Lax`, path `/`, duration configurable (default 365 days).
- Cookie `lrob_cc_status` = `dismissed|show`.
- **Consent versioning**: `version` = short hash of the active category/rule config. If it changes, re-prompt (port the saved-vs-current comparison idea from Complianz `cmplz_track_status`).

### 4.3 Public JS API (`window.lrobCc`)
- `lrob_cc_has_consent(category)` → bool (`functional` always true; bot → true).
- `lrob_cc_accept_all()`, `lrob_cc_deny_all()`, `lrob_cc_set_consent(category, 'allow'|'deny')`, `lrob_cc_accepted_categories()`.
- Dispatch `document` CustomEvents: `lrob_cc_enable_category` (`detail: {category, categories}`) and `lrob_cc_status_change`, so theme/dev scripts can hook activation.

### 4.4 Script activation after consent
On category consent: select `script[type="text/plain"][data-category="X"]`, recreate an executable `<script>` (copy attributes, `data-src`→`src`, copy innerHTML), insert, remove placeholder; for iframes `data-src`→`src` and drop `lrob-cc-blocked`. Port `cmplz_run_script()`.

### 4.5 Body classes
Add `lrob-cc-{category}` to `<body>` per consented category (for conditional CSS). Port `cmplz_set_category_as_body_class`.

### 4.6 WP Consent API (optional, soft dependency)
If the **WP Consent API** plugin is active (`function_exists('wp_set_consent')`), mirror category decisions via `wp_set_consent()` and declare `wp_consent_type = 'optin'`. No hard dependency.

## 5. Banner (`views/banner.php` + `class-frontend.php`)

### 5.1 Markup
Simplified port of Complianz `cookiebanner/templates/cookiebanner.php`: header (logo + title + close), message, category block (per category: accordion toggle + checkbox; `functional` shows "Always active", not toggleable), button row. Placeholder tokens: `{header}`, `{message}`, `{category_*}`, `{*_text}`, `{accept_text}`, `{deny_text}`, `{save_text}`, `{logo}`.

### 5.2 Accessibility — required (port from Complianz, it does this well)
`role="dialog"`, `aria-modal`, `aria-labelledby`, `aria-describedby`, focus trap (`cmplz_trap_focus`), keyboard close, `aria-expanded` on toggles, screen-reader labels on checkboxes. Match age-gate's accessibility bar (ARIA, Tab/Shift+Tab, focus management) — but note age-gate disables ESC; here ESC should be allowed (consent banners must be dismissible, unlike an age gate).

### 5.3 Buttons & disclosure
Accept all / Deny all / Save preferences. **Each button is individually toggleable** (`show_deny`, `show_save`); Deny is visible by default. **Category toggles are collapsed by default** behind a "Customize" button (`categories_collapsed`) — the default banner is just header + message + Accept/Deny/Customize, and the per-category switches + Save appear only after the visitor clicks Customize. Shortcode `[lrob_cc_manage]` re-opens the banner; an optional floating **"Manage cookies" revisit button** (`revisit_button`, off by default) reappears after a decision (Complianz-style).

**The banner only renders when there is something to manage** — i.e. at least one block rule or inline script is configured. Enabling the plugin with no rules shows nothing (a consent banner that blocks nothing is misleading).

### 5.4 Appearance — FSE-first, fixes the original Complianz grievance
**Goal: zero customization needed by default.** Out of the box the banner inherits the active theme's colors, fonts, button styling and radii, so on a well-built (especially FSE / block) theme it looks native immediately. The admin customizes only if they *want* to.

- **FSE / block-theme integration (priority).** Map the banner's CSS variables onto WordPress global-style tokens — `--wp--preset--color--*` (base/contrast/primary/…), `--wp--preset--font-size--*`, `--wp--preset--font-family--*`, and exposed root padding / border radii. Read the theme palette via `wp_get_global_settings()` (age-gate already does this) to seed "follow theme" defaults and populate preview swatches. Reuse the theme's button block styling where available.
- **Theme mode (port + extend age-gate's model):**
  - **Auto (default)** — follow the site: inherit FSE/theme tokens, with a `prefers-color-scheme` dark fallback for non-FSE themes.
  - **Light** — forced light palette (fixed colors, ignores the theme).
  - **Dark** — forced dark palette (fixed colors, ignores the theme).
  - **Custom** — admin-picked colors.
- **Every color is a CSS variable on `.lrob-cc-banner`; no hardcoded colors anywhere** (the bug that started this: Complianz hardcodes `.cmplz-message { color:#000 }` with no exposed variable). Each variable resolves in order: custom value → theme token → built-in fallback. Every text element (title, message, category titles, descriptions) inherits from a variable:

--lrob-cc-bg, --lrob-cc-text, --lrob-cc-title, --lrob-cc-border,
--lrob-cc-btn-bg, --lrob-cc-btn-text, --lrob-cc-btn-deny-bg, --lrob-cc-btn-deny-text,
--lrob-cc-radius, --lrob-cc-font-size, …

Settings (all as button/segmented controls, not dropdowns, with active-state feedback): position (bottom / center / bottom-left / bottom-right), colors (Auto/Light/Dark/Custom), **independent size controls** — popup width (small/medium/large), density/spacing (compact/cozy/comfortable), font size (small/medium/large), and corner shape (square/rounded/pill) — plus custom colors, backdrop blur, logo, and text presets (§5.6). Defaults are deliberately compact (small width, cozy density) — not a wide bar.

### 5.5 Live preview (age-gate pattern)
Reuse age-gate's live-preview mechanism: render the banner inside the settings page and update it in JS on field change (colors, texts, position, theme, presets) with no reload — `assets/js/admin-preview.js`.

### 5.6 Presets — lower the customization burden
Two kinds, both selectable in the Banner tab, both applied client-side into the live preview and saved into `lrob_cc_options`. Presets only *seed* the underlying values; everything stays editable afterward.

- **Style presets** — one-click looks bundling colors + shape + size:
  - *Colors*: Follow theme (default), Light, Dark, plus a few accent pairings (e.g. Neutral, High-contrast, Brand-soft) — sets button colors + text/bg colors.
  - *Shape*: Square / Rounded / Pill (border-radius scale).
  - *Size*: Compact / Default / Large (padding + font-size scale).
- **Text presets** — ready-to-use copy for header/message/buttons, defined in PHP (`Support\Presets`) with `__()` so the messages **and buttons** are translatable (gettext can't extract from JSON). Tones: neutral/legal default, minimal, and friendly/fun ones, e.g. *"A few cookies to keep the shop running 🍪"*. The chosen preset id is remembered (`text_preset`).

Expose `apply_filters('lrob_cc_style_presets', $presets)` and `apply_filters('lrob_cc_text_presets', $presets)` so client sites can register their own.

## 6. Proof of consent (`Consent\*`) — legally robust

The burden of proof is on the data controller: a click is not enough — we must be able to show consent was **free, specific, informed, unambiguous**, and record withdrawals too. Each consent event (`Consent\Schema` table `{prefix}lrob_cc_consent_log`, schema v5) stores:
- **Precise timestamp** (`created_at`, UTC) + **`expires_at`** (created + `cookie_days`, ~13 months / CNIL renewal).
- **Anonymous subject identifier** (`consent_id`) — a random token generated client-side, stored in the consent cookie and reused across that browser's events (data-minimised; not PII). Always logged so records are auditable regardless of IP setting.
- **Granular per-purpose decision** (`choices` JSON: `{category: 1|0}` for **every optional category actually proposed** — `Rules::active_categories()`, i.e. those that block something — an explicit allow/deny per purpose, never a single blanket flag). functional is implicit, never a choice.
- **Banner version** (`banner_version`) — links to the exact banner as the visitor saw it, captured from the rendered DOM at consent time so it stays accurate under a translation plugin (see §6b).
- **Technical trace of the positive act**: `method` (which button: accept_all / deny_all / save / service) + `payload` (raw client JSON) + `event_type` (consent / update / **withdraw**). Withdrawals are logged the same way.
- `config_version`, optional `user_id` (`store_wp_user`), `ip` (`ip_storage`: *hashed* default / *full* — the subject_id keeps records identifiable either way), `user_agent` (opt-in).
- Skip logging for bots; only when "Store proof of consent" is on (default on).

### 6b. Versioning, retention, audit
- **Cookie-consent version** (`Consent\BannerVersion` + `{prefix}lrob_cc_banner_versions`): the full snapshot — header/message/buttons, every category's label+description, **and what each category blocks** (rules + inline scripts) — is hashed; the hash in force is recorded with each consent; editing any of it yields a new hash, and old records keep theirs. The admin lists versions and can click a record's version to view its complete snapshot; the snapshot is included in the CSV export. A version is **pruned once no record references it** (`prune_orphans()`, run after every log deletion).
- **Retention**: configurable purge (daily cron) — proof is not kept indefinitely (minimisation).
- **Audit view**: `Admin\ConsentLogTable` (a `WP_List_Table`) — sortable/paginated, **per-row + bulk delete**, CSV export, for CNIL inspection.

## 7. Admin settings (`class-admin.php` + `views/admin-settings.php`)

Single page under Settings (no multi-step wizard). WP Settings API. Single `lrob_cc_options` array (age-gate style). Tabs:
1. **General** — enable/disable, consent type (opt-in only v1), cookie duration, log retention, anonymize-IP toggle, respect Do-Not-Track.
2. **Banner** — texts (header, message, buttons, category descriptions) + appearance (theme/colors/position/radius/blur/logo) + style & text **presets** (§5.6) + **live preview**. FSE-first: defaults inherit theme tokens so no customization is needed out of the box.
3. **Cookies** (formerly "Blocking") — manage categories (§4.1), guided rule editor / raw mode (§3.4), site scan + quick-add, admin-declared inline scripts (§3.5).
4. **Log** — consent table + CSV export + purge.

Security throughout: nonces on every admin action + AJAX (`check_ajax_referer` + `manage_lrob_cc`), sanitize on input, escape on output (match age-gate's stated security model).

## 8. Auto-update & release (port from email-toolkit)

- `class-updater.php`: port email-toolkit's GitHub updater — `pre_set_site_transient_update_plugins` + `plugins_api` hooks, checks latest release of `LRob-FR/wp-lrob-cookie-consent`, ~1 h cache, force-refresh on `update-core.php`, downloads the release zip.
- Plugin header: `Requires PHP: 8.2`, `Requires at least: 6.8`, `Update URI`, `Text Domain: lrob-cookie-consent`, `Domain Path: /languages`.
- `release.sh`: port email-toolkit's script — `php -l` + `node --check` lint, dead-CSS scan for `.lrob-cc-*`, POT regen + `msgmerge` + `msgfmt`/`make-json`, build stats, zip to `../releases/lrob-cookie-consent-<version>.zip`, excluding dev files (`*.sh`, `*.po/.pot`, `README.md`, `.git`, etc.). Keep email-toolkit's **release gate**: `msgfmt` line must read `N translated, 0 fuzzy, 0 untranslated` before tagging.

## 9. Conventions (inherit from email-toolkit CLAUDE.md)

- `declare(strict_types=1);` at the top of every PHP file in `includes/`.
- Final classes.
- Constructor property promotion (PHP 8.2+).
- Minimal comments: one-line WHY only where non-obvious; never narrate WHAT. Keep the WP plugin header and `/* translators: */` notes.
- No backwards-compat shims while version < 1.0.0 — schema can change freely between minor versions.
- No `window.confirm/alert/prompt` in admin — if a destructive confirm is needed (e.g. purge log), build a minimal in-plugin confirm (this plugin is standalone, so a small self-contained confirm dialog is fine; don't pull email-toolkit's `etk-confirm.js`).
- Don't pre-bump versions or auto-commit; wait for the user's cue. **Ask the user for the version number** on ship.

## 9b. Scanning (`src/Scanning/`)

Helps admins discover what to block instead of typing rules blind.
- **Two modes (predictable by design):**
  - **Database (content)** — default. Reads the `post_content` of **all published posts and pages** (no HTTP) and finds cross-origin embeds/scripts + known provider URLs. Predictable (nothing in your content is missed) and light. Can't see theme/plugin-injected or auto-rendered embeds.
  - **Visit pages (rendered)** — fetches an **explicit, listed** set (home + published pages/posts, most-recent first, ≤50) **anonymously** via `wp_remote_get`, one at a time with a progress bar; catches theme-injected too. SSL-error → offer "retry ignoring SSL". The exact URLs scanned are shown with the results.
- The curated services list is **grouped by category** in the quick-add UI; results map to block rules.
- **Provider seam:** `ScanProvider` interface + `Scanner::providers()` (`lrob_cc_scan_providers` filter) so a **remote LRob headless deep-scan** (renders pages, catches dynamic trackers + actual cookies set) can plug in later as a promoted premium option.
- **AJAX** `wp_ajax_lrob_cc_scan` (cap + nonce) → results table with per-row checkbox + category; "Add selected" injects rows into the guided rule editor.
- **Guided wizard:** a "which services do you use?" checklist (services grouped by category) that generates rules — for non-technical admins.

## 10. Out of scope v1 (note, don't build)

Privacy-policy generator, consent-per-service, A/B testing, packaged integrations (WooCommerce/forms/etc.), multi-region/geolocation, Google Consent Mode, TCF/IAB, and the **remote headless scan service** (designed-for but not built — future LRob-hosted premium provider). Revisit in v2 only on a real client need.

## 11. Migration note

Sites move Complianz → lrob-cc by deactivate/activate. Optional, low priority: read `complianz_options_*` to pre-fill colors/texts. Document the `.cmplz-*` → `.lrob-cc-*` class mapping for any sites with custom CSS that targeted Complianz selectors.

---

### Complianz 7.5.0 reference index (consult for implementation detail)
- Blocking: `class-cookie-blocker.php` → `replace_tags()`, `set_javascript_to_plain()`, `replace_src()`, `blocked_scripts()`, `start_buffer()/end_buffer()`.
- Front engine: `cookiebanner/js/complianz.js` → `cmplz_run_script()`, `cmplz_enable_category()`, `cmplz_has_consent()`, `cmplz_track_status()`, `cmplz_set_blocked_content_container()`, `cmplz_trap_focus()`, `cmplz_set_category_as_body_class()`.
- Banner markup: `cookiebanner/templates/cookiebanner.php`.
- Enqueue/inject: `cookiebanner/class-banner-loader.php` → `enqueue_assets()`, `cookiebanner_html()`.
- Proof of consent: `proof-of-consent/class-proof-of-consent.php`.


