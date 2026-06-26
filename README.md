# LRob - Cookie Consent

A lean, opinionated **GDPR / ePrivacy cookie-consent** plugin for WordPress — an opt-in banner, a script & iframe blocking engine, and legally-robust proof of consent. Built as a focused, no-bloat alternative to heavyweight consent plugins, for LRob and client managed-hosting sites.

- **Opt-in by default** (strict EU model): nothing optional loads until the visitor agrees.
- **FSE-first appearance**: the banner inherits your block theme's colors, fonts and buttons, so it looks native with zero configuration. Light / Dark / Midnight / Ocean / Sand / Custom themes and presets are there when you want them.
- **No bloat**: no policy generator, no packaged third-party integrations, no tracking of you. Plain PHP, vanilla JS, server-rendered admin. The only outbound request is the GitHub updater.

Requires **PHP 8.2+** and **WordPress 6.8+**.

## Features

- Accessible consent banner (`role="dialog"`, focus trap, ESC-dismissible). `functional` is always-on; the **optional categories are admin-managed** — built-in **preferences / statistics / marketing / external content / security** (immutable) plus any **custom** ones you add.
- **Blocking engine** (full-page output buffer) that neutralises third-party `<script>` and `<iframe>` tags before consent and re-activates them when the matching category is accepted. Blocked embeds show a sized, click-to-load placeholder naming the source.
- **Auto-detect** — scan your site (database content, or by visiting pages with a selectable scope + page counts) to find third-party scripts/embeds and add them as rules. A curated, grouped service list backs quick-add and detection (`lrob_cc_common_services`, `lrob_cc_detection_map`, `lrob_cc_scan_providers`).
- **Reference necessary cookies** — list your own/site or payment-gateway cookies under `functional`: documented in the audit trail but never blocked.
- **Inline-script injection** — paste a GA4 / Matomo snippet and pick a category, no theme editing.
- **Proof of consent** (on by default) — per-event record with an anonymous subject ID, **granular per-purpose decisions**, the act (button + raw payload), the **versioned banner/blocking context** shown at the time, hashed or full IP, ~13-month renewal, configurable retention purge, and a **`WP_List_Table` audit view** with per-row/bulk delete and self-contained CSV export.
- **Live preview** of the banner in the settings page. **GitHub auto-updates**. **WP Consent API** mirroring (soft dependency).

## Setup

1. Install and activate. **Nothing is blocked until rules exist** — and the banner only shows once there is something to manage.
2. Go to **Settings → Cookie Consent** (or the **Configure** link on the Plugins screen). One **Save** covers the General, Banner and Cookies tabs.
3. **Cookies** tab → **Auto-detect my cookies** to scan and add rules, or add them by hand (guided editor or raw text). A rule is `pattern | category | service name`; the pattern is matched against script/iframe `src` and inline script bodies.
4. **Banner** tab: pick a text/style preset or leave colors on *Auto* to follow your theme; watch the live preview.
5. Proof of consent is in the **General** tab; the audit log + text versions are in the **Log** tab.

## Shortcode

```
[lrob_cc_manage text="Cookie settings"]
```

Re-opens the banner. An optional floating "Manage cookies" button does the same.

## JavaScript API (`window.lrobCc`)

- `hasConsent(category)` → boolean (`functional` always true)
- `acceptAll()`, `denyAll()`, `setConsent(category, 'allow'|'deny')`, `acceptedCategories()`
- `showBanner()`, `hideBanner()`

Events on `document`: `lrob_cc_enable_category` (`detail: {category, categories}`) and `lrob_cc_status_change` (`detail: {categories, consent}`).

## Filters

- `lrob_cc_block_rules` — add/modify parsed block rules in code.
- `lrob_cc_category_labels` — customise category titles/descriptions.
- `lrob_cc_common_services` / `lrob_cc_detection_map` — extend the quick-add list and the scanner's host detection.
- `lrob_cc_scan_providers` — register a scan provider (e.g. a future remote deep-scan).
- `lrob_cc_text_presets` / `lrob_cc_style_presets` — register your own presets.

## Privacy

The banner sets two first-party cookies (`lrob_cc_consent` — includes an anonymous subject id — and `lrob_cc_status`). Proof-of-consent logging is **on by default** (advised for GDPR accountability); the IP is stored **hashed** by default (salted SHA-256) or optionally in full, the user-agent and logged-in user are opt-in, and proof is purged per your retention setting. Uninstalling removes the log + version tables, all options, and the capability.

## License

GPL-2.0+ — © [LRob](https://www.lrob.fr)
