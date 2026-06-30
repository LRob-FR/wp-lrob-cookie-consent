<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

/**
 * A small curated list of common third-party services, offered as one-click
 * "quick add" rule helpers in the admin. This is a convenience for authoring
 * rules — NOT a hidden auto-blocking database: a rule only takes effect once
 * the admin adds it. Filterable so sites can extend it.
 */
final class Services
{
    /** @return list<array{label:string,pattern:string,category:string,service:string}> */
    public static function common(): array
    {
        $services = [
            ['label' => 'Google Analytics', 'pattern' => 'google-analytics.com', 'category' => 'statistics', 'service' => 'Google Analytics'],
            ['label' => 'Google Tag Manager', 'pattern' => 'googletagmanager.com', 'category' => 'statistics', 'service' => 'Google Tag Manager'],
            ['label' => 'Matomo', 'pattern' => 'matomo.js', 'category' => 'statistics', 'service' => 'Matomo'],
            ['label' => 'Hotjar', 'pattern' => 'hotjar.com', 'category' => 'statistics', 'service' => 'Hotjar'],
            ['label' => 'Facebook / Meta Pixel', 'pattern' => 'connect.facebook.net', 'category' => 'marketing', 'service' => 'Facebook'],
            ['label' => 'Google Ads', 'pattern' => 'googleadservices.com', 'category' => 'marketing', 'service' => 'Google Ads'],
            ['label' => 'LinkedIn Insight', 'pattern' => 'snap.licdn.com', 'category' => 'marketing', 'service' => 'LinkedIn'],
            ['label' => 'Gravatar', 'pattern' => 'gravatar.com', 'category' => 'preferences', 'service' => 'Gravatar'],
            // Embedded external content → the "external content" category (not
            // inherently marketing).
            ['label' => 'Google Maps', 'pattern' => 'maps.google.com', 'category' => 'embed', 'service' => 'Google Maps'],
            ['label' => 'Google Maps embed', 'pattern' => 'google.com/maps/embed', 'category' => 'embed', 'service' => 'Google Maps'],
            ['label' => 'YouTube', 'pattern' => 'youtube.com/embed', 'category' => 'embed', 'service' => 'YouTube'],
            ['label' => 'YouTube (no-cookie)', 'pattern' => 'youtube-nocookie.com', 'category' => 'embed', 'service' => 'YouTube'],
            ['label' => 'Vimeo', 'pattern' => 'player.vimeo.com', 'category' => 'embed', 'service' => 'Vimeo'],
            ['label' => 'Dailymotion', 'pattern' => 'dailymotion.com/embed', 'category' => 'embed', 'service' => 'Dailymotion'],
            ['label' => 'X / Twitter', 'pattern' => 'platform.twitter.com', 'category' => 'embed', 'service' => 'X (Twitter)'],
            ['label' => 'Instagram', 'pattern' => 'instagram.com/embed', 'category' => 'embed', 'service' => 'Instagram'],
            ['label' => 'TikTok', 'pattern' => 'tiktok.com/embed', 'category' => 'embed', 'service' => 'TikTok'],
            ['label' => 'Facebook embeds', 'pattern' => 'facebook.com/plugins', 'category' => 'embed', 'service' => 'Facebook'],
            // CAPTCHAs default to necessary (functional): they're injected by JS, so
            // blocking just silently breaks the form with no placeholder to click.
            // Turnstile/hCaptcha are privacy-friendly; advanced users can move
            // reCAPTCHA to a blockable category if they accept the broken-form risk.
            ['label' => 'Cloudflare Turnstile', 'pattern' => 'challenges.cloudflare.com', 'category' => 'functional', 'service' => 'Cloudflare Turnstile'],
            ['label' => 'hCaptcha', 'pattern' => 'hcaptcha.com', 'category' => 'functional', 'service' => 'hCaptcha'],
            ['label' => 'Google reCAPTCHA', 'pattern' => 'recaptcha', 'category' => 'functional', 'service' => 'Google reCAPTCHA'],
            // Necessary (functional) — referenced for transparency, never blocked.
            ['label' => 'Stripe (payments)', 'pattern' => 'js.stripe.com', 'category' => 'functional', 'service' => 'Stripe'],
            ['label' => 'PayPal (payments)', 'pattern' => 'paypal.com', 'category' => 'functional', 'service' => 'PayPal'],
            // External fonts: loading them from Google sends the visitor's IP to
            // Google (a GDPR concern). Self-hosting is the fix; blocking breaks
            // layout. Referenced as functional so admins are aware.
            ['label' => 'Google Fonts (self-host instead)', 'pattern' => 'fonts.googleapis.com', 'category' => 'functional', 'service' => 'Google Fonts'],
        ];
        return apply_filters('lrob_cc_common_services', $services);
    }

    /**
     * The site's own WordPress cookies, declared as necessary (functional)
     * entries. The scanner only finds third-party *resources*, never first-party
     * cookies, so these are offered as a one-click declaration for transparency.
     * The patterns are cookie-name prefixes that never match a script URL, so
     * they are listed but never block anything.
     *
     * @return list<array{label:string,pattern:string,category:string,service:string}>
     */
    public static function wordpressCookies(): array
    {
        $list = [
            ['pattern' => 'wordpress_logged_in_', 'service' => __('WordPress login session', 'lrob-cookie-consent')],
            ['pattern' => 'wp-settings-',          'service' => __('WordPress preferences', 'lrob-cookie-consent')],
            ['pattern' => 'comment_author_',       'service' => __('Comment form', 'lrob-cookie-consent')],
            ['pattern' => 'wp-postpass_',          'service' => __('Password-protected posts', 'lrob-cookie-consent')],
        ];
        if (class_exists('WooCommerce')) {
            $list[] = ['pattern' => 'woocommerce_', 'service' => __('WooCommerce cart & checkout', 'lrob-cookie-consent')];
        }
        $out = [];
        foreach ($list as $c) {
            $out[] = ['label' => $c['service'], 'pattern' => $c['pattern'], 'category' => 'functional', 'service' => $c['service']];
        }
        return apply_filters('lrob_cc_wordpress_cookies', $out);
    }

    /**
     * Question-driven quick-setup steps. Each step asks one yes/no-style question
     * and offers the services relevant to it.
     *
     * @return list<array{question:string,hint:string,services:list<array{label:string,pattern:string,category:string,service:string}>}>
     */
    public static function wizard(): array
    {
        $by = [];
        foreach (self::common() as $svc) {
            $by[$svc['pattern']] = $svc;
        }
        $pick = static function (string ...$patterns) use ($by): array {
            $out = [];
            foreach ($patterns as $p) {
                if (isset($by[$p])) {
                    $out[] = $by[$p];
                }
            }
            return $out;
        };

        $steps = [
            [
                'question' => __('Do you measure visitor statistics or analytics?', 'lrob-cookie-consent'),
                'hint'     => __('Tools that count visits and analyse behaviour. Note: Matomo can run in a cookieless mode — if yours does, you may not need to block it.', 'lrob-cookie-consent'),
                'services' => $pick('google-analytics.com', 'googletagmanager.com', 'matomo.js', 'hotjar.com'),
            ],
            [
                'question' => __('Do you embed external content (videos, social posts)?', 'lrob-cookie-consent'),
                'hint'     => __('Players and social embeds can set cookies before consent — they go under "External content", not marketing.', 'lrob-cookie-consent'),
                'services' => $pick('youtube.com/embed', 'player.vimeo.com', 'dailymotion.com/embed', 'platform.twitter.com', 'instagram.com/embed', 'tiktok.com/embed', 'facebook.com/plugins'),
            ],
            [
                'question' => __('Do you run ads or marketing / conversion pixels?', 'lrob-cookie-consent'),
                'hint'     => __('Advertising and retargeting trackers.', 'lrob-cookie-consent'),
                'services' => $pick('googleadservices.com', 'connect.facebook.net', 'snap.licdn.com'),
            ],
            [
                'question' => __('Do you embed maps?', 'lrob-cookie-consent'),
                'hint'     => __('Map embeds may load third-party scripts.', 'lrob-cookie-consent'),
                'services' => $pick('maps.google.com'),
            ],
            [
                'question' => __('Do you use a CAPTCHA?', 'lrob-cookie-consent'),
                'hint'     => __('Listed as necessary by default so your forms keep working — a JavaScript-injected CAPTCHA can\'t show a click-to-load placeholder, so blocking it just breaks the form. Turnstile and hCaptcha are privacy-friendly; reCAPTCHA loads Google.', 'lrob-cookie-consent'),
                'services' => $pick('challenges.cloudflare.com', 'hcaptcha.com', 'recaptcha'),
            ],
            [
                'question' => __('Do you use payment gateways?', 'lrob-cookie-consent'),
                'hint'     => __('Listed as necessary — referenced for transparency but never blocked, so checkout keeps working.', 'lrob-cookie-consent'),
                'services' => $pick('js.stripe.com', 'paypal.com'),
            ],
        ];

        return apply_filters('lrob_cc_wizard_steps', $steps);
    }
}

