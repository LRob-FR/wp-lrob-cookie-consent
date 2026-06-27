# CLAUDE.md

Guidance for Claude Code sessions in this repository. **This file is the always-loaded technical-guideline layer** (conventions, naming, architecture, gotchas). For *what the plugin does* (public), see `README.md`. For *what we're building and why* (the product brief), see `spec.md` — read it before any feature work.

## Project

WordPress plugin **LRob - Cookie Consent** (slug `lrob-cookie-consent`). A lean, opinionated GDPR / ePrivacy cookie-consent plugin — opt-in banner, script/iframe blocking engine, proof of consent — built as a focused replacement for Complianz on LRob and client managed-hosting sites. Requires PHP 8.2+ and WordPress 6.8+.

**Design stance (from `spec.md` §0):** lean (no cookie scanner, no policy generator, no packaged integrations, GDPR core only), opinionated (opt-in by default, 4 fixed categories, no consent-per-service in v1), self-contained (no Composer, no React, no build pipeline — plain PHP 8.2+, vanilla JS, server-rendered admin). The only outbound network call is the GitHub auto-updater. Target: front JS < 15 KB, front CSS < 10 KB, total plugin far under Complianz's ~23 MB.

**It must suit everyone** — a non-technical owner who just wants a compliant banner with sane defaults *and* an advanced user who wants to tune it. Defaults = what GDPR mandates or best practice advises; everything beyond that is an option, never a requirement.

## Status & backlog (as of this point)

Functionally complete and shipping from `main` at **v0.0.1** (not yet smoke-tested in a live WP — validated by `php -l`/`node --check` + the build pipeline only). French (`fr_FR`) is kept at **0 fuzzy / 0 untranslated** every build via a translation subagent.

**Open work (the user will pick what's next):**
- **Layout redesign** — the settings still stack vertically ("scroll-simulator"). Wants multi-column layouts, a **categories modal**, and **Gutenberg-style unit controls** (slider + value + default, validated) for the size/radius fields. Also: detect-cookies step inside the wizard, drag-reorder buttons.
- **Safe mode** — optional "block ALL cross-origin resources by default; unknown/unauthorised → placeholder advising the visitor to contact the site owner via an admin-set contact page." Needs a `safe_mode` option, a block-everything path in `Blocking\Blocker`, an "unknown" placeholder, and a `contact_page_id` option + UI.
- **Granular scan UI** — replace the scope dropdown with per-type checkboxes (home forced, pages, posts, each CPT) + per-type limit + newest/oldest order.
- **Responsive-embed void** — for oEmbeds in a `.wp-block-embed` wrapper, the theme reserves aspect-ratio space, so a thin gap can remain around the (correctly-sized) placeholder; needs wrapper-targeted CSS.
- **Scan inside the wizard** — the setup wizard still asks yes/no per service instead of actually scanning. Run the real scan (reuse `scanUrls`/`scanDb`/`renderScan` — refactor them to be reusable, don't duplicate) as a wizard step, with the same scope/warning UI; prefer querying over asking (more reliable, fewer steps).
- **Scheduled scan + notify** — periodically scan a chosen set of pages (wp-cron) and alert the admin (mechanism TBD: admin notice / email) when new third-party resources appear so they can add rules.
- **Uninstall data policy** — `uninstall.php` currently drops everything. Decide what should be retained/exportable (esp. the consent-proof log, which may be legally needed after uninstall) and add an opt-in "keep my data on uninstall" setting.

## Heritage — two sibling plugins, two different things to borrow

This plugin lives beside other LRob plugins. Conventions are lifted from two of them:

- **`../lrob-email-toolkit`** — take its **architecture and discipline**: hand-rolled PSR-4 autoloader, `Plugin`/`Container`/`Activator`/`Deactivator` lifecycle, `final` classes + `declare(strict_types=1)` + constructor promotion, the GitHub `Updater`, the `release.sh` build + translation gate, the `anonymise_ip()` helper, and the UTC-epoch SQL bucket pattern. This is the structural model.
- **`../lrob-age-gate`** — take **only three things**: the back-office **live-preview** of the front render (the fiddly, hard-won part), the **appearance/customization settings** model (theme Auto/Light/Dark/Custom, CSS-variable colors), and *maybe* its **cookie storage** approach. Do **not** copy its flat-class / `includes/` structure or its `manage_options` cap — we use the email-toolkit shape instead.

Complianz 7.5.0 is **inspiration for behavior only** (what a good blocking engine + consent state machine should do). We write our own code; never copy Complianz source.

## Build / lint / release

`./release.sh` is the single build entry point. Run it yourself whenever needed; `./release.sh 2>&1 | tail -40` shows what matters. It lints every PHP (`php -l`) + JS (`node --check`) file, scans `assets/css/*.css` for dead `.lrob-cc-*` selectors, regenerates the POT + `msgmerge`s into every `.po`, compiles `.mo`/`.json`, and zips into `../releases/lrob-cookie-consent-<version>.zip`. The zip excludes dev-only files (`CLAUDE.md`, `README.md`, `spec.md`, `*.sh`, `.git`, etc.).

No PHPUnit / PHPCS / PHPStan config — don't invent commands.

### 🚫 RELEASE GATE — translations are non-negotiable before tagging

Before any release, the `./release.sh` `msgfmt` line MUST read `N translated, 0 fuzzy, 0 untranslated`. A release with partial translation is broken — users hit untranslated fragments mid-flow. If the line isn't clean, STOP and fix the `.po` first. Dev is in **English** (source); French + other EU locales are translated later, at milestone boundaries, not per commit.

## Versioning

- **+0.0.1 (patch)** — small adjustments; iterations stack at the same version while testing. Bump only on the user's explicit ship cue. **Ask the user for the version number — don't decide it.**
- **+0.1.0 (minor)** — a meaningful subsystem shipped.

Single source of truth: `lrob-cookie-consent.php` carries both the `Version:` header and the `LROB_CC_VERSION` constant — **bump them together**. `1.0.0` when stable enough to declare. Pre-1.0 the schema can change freely between bumps (no back-compat shims). Don't pre-bump or auto-commit; wait for the user's cue. Git repo will be created later, on the user's cue.

## Naming convention — **MANDATORY**

Prefixes must be plugin-specific. Several LRob plugins coexist; bare `lrob_` collides. This plugin's token is **`cc`** (= "cookie consent"), everywhere a runtime identifier appears.

| Layer | Prefix | Examples |
|---|---|---|
| PHP namespace | `LRob\CookieConsent\` | `LRob\CookieConsent\Blocking\Blocker` |
| Functions (global) | `lrob_cc_` | `lrob_cc_get_options()` |
| Hooks (actions/filters) | `lrob_cc_` | `lrob_cc_block_rules`, `lrob_cc_enable_category` |
| Constants | `LROB_CC_` | `LROB_CC_VERSION`, `LROB_CC_PATH` |
| Options | `lrob_cc_options` (single array) + `lrob_cc_db_version` | |
| DB tables | `{wpdb->prefix}lrob_cc_` | `wp_lrob_cc_consent_log` |
| REST namespace | `lrob-cc/v1` | `/wp-json/lrob-cc/v1/log` |
| Capability | `manage_lrob_cc` | granted to `administrator` on activate |
| Cookies | `lrob_cc_consent`, `lrob_cc_status` | |
| Text domain | `lrob-cookie-consent` | (human-readable slug) |
| CSS classes / JS globals | `lrob-cc-` / `lrobCc` | `lrob-cc-banner`, `window.lrobCc` |

Anything added — option key, table, hook, CSS class — **must** follow these prefixes. No exceptions.

## Architecture

**Entry point** (`lrob-cookie-consent.php`): defines constants, registers a hand-rolled PSR-4 autoloader (`LRob\CookieConsent\Foo\Bar` → `src/Foo/Bar.php`), boots `Plugin::instance()->boot()` on `plugins_loaded`. No Composer at runtime by design.

**Lifecycle**: `Activator::activate()` grants `manage_lrob_cc` to administrator, seeds `lrob_cc_options` (merge over existing, never clobber), and `dbDelta`s the consent-log table (`Consent\Schema::create()`). `Deactivator::deactivate()` clears any `lrob_cc_*` cron (none planned v1; keep ready). `uninstall.php` drops every `{prefix}lrob_cc_*` table + every `lrob_cc_*` option + the capability (belt-and-suspenders prefix scan).

**Container** (`src/Container.php`): tiny `set()`/`get()`/`has()` service locator; constructor injection is the norm.

**Single-purpose** — no `ModuleManager`. `Plugin::boot()` loads the text domain, registers the `Updater`, then wires admin vs front based on context (`is_admin()`).

**Subsystems** (see `spec.md` for the full brief):
- `Blocking\Blocker` (§3) — always output-buffers the front response on `template_redirect` (no "enqueued-only" mode — it was removed), rewrites `<script>`/`<iframe>` matching admin rules to `text/plain` / `data-src`; consent.js builds the sized click-to-load placeholders. Skip buffering for admin/REST/AJAX/cron/feed/POST/bots/(logged-in when off). `block_iframes` toggle (default on). **Never enforces `functional` rules** (`enforceable_rules()` excludes them) — functional entries are references for necessary cookies, documented but not blocked.
- `Frontend\{Banner,Assets}` (§4–§5) — banner markup + the `assets/js/consent.js` state machine, public `window.lrobCc` API, category body classes, optional WP Consent API mirroring. **FSE-first appearance**: defaults inherit the theme's global-style tokens (`--wp--preset--color--*`, font sizes, button styling) via `wp_get_global_settings()` so a fresh install looks native with zero customization. Theme mode Auto (follow site, default) / Light / Dark (forced palettes) / Custom. Every color is a `.lrob-cc-banner` CSS variable — no hardcoded colors. Style + text presets (PHP `Support\Presets`, `__()`-translatable) lower the customization burden; both filterable (`lrob_cc_style_presets`, `lrob_cc_text_presets`).
- `Consent\{Schema,LogRepository,RestController,BannerVersion}` (§6) — legally-robust proof of consent (schema v4, migrates via `Schema::maybe_upgrade()`). One row per event: anonymous `consent_id` (subject), granular per-purpose `choices` JSON, `method`+`payload` (the act), `event_type` (consent/update/withdraw), `banner_version` → `BannerVersion` snapshot (text + category labels + **what each category blocks**; orphan-pruned), `expires_at` (~13mo), IP (`ip_storage` hashed default/full), opt-in UA + `user_id`. Logging **on by default**. Admin: `Admin\ConsentLogTable` (`WP_List_Table`, per-row/bulk delete, CSV).
- `Admin\SettingsPage` (§7) — single Settings-API page, tabs General / Banner / **Cookies** / Log, single `lrob_cc_options` array, **live preview** (age-gate pattern), segmented (button) controls, guided rule editor + raw mode, scan + wizard AJAX, clickable "?" help toggles.
- `Support\Categories` — `functional` is hardcoded/forced-on. Optional categories = **immutable built-in defaults** (`preferences/statistics/marketing/embed/security`, computed — NOT stored) + **custom** ones (`lrob_cc_options['categories']` holds customs only, so new built-ins appear for everyone and old saves can't freeze them out). Everything iterates `Categories::optional()`/`labels()` — never hardcode. Rules referencing an invalid category are dropped, so keep this list authoritative.
- `Scanning\{Scanner,LocalScanner,ScanProvider}` (§9b) — two modes: **database** (reads all published `post_content`, broad host map catches oEmbed-by-URL) and **visit pages** (anonymous `wp_remote_get` of a selectable scope — home + pages/posts/CPTs, ≤50, listed). AJAX `lrob_cc_scan_targets`/`_url`/`_db`/`_search_pages`. Provider seam reserved for a remote deep-scan.
- `AutoUpdate\Updater` (§8) — port of email-toolkit's GitHub updater for repo `LRob-FR/wp-lrob-cookie-consent`.

## Conventions to follow

- **Strict types**: every PHP file in `src/` starts with `declare(strict_types=1);`.
- **Final classes** unless explicitly meant for subclassing.
- **Constructor property promotion** — PHP 8.2+.
- **No mock/stub/fallback paths for things that can't happen.** Trust internal callers; validate only at WP REST/admin/form boundaries.
- **Comments: minimal.** One-line WHY only where non-obvious; never narrate WHAT. Never strip load-bearing comments: the WP plugin header, `/* translators: */` notes, lint directives.
- **No backwards-compat shims** while version < 1.0.0.
- **Security throughout**: nonces on every admin action + AJAX (`check_ajax_referer` + `manage_lrob_cc`), sanitize on input, escape on output.
- **No `window.confirm/alert/prompt`** in admin — build a minimal self-contained in-plugin confirm for destructive actions (e.g. purge log). Don't pull email-toolkit's `etk-confirm.js`.
- **Appearance**: every color on `.lrob-cc-banner` is a CSS variable — **no hardcoded colors anywhere** (this is the original Complianz grievance that started the plugin).

## SQL + timezone

WordPress stores `DATETIME` in the **server session timezone** — unstable across hosts/DST. Avoid `UNIX_TIMESTAMP()`. For any time-bucketed aggregation:

```sql
FLOOR(TIMESTAMPDIFF(SECOND, '2000-01-01 00:00:00', created_at) / %d) * %d + 946684800 AS bucket_ts
```

`946684800` is the UTC epoch of `2000-01-01 00:00:00`. Render times browser-local via JS `Date`, not server-formatted strings.

## Deployment note

The user runs the plugin from the release zip, not the working tree. **Every PHP change must be followed by `./release.sh`** before claiming a fix is live.
