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
            ['label' => 'YouTube embeds', 'pattern' => 'youtube.com/embed', 'category' => 'marketing', 'service' => 'YouTube'],
            ['label' => 'Vimeo embeds', 'pattern' => 'player.vimeo.com', 'category' => 'marketing', 'service' => 'Vimeo'],
            ['label' => 'Google Maps', 'pattern' => 'maps.google.com', 'category' => 'preferences', 'service' => 'Google Maps'],
            ['label' => 'LinkedIn Insight', 'pattern' => 'snap.licdn.com', 'category' => 'marketing', 'service' => 'LinkedIn'],
        ];
        return apply_filters('lrob_cc_common_services', $services);
    }
}
