<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

/**
 * functional is hardcoded, always-on and force-accepted. The optional categories
 * are admin-managed (stored in options), seeded with sensible defaults. Default
 * slugs keep translatable labels; custom categories use admin-provided text.
 */
final class Categories
{
    public const FUNCTIONAL = 'functional';

    private const DEFAULTS = ['preferences', 'statistics', 'marketing', 'embed', 'security'];

    public static function default_label(string $slug): string
    {
        return match ($slug) {
            'functional'  => __('Functional', 'lrob-cookie-consent'),
            'preferences' => __('Preferences', 'lrob-cookie-consent'),
            'statistics'  => __('Statistics', 'lrob-cookie-consent'),
            'marketing'   => __('Marketing', 'lrob-cookie-consent'),
            'embed'       => __('External content', 'lrob-cookie-consent'),
            'security'    => __('Security', 'lrob-cookie-consent'),
            default       => ucfirst(str_replace(['-', '_'], ' ', $slug)),
        };
    }

    public static function default_desc(string $slug): string
    {
        return match ($slug) {
            'functional'  => __('Required for the site to work. Always allowed.', 'lrob-cookie-consent'),
            'preferences' => __('Remembers choices you make (language, region, layout).', 'lrob-cookie-consent'),
            'statistics'  => __('Anonymous measurement of how the site is used.', 'lrob-cookie-consent'),
            'marketing'   => __('Used to track visitors for advertising.', 'lrob-cookie-consent'),
            'embed'       => __('Content loaded from other sites — videos, maps, social posts, images, 3D views and similar embeds (YouTube, Google Maps, etc.).', 'lrob-cookie-consent'),
            'security'    => __('Spam protection, CAPTCHAs and firewalls.', 'lrob-cookie-consent'),
            default       => '',
        };
    }

    public static function is_default(string $slug): bool
    {
        return $slug === self::FUNCTIONAL || in_array($slug, self::DEFAULTS, true);
    }

    /** Built-in optional slugs (immutable, always present, fixed order). @return list<string> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * Admin-added custom categories only — the option stores nothing else, so
     * built-in defaults are computed and can never be edited or frozen out.
     *
     * @return list<array{slug:string,label:string,desc:string}>
     */
    public static function custom(): array
    {
        $opt = Options::get('categories');
        $out = [];
        if (is_array($opt)) {
            foreach ($opt as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $slug = sanitize_key((string) ($c['slug'] ?? ''));
                if ($slug === '' || self::is_default($slug) || isset($out[$slug])) {
                    continue;
                }
                $out[$slug] = ['slug' => $slug, 'label' => (string) ($c['label'] ?? ''), 'desc' => (string) ($c['desc'] ?? '')];
            }
        }
        return array_values($out);
    }

    /** @return list<string> optional slugs: immutable defaults, then customs */
    public static function optional(): array
    {
        $slugs = self::DEFAULTS;
        foreach (self::custom() as $c) {
            $slugs[] = $c['slug'];
        }
        return $slugs;
    }

    /** @return list<string> functional first, then the optional ones */
    public static function all(): array
    {
        return array_merge([self::FUNCTIONAL], self::optional());
    }

    public static function is_valid(string $slug): bool
    {
        return in_array($slug, self::all(), true);
    }

    /**
     * Resolved titles/descriptions for functional + optional categories.
     *
     * @return array<string,array{title:string,desc:string}>
     */
    /**
     * Admin description overrides for built-in categories (names stay fixed).
     *
     * @return array<string,string> slug => description
     */
    public static function desc_overrides(): array
    {
        $opt = Options::get('cat_desc_overrides');
        return is_array($opt) ? $opt : [];
    }

    public static function labels(): array
    {
        $overrides = self::desc_overrides();
        $out = [];
        foreach (array_merge([self::FUNCTIONAL], self::DEFAULTS) as $slug) {
            $desc = isset($overrides[$slug]) && (string) $overrides[$slug] !== '' ? (string) $overrides[$slug] : self::default_desc($slug);
            $out[$slug] = ['title' => self::default_label($slug), 'desc' => $desc];
        }
        foreach (self::custom() as $c) {
            $out[$c['slug']] = [
                'title' => $c['label'] !== '' ? $c['label'] : self::default_label($c['slug']),
                'desc'  => $c['desc'],
            ];
        }
        return apply_filters('lrob_cc_category_labels', $out);
    }
}
