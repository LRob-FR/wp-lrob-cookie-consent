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

    private const DEFAULTS = ['preferences', 'statistics', 'marketing', 'security'];

    public static function default_label(string $slug): string
    {
        return match ($slug) {
            'functional'  => __('Functional', 'lrob-cookie-consent'),
            'preferences' => __('Preferences', 'lrob-cookie-consent'),
            'statistics'  => __('Statistics', 'lrob-cookie-consent'),
            'marketing'   => __('Marketing', 'lrob-cookie-consent'),
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
            'security'    => __('Spam protection, CAPTCHAs and firewalls.', 'lrob-cookie-consent'),
            default       => '',
        };
    }

    /**
     * Raw optional category definitions (admin overrides), or the defaults when
     * none are stored. functional is never in this list.
     *
     * @return list<array{slug:string,label:string,desc:string}>
     */
    public static function stored(): array
    {
        $opt = Options::get('categories');
        $out = [];
        if (is_array($opt)) {
            foreach ($opt as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $slug = sanitize_key((string) ($c['slug'] ?? ''));
                if ($slug === '' || $slug === self::FUNCTIONAL || isset($out[$slug])) {
                    continue;
                }
                $out[$slug] = ['slug' => $slug, 'label' => (string) ($c['label'] ?? ''), 'desc' => (string) ($c['desc'] ?? '')];
            }
        }
        if ($out === []) {
            foreach (self::DEFAULTS as $slug) {
                $out[$slug] = ['slug' => $slug, 'label' => '', 'desc' => ''];
            }
        }
        return array_values($out);
    }

    /** @return list<string> optional category slugs, in order */
    public static function optional(): array
    {
        return array_map(static fn (array $c): string => $c['slug'], self::stored());
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
    public static function labels(): array
    {
        $out = [self::FUNCTIONAL => [
            'title' => self::default_label(self::FUNCTIONAL),
            'desc'  => self::default_desc(self::FUNCTIONAL),
        ]];
        foreach (self::stored() as $c) {
            $out[$c['slug']] = [
                'title' => $c['label'] !== '' ? $c['label'] : self::default_label($c['slug']),
                'desc'  => $c['desc'] !== '' ? $c['desc'] : self::default_desc($c['slug']),
            ];
        }
        return apply_filters('lrob_cc_category_labels', $out);
    }
}
