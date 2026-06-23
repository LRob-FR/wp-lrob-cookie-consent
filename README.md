# LRob - Cookie Consent

A lean, opinionated **GDPR / ePrivacy cookie-consent** plugin for WordPress — an opt-in banner, a script & iframe blocking engine, and proof of consent. Built as a focused, no-bloat alternative to heavyweight consent plugins, for LRob and client managed-hosting sites.

- **Opt-in by default** (strict EU model): nothing optional loads until the visitor agrees.
- **FSE-first appearance**: the banner inherits your block theme's colors, fonts and buttons, so it looks native with zero configuration. Light / Dark / Custom themes and one-click style presets are there when you want them.
- **No bloat**: no cookie scanner, no policy generator, no packaged third-party integrations, no tracking of you. Plain PHP, vanilla JS, server-rendered admin. The only outbound request is the GitHub updater.

Requires **PHP 8.2+** and **WordPress 6.8+**.

## Features

- Accessible consent banner (`role="dialog"`, focus trap, keyboard-dismissible) with four fixed categories: **functional** (always on), **preferences**, **statistics**, **marketing**.
- **Blocking engine** that neutralises third-party `<script>` and `<iframe>` tags before consent and re-activates them the moment the matching category is accepted. Blocked embeds show a click-to-load placeholder.
- **Admin-declared rules** — you decide what to block; there is no hidden services database.
- **Inline-script injection** — paste a GA4 / Matomo snippet and pick a category, no theme editing.
- **Proof of consent** (optional) — timestamp, anonymised IP, accepted categories, config version, stored locally with CSV export and automatic retention purge.
- **Live preview** of the banner in the settings page.
- **GitHub auto-updates**.
- **WP Consent API** support (soft dependency): category decisions are mirrored via `wp_set_consent()` when that plugin is active.

## Setup

1. Install and activate. **Nothing is blocked until you add rules** — this is a safe default.
2. Go to **Settings → Cookie Consent**.
3. **Banner** tab: pick a text preset (or write your own) and a style preset, or just leave it on *Auto* to follow your theme. Watch the live preview.
4. **Blocking** tab: add one rule per line —
   ```
   pattern | category | service name
   ```
   for example:
   ```
   google-analytics.com | statistics | Google Analytics
   googletagmanager.com  | statistics | Google Tag Manager
   connect.facebook.net  | marketing  | Facebook
   youtube.com/embed     | marketing  | YouTube
   ```
   `pattern` is matched against script/iframe `src` (and inline script bodies). `category` is `preferences`, `statistics` or `marketing`.
5. Optionally enable **proof of consent** in the **General** tab.

### Block method

- **Full-page scan** (recommended) buffers the page output and catches every matching tag, including hardcoded theme scripts and iframes.
- **Enqueued scripts only** is lighter but only rewrites WordPress-enqueued scripts — it won't catch hardcoded embeds or iframes.

## Shortcode

Place a "manage cookie preferences" link anywhere to re-open the banner:

```
[lrob_cc_manage text="Cookie settings"]
```

## JavaScript API

Available on `window.lrobCc`:

- `lrobCc.hasConsent(category)` → boolean (`functional` is always true)
- `lrobCc.acceptAll()`, `lrobCc.denyAll()`
- `lrobCc.setConsent(category, 'allow' | 'deny')`
- `lrobCc.acceptedCategories()` → array
- `lrobCc.showBanner()`, `lrobCc.hideBanner()`

Events dispatched on `document`:

- `lrob_cc_enable_category` — `detail: { category, categories }`
- `lrob_cc_status_change` — `detail: { categories, consent }`

## Filters

- `lrob_cc_block_rules` — add/modify parsed block rules in code.
- `lrob_cc_category_labels` — customise category titles/descriptions.
- `lrob_cc_text_presets` / `lrob_cc_style_presets` — register your own presets.

## Privacy

The consent banner sets two first-party cookies (`lrob_cc_consent`, `lrob_cc_status`). Proof-of-consent logging is **off by default**; when enabled, the IP is **anonymised by default** (IPv4 → /24, IPv6 → /48) and the user-agent is stored only if you opt in. Uninstalling removes the log table, all options, and the capability.

## License

GPL-2.0+ — © [LRob](https://www.lrob.fr)
