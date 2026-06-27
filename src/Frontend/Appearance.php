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

    /** Edge-distance presets; "custom" reveals a value + unit control instead. */
    public const OFFSET_PRESETS = ['snug' => '12px', 'default' => '24px', 'spacious' => '44px'];

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
                'btn-hover-bg' => 'color_btn_hover_bg', 'btn-deny-hover-bg' => 'color_btn_deny_hover_bg',
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

        $preset = (string) Options::get('offset_preset');
        if (isset(self::OFFSET_PRESETS[$preset])) {
            $vars['--lrob-cc-offset-x'] = self::OFFSET_PRESETS[$preset];
            $vars['--lrob-cc-offset-y'] = self::OFFSET_PRESETS[$preset];
        } else {
            $unit = (string) Options::get('offset_unit');
            $unit = in_array($unit, ['px', 'rem', 'em', 'vw', '%'], true) ? $unit : 'px';
            $vars['--lrob-cc-offset-x'] = max(0, (int) Options::get('offset_x')) . $unit;
            $vars['--lrob-cc-offset-y'] = max(0, (int) Options::get('offset_y')) . $unit;
        }
        $vars['--lrob-cc-logo-height'] = max(12, (int) Options::get('logo_height')) . 'px';

        // Entrance animation, composed from independent fade / move / easing controls.
        $vars['--lrob-cc-anim-duration'] = max(0, (int) Options::get('anim_speed')) . 'ms';
        $vars['--lrob-cc-anim-ease'] = (string) Options::get('anim_easing') === 'bounce'
            ? 'cubic-bezier(0.34, 1.56, 0.64, 1)' : 'ease';
        $vars['--lrob-cc-anim-opacity'] = (int) Options::get('anim_fade') === 1 ? '0' : '1';
        $x = '0';
        $y = '0';
        $scale = '1';
        $move = (string) Options::get('anim_move');
        if ($move === 'slide') {
            $d = '24px';
            [$x, $y] = match ((string) Options::get('anim_direction')) {
                'top'   => ['0', '-' . $d],
                'left'  => ['-' . $d, '0'],
                'right' => [$d, '0'],
                default => ['0', $d], // bottom
            };
        } elseif ($move === 'zoom') {
            $scale = '0.92';
        }
        $vars['--lrob-cc-anim-x'] = $x;
        $vars['--lrob-cc-anim-y'] = $y;
        $vars['--lrob-cc-anim-scale'] = $scale;

        $vars['--lrob-cc-align-title'] = self::align((string) Options::get('align_title'));
        $vars['--lrob-cc-align-text'] = self::align((string) Options::get('align_text'));
        $vars['--lrob-cc-align-footer'] = self::align((string) Options::get('align_footer'), 'center');
        $vars['--lrob-cc-align-buttons'] = self::ALIGN_FLEX[(string) Options::get('align_buttons')] ?? 'flex-start';

        $decl = '';
        foreach ($vars as $name => $value) {
            $decl .= $name . ':' . $value . ';';
        }

        return '#lrob-cc-banner{' . $decl . '}';
    }

    private static function align(string $value, string $default = 'left'): string
    {
        return in_array($value, ['left', 'center', 'right'], true) ? $value : $default;
    }
}
