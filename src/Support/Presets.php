<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

/**
 * Built-in presets, defined in PHP so the messages AND button labels are
 * translatable (gettext can't extract from JSON). Filterable for client sites.
 */
final class Presets
{
    /** @return list<array<string,string>> */
    public static function text(): array
    {
        return apply_filters('lrob_cc_text_presets', [
            [
                'id'      => 'neutral',
                'label'   => __('Neutral (default)', 'lrob-cookie-consent'),
                'header'  => __('We value your privacy', 'lrob-cookie-consent'),
                'message' => __('We use cookies to improve your experience, analyse traffic and personalise content. You can accept all, deny non-essential cookies, or choose by category.', 'lrob-cookie-consent'),
                'accept'  => __('Accept all', 'lrob-cookie-consent'),
                'deny'    => __('Deny', 'lrob-cookie-consent'),
                'save'    => __('Save preferences', 'lrob-cookie-consent'),
            ],
            [
                'id'      => 'minimal',
                'label'   => __('Minimal', 'lrob-cookie-consent'),
                'header'  => __('Cookies', 'lrob-cookie-consent'),
                'message' => __('We use cookies. Choose what you allow.', 'lrob-cookie-consent'),
                'accept'  => __('Accept', 'lrob-cookie-consent'),
                'deny'    => __('Deny', 'lrob-cookie-consent'),
                'save'    => __('Save', 'lrob-cookie-consent'),
            ],
            [
                'id'      => 'fun',
                'label'   => __('Friendly 🍪', 'lrob-cookie-consent'),
                'header'  => __('Cookie time! 🍪', 'lrob-cookie-consent'),
                'message' => __('We bake a few cookies to keep things running smoothly and to see what you love most. Pick your flavour below — no hard feelings if you pass.', 'lrob-cookie-consent'),
                'accept'  => __('Yes please', 'lrob-cookie-consent'),
                'deny'    => __('No thanks', 'lrob-cookie-consent'),
                'save'    => __('Save my choices', 'lrob-cookie-consent'),
            ],
            [
                'id'      => 'shop',
                'label'   => __('Shop 🛒', 'lrob-cookie-consent'),
                'header'  => __('A few cookies to keep the shop running 🍪', 'lrob-cookie-consent'),
                'message' => __("Some cookies keep your cart and checkout working; others help us understand what sells. Allow the ones you're comfortable with.", 'lrob-cookie-consent'),
                'accept'  => __('Accept all', 'lrob-cookie-consent'),
                'deny'    => __('Essential only', 'lrob-cookie-consent'),
                'save'    => __('Save preferences', 'lrob-cookie-consent'),
            ],
        ]);
    }

    /** @return array<string,list<array<string,mixed>>> */
    public static function styles(): array
    {
        return apply_filters('lrob_cc_style_presets', [
            'colors' => [
                [
                    'id'    => 'neutral',
                    'label' => __('Neutral', 'lrob-cookie-consent'),
                    'options' => [
                        'theme' => 'custom', 'color_bg' => '#ffffff', 'color_text' => '#3c3c3c',
                        'color_title' => '#1a1a1a', 'color_border' => '#e2e2e2', 'color_btn_bg' => '#2563eb',
                        'color_btn_text' => '#ffffff', 'color_btn_deny_bg' => '#f1f1f1', 'color_btn_deny_text' => '#1a1a1a',
                        'color_btn_hover_bg' => '#1d4ed8', 'color_btn_deny_hover_bg' => '#e2e2e2',
                    ],
                ],
                [
                    'id'    => 'high-contrast',
                    'label' => __('High contrast', 'lrob-cookie-consent'),
                    'options' => [
                        'theme' => 'custom', 'color_bg' => '#000000', 'color_text' => '#ffffff',
                        'color_title' => '#ffffff', 'color_border' => '#ffffff', 'color_btn_bg' => '#ffff00',
                        'color_btn_text' => '#000000', 'color_btn_deny_bg' => '#222222', 'color_btn_deny_text' => '#ffffff',
                        'color_btn_hover_bg' => '#e6e600', 'color_btn_deny_hover_bg' => '#333333',
                    ],
                ],
            ],
            'shape' => [],
            'size'  => [],
        ]);
    }
}
