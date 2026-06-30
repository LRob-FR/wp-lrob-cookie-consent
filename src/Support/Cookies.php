<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

/**
 * Curated database of well-known cookies: name (or name prefix) → service,
 * category, party (first/third) and a plain-language description. The real
 * browser scan reads the actual cookie names on the site; this map turns those
 * raw names into something an admin (and a visitor) can understand. Unknown
 * cookies are still listed — the admin fills in the blanks.
 *
 * Categories use this plugin's slugs. Party: 'first' (set on the site's own
 * domain, readable by the scan) or 'third' (set by an external service on its
 * own domain — detected via the resource, never read).
 */
final class Cookies
{
    /**
     * @return list<array{match:string,prefix:bool,service:string,category:string,party:string,desc:string}>
     */
    public static function known(): array
    {
        $f = static fn (string $match, bool $prefix, string $service, string $category, string $party, string $desc): array
            => ['match' => $match, 'prefix' => $prefix, 'service' => $service, 'category' => $category, 'party' => $party, 'desc' => $desc];

        $list = [
            // --- WordPress core (necessary) ---
            $f('wordpress_logged_in_', true, 'WordPress', 'functional', 'first', __('Keeps you signed in.', 'lrob-cookie-consent')),
            $f('wordpress_sec_', true, 'WordPress', 'functional', 'first', __('Secures the logged-in session.', 'lrob-cookie-consent')),
            $f('wp-settings-', true, 'WordPress', 'functional', 'first', __('Remembers your admin screen preferences.', 'lrob-cookie-consent')),
            $f('comment_author_', true, 'WordPress', 'functional', 'first', __('Pre-fills the comment form.', 'lrob-cookie-consent')),
            $f('wp-postpass_', true, 'WordPress', 'functional', 'first', __('Unlocks password-protected posts.', 'lrob-cookie-consent')),
            $f('wordpress_test_cookie', false, 'WordPress', 'functional', 'first', __('Checks that cookies work.', 'lrob-cookie-consent')),

            // --- WooCommerce (necessary) ---
            $f('woocommerce_cart_hash', false, 'WooCommerce', 'functional', 'first', __('Tracks changes to your cart.', 'lrob-cookie-consent')),
            $f('woocommerce_items_in_cart', false, 'WooCommerce', 'functional', 'first', __('Counts the items in your cart.', 'lrob-cookie-consent')),
            $f('wp_woocommerce_session_', true, 'WooCommerce', 'functional', 'first', __('Keeps your cart and checkout working.', 'lrob-cookie-consent')),
            $f('storeApi', true, 'WooCommerce', 'functional', 'first', __('Powers the block-based cart and checkout.', 'lrob-cookie-consent')),
            $f('woocommerce_recently_viewed', false, 'WooCommerce', 'preferences', 'first', __('Remembers products you viewed.', 'lrob-cookie-consent')),

            // --- WooCommerce order attribution (Sourcebuster) — analytics ---
            $f('sbjs_', true, 'WooCommerce order attribution', 'statistics', 'first', __('Records how a visitor reached the site (marketing source).', 'lrob-cookie-consent')),

            // --- Stripe (necessary: payments / fraud) ---
            $f('__stripe_mid', false, 'Stripe', 'functional', 'first', __('Fraud prevention for payments.', 'lrob-cookie-consent')),
            $f('__stripe_sid', false, 'Stripe', 'functional', 'first', __('Fraud prevention for payments (session).', 'lrob-cookie-consent')),

            // --- Google Analytics ---
            $f('_ga', false, 'Google Analytics', 'statistics', 'first', __('Distinguishes visitors for traffic statistics.', 'lrob-cookie-consent')),
            $f('_ga_', true, 'Google Analytics', 'statistics', 'first', __('Persists the analytics session state.', 'lrob-cookie-consent')),
            $f('_gid', false, 'Google Analytics', 'statistics', 'first', __('Distinguishes visitors (24 hours).', 'lrob-cookie-consent')),
            $f('_gat', true, 'Google Analytics', 'statistics', 'first', __('Throttles the request rate.', 'lrob-cookie-consent')),

            // --- Google Ads / Tag Manager ---
            $f('_gcl_', true, 'Google Ads', 'marketing', 'first', __('Measures ad conversions.', 'lrob-cookie-consent')),

            // --- Meta / Facebook ---
            $f('_fbp', false, 'Meta (Facebook)', 'marketing', 'first', __('Identifies browsers for advertising.', 'lrob-cookie-consent')),
            $f('_fbc', false, 'Meta (Facebook)', 'marketing', 'first', __('Stores the last ad click for attribution.', 'lrob-cookie-consent')),
            $f('fr', false, 'Meta (Facebook)', 'marketing', 'third', __('Advertising and ad measurement.', 'lrob-cookie-consent')),

            // --- TikTok ---
            $f('_ttp', false, 'TikTok', 'marketing', 'first', __('Measures and improves ad performance.', 'lrob-cookie-consent')),

            // --- Pinterest ---
            $f('_pin_unauth', false, 'Pinterest', 'marketing', 'first', __('Groups actions for non-logged-in users.', 'lrob-cookie-consent')),
            $f('_epik', false, 'Pinterest', 'marketing', 'first', __('Conversion measurement.', 'lrob-cookie-consent')),

            // --- LinkedIn ---
            $f('bcookie', false, 'LinkedIn', 'marketing', 'third', __('Shares features and advertising.', 'lrob-cookie-consent')),
            $f('lidc', false, 'LinkedIn', 'marketing', 'third', __('Routes to a data centre.', 'lrob-cookie-consent')),

            // --- Microsoft Clarity ---
            $f('_clck', false, 'Microsoft Clarity', 'statistics', 'first', __('Session replay and heatmaps.', 'lrob-cookie-consent')),
            $f('_clsk', false, 'Microsoft Clarity', 'statistics', 'first', __('Session replay (current session).', 'lrob-cookie-consent')),

            // --- Hotjar ---
            $f('_hj', true, 'Hotjar', 'statistics', 'first', __('Behaviour analytics and heatmaps.', 'lrob-cookie-consent')),

            // --- Matomo ---
            $f('_pk_id', true, 'Matomo', 'statistics', 'first', __('Distinguishes visitors for analytics.', 'lrob-cookie-consent')),
            $f('_pk_ses', true, 'Matomo', 'statistics', 'first', __('Stores the analytics session.', 'lrob-cookie-consent')),
            $f('mtm_', true, 'Matomo', 'statistics', 'first', __('Tag-manager analytics.', 'lrob-cookie-consent')),

            // --- YouTube / Google video (embeds, third-party) ---
            $f('VISITOR_INFO1_LIVE', false, 'YouTube', 'embed', 'third', __('Estimates bandwidth for the player.', 'lrob-cookie-consent')),
            $f('YSC', false, 'YouTube', 'embed', 'third', __('Remembers video views in the session.', 'lrob-cookie-consent')),
            $f('VISITOR_PRIVACY_METADATA', false, 'YouTube', 'embed', 'third', __('Stores video privacy state.', 'lrob-cookie-consent')),

            // --- Vimeo ---
            $f('vuid', false, 'Vimeo', 'embed', 'third', __('Remembers playback preferences.', 'lrob-cookie-consent')),

            // --- CAPTCHA / security (necessary, but external) ---
            $f('_GRECAPTCHA', false, 'Google reCAPTCHA', 'functional', 'third', __('Tells humans from bots to protect forms.', 'lrob-cookie-consent')),
            $f('__cf_bm', false, 'Cloudflare', 'functional', 'first', __('Bot management to protect the site.', 'lrob-cookie-consent')),
            $f('cf_clearance', false, 'Cloudflare', 'functional', 'first', __('Stores the result of a security challenge.', 'lrob-cookie-consent')),

            // --- DoubleClick ---
            $f('IDE', false, 'Google DoubleClick', 'marketing', 'third', __('Ad targeting and measurement.', 'lrob-cookie-consent')),
            $f('test_cookie', false, 'Google DoubleClick', 'marketing', 'third', __('Checks that the browser supports cookies.', 'lrob-cookie-consent')),

            // --- Consent platforms (so we recognise an existing one) ---
            $f('axeptio_', true, 'Axeptio', 'functional', 'first', __('Stores your consent choices.', 'lrob-cookie-consent')),
            $f('cmplz_', true, 'Complianz', 'functional', 'first', __('Stores your consent choices.', 'lrob-cookie-consent')),
            $f('cookielawinfo', true, 'CookieYes', 'functional', 'first', __('Stores your consent choices.', 'lrob-cookie-consent')),
            $f('borlabs-cookie', false, 'Borlabs Cookie', 'functional', 'first', __('Stores your consent choices.', 'lrob-cookie-consent')),
            $f('OptanonConsent', false, 'OneTrust', 'functional', 'first', __('Stores your consent choices.', 'lrob-cookie-consent')),
            $f('lrob_cc_consent', false, 'LRob Cookie Consent', 'functional', 'first', __('Stores your consent choices.', 'lrob-cookie-consent')),
            $f('lrob_cc_status', false, 'LRob Cookie Consent', 'functional', 'first', __('Remembers that you answered the banner.', 'lrob-cookie-consent')),
        ];

        return apply_filters('lrob_cc_known_cookies', $list);
    }

    /**
     * Best-match metadata for a raw cookie name, or null if unknown. Longer, more
     * specific matches win over short prefixes.
     *
     * @return array{service:string,category:string,party:string,desc:string}|null
     */
    public static function classify(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $best = null;
        $bestLen = -1;
        foreach (self::known() as $c) {
            $hit = $c['prefix'] ? str_starts_with($name, $c['match']) : strcasecmp($name, $c['match']) === 0;
            if ($hit && strlen($c['match']) > $bestLen) {
                $best = $c;
                $bestLen = strlen($c['match']);
            }
        }
        if ($best === null) {
            return null;
        }
        return ['service' => $best['service'], 'category' => $best['category'], 'party' => $best['party'], 'desc' => $best['desc']];
    }

    /**
     * Other consent / cookie-banner plugins that are active — they gate trackers
     * and would skew the real-cookie scan, so the UI warns about them.
     *
     * @return list<string>
     */
    public static function activeCmpPlugins(): array
    {
        $known = [
            'complianz-gdpr/complianz-gpdr.php'              => 'Complianz',
            'complianz-gdpr-premium/complianz-gpdr-premium.php' => 'Complianz',
            'cookie-law-info/cookie-law-info.php'           => 'CookieYes',
            'cookie-notice/cookie-notice.php'               => 'Cookie Notice',
            'borlabs-cookie/borlabs-cookie.php'             => 'Borlabs Cookie',
            'gdpr-cookie-consent/gdpr-cookie-consent.php'   => 'WP Cookie Consent',
            'cookiebot/cookiebot.php'                       => 'Cookiebot',
            'wp-axeptio/wp-axeptio.php'                     => 'Axeptio',
            'onetrust/onetrust.php'                         => 'OneTrust',
        ];
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $found = [];
        foreach ($known as $file => $label) {
            if (is_plugin_active($file) && !in_array($label, $found, true)) {
                $found[] = $label;
            }
        }
        return $found;
    }
}
