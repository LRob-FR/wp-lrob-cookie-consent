<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Frontend;

use LRob\CookieConsent\Support\Options;

/**
 * Builds the inline CSS-variable block scoped to the banner. Auto theme sets no
 * color vars — banner.css falls back to FSE/theme tokens. Light/Dark force a
 * fixed palette; Custom uses the admin's picked colors. Width / spacing / font /
 * radius come from independent scale controls.
 */
final class Appearance
{
    private const PALETTES = [
        'light' => [
            'bg' => '#ffffff', 'text' => '#1a1a1a', 'title' => '#111111', 'border' => '#e2e2e2',
            'btn-bg' => '#1a1a1a', 'btn-text' => '#ffffff', 'btn-deny-bg' => '#f0f0f0', 'btn-deny-text' => '#1a1a1a',
        ],
        'dark' => [
            'bg' => '#1c1c1e', 'text' => '#e8e8e8', 'title' => '#ffffff', 'border' => '#3a3a3c',
            'btn-bg' => '#ffffff', 'btn-text' => '#111111', 'btn-deny-bg' => '#2c2c2e', 'btn-deny-text' => '#e8e8e8',
        ],
        'midnight' => [
            'bg' => '#0f172a', 'text' => '#cbd5e1', 'title' => '#f8fafc', 'border' => '#334155',
            'btn-bg' => '#38bdf8', 'btn-text' => '#0f172a', 'btn-deny-bg' => '#1e293b', 'btn-deny-text' => '#cbd5e1',
        ],
        'ocean' => [
            'bg' => '#f0f9ff', 'text' => '#0c4a6e', 'title' => '#075985', 'border' => '#bae6fd',
            'btn-bg' => '#0284c7', 'btn-text' => '#ffffff', 'btn-deny-bg' => '#e0f2fe', 'btn-deny-text' => '#075985',
        ],
        'sand' => [
            'bg' => '#fdf6ec', 'text' => '#52452f', 'title' => '#3f3522', 'border' => '#e7d9c0',
            'btn-bg' => '#b45309', 'btn-text' => '#ffffff', 'btn-deny-bg' => '#f1e6d3', 'btn-deny-text' => '#52452f',
        ],
    ];

    private const ALIGN_FLEX = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];

    private const WIDTH = ['small' => '340px', 'medium' => '420px', 'large' => '540px'];
    private const DENSITY = [
        'compact'     => ['pad' => '14px', 'gap' => '8px'],
        'cozy'        => ['pad' => '18px', 'gap' => '12px'],
        'comfortable' => ['pad' => '26px', 'gap' => '18px'],
    ];
    private const FONT = [
        'small'  => ['font' => '0.82rem', 'title' => '1rem'],
        'medium' => ['font' => '0.92rem', 'title' => '1.15rem'],
        'large'  => ['font' => '1.02rem', 'title' => '1.35rem'],
    ];
    private const RADIUS = ['square' => '0px', 'rounded' => '10px', 'pill' => '22px'];

    /** @return array<string,array<string,string>> */
    public static function palettes(): array
    {
        return self::PALETTES;
    }

    /** @return array{width:array<string,string>,density:array<string,array<string,string>>,font:array<string,array<string,string>>,radius:array<string,string>} */
    public static function scales(): array
    {
        return ['width' => self::WIDTH, 'density' => self::DENSITY, 'font' => self::FONT, 'radius' => self::RADIUS];
    }

    public static function inline_css(): string
    {
        $vars = [];

        $theme = (string) Options::get('theme');
        if (isset(self::PALETTES[$theme])) {
            foreach (self::PALETTES[$theme] as $key => $value) {
                $vars['--lrob-cc-' . $key] = $value;
            }
        } elseif ($theme === 'custom') {
            $map = [
                'bg' => 'color_bg', 'text' => 'color_text', 'title' => 'color_title', 'border' => 'color_border',
                'btn-bg' => 'color_btn_bg', 'btn-text' => 'color_btn_text',
                'btn-deny-bg' => 'color_btn_deny_bg', 'btn-deny-text' => 'color_btn_deny_text',
            ];
            foreach ($map as $key => $opt) {
                $color = sanitize_hex_color((string) Options::get($opt));
                if ($color) {
                    $vars['--lrob-cc-' . $key] = $color;
                }
            }
        }

        $vars['--lrob-cc-width'] = self::WIDTH[(string) Options::get('popup_size')] ?? self::WIDTH['small'];
        $density = self::DENSITY[(string) Options::get('density')] ?? self::DENSITY['cozy'];
        $vars['--lrob-cc-pad'] = $density['pad'];
        $vars['--lrob-cc-gap'] = $density['gap'];
        $font = self::FONT[(string) Options::get('font_size')] ?? self::FONT['medium'];
        $vars['--lrob-cc-font-size'] = $font['font'];
        $vars['--lrob-cc-title-size'] = $font['title'];
        $vars['--lrob-cc-radius'] = self::RADIUS[(string) Options::get('shape')] ?? self::RADIUS['rounded'];
        $vars['--lrob-cc-blur'] = max(0, (int) Options::get('backdrop_blur')) . 'px';

        $vars['--lrob-cc-align-title'] = self::align((string) Options::get('align_title'));
        $vars['--lrob-cc-align-text'] = self::align((string) Options::get('align_text'));
        $vars['--lrob-cc-align-buttons'] = self::ALIGN_FLEX[(string) Options::get('align_buttons')] ?? 'flex-start';

        $decl = '';
        foreach ($vars as $name => $value) {
            $decl .= $name . ':' . $value . ';';
        }

        return '#lrob-cc-banner{' . $decl . '}';
    }

    private static function align(string $value): string
    {
        return in_array($value, ['left', 'center', 'right'], true) ? $value : 'left';
    }
}
