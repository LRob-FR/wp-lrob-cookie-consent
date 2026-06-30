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
            [
                'id'      => 'embeds',
                'label'   => __('External content', 'lrob-cookie-consent'),
                'header'  => __('External content & your privacy', 'lrob-cookie-consent'),
                'message' => __('This page can load content from other services — videos, maps, fonts and similar — which may receive your data (such as your IP address). Choose what you allow to load.', 'lrob-cookie-consent'),
                'accept'  => __('Allow all content', 'lrob-cookie-consent'),
                'deny'    => __('Block external content', 'lrob-cookie-consent'),
                'save'    => __('Save my choices', 'lrob-cookie-consent'),
            ],
            [
                'id'      => 'embeds-minimal',
                'label'   => __('External content (minimal)', 'lrob-cookie-consent'),
                'header'  => __('External content', 'lrob-cookie-consent'),
                'message' => __('We load some content from third-party services. Choose what may load.', 'lrob-cookie-consent'),
                'accept'  => __('Allow all', 'lrob-cookie-consent'),
                'deny'    => __('Essential only', 'lrob-cookie-consent'),
                'save'    => __('Save', 'lrob-cookie-consent'),
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
                [
                    'id'    => 'forest',
                    'label' => __('Forest', 'lrob-cookie-consent'),
                    'options' => [
                        'theme' => 'custom', 'color_bg' => '#f3f7f2', 'color_text' => '#22372b',
                        'color_title' => '#14301f', 'color_border' => '#cfe0d3', 'color_btn_bg' => '#15803d',
                        'color_btn_text' => '#ffffff', 'color_btn_deny_bg' => '#e3ede4', 'color_btn_deny_text' => '#22372b',
                        'color_btn_hover_bg' => '#166534', 'color_btn_deny_hover_bg' => '#d3e2d6',
                    ],
                ],
                [
                    'id'    => 'rose',
                    'label' => __('Rose', 'lrob-cookie-consent'),
                    'options' => [
                        'theme' => 'custom', 'color_bg' => '#fff5f7', 'color_text' => '#5b2333',
                        'color_title' => '#7a1f3d', 'color_border' => '#f3d4dd', 'color_btn_bg' => '#e11d62',
                        'color_btn_text' => '#ffffff', 'color_btn_deny_bg' => '#fbe3ea', 'color_btn_deny_text' => '#5b2333',
                        'color_btn_hover_bg' => '#be123c', 'color_btn_deny_hover_bg' => '#f5d2dd',
                    ],
                ],
                [
                    'id'    => 'slate',
                    'label' => __('Slate', 'lrob-cookie-consent'),
                    'options' => [
                        'theme' => 'custom', 'color_bg' => '#f8fafc', 'color_text' => '#334155',
                        'color_title' => '#0f172a', 'color_border' => '#e2e8f0', 'color_btn_bg' => '#475569',
                        'color_btn_text' => '#ffffff', 'color_btn_deny_bg' => '#eef2f6', 'color_btn_deny_text' => '#334155',
                        'color_btn_hover_bg' => '#334155', 'color_btn_deny_hover_bg' => '#e2e8f0',
                    ],
                ],
                [
                    'id'    => 'grape',
                    'label' => __('Grape', 'lrob-cookie-consent'),
                    'options' => [
                        'theme' => 'custom', 'color_bg' => '#faf5ff', 'color_text' => '#44337a',
                        'color_title' => '#3b1d6b', 'color_border' => '#e9d5ff', 'color_btn_bg' => '#7c3aed',
                        'color_btn_text' => '#ffffff', 'color_btn_deny_bg' => '#f0e6fb', 'color_btn_deny_text' => '#44337a',
                        'color_btn_hover_bg' => '#6d28d9', 'color_btn_deny_hover_bg' => '#e6d5f7',
                    ],
                ],
            ],
            'shape' => [],
            'size'  => [],
        ]);
    }

    /**
     * Whole-layout presets — one click sets position, size, spacing, corners,
     * backdrop and entrance animation together. The UI always pairs these with a
     * "Custom" escape hatch so the 10% who want to fine-tune still can.
     *
     * @return list<array{id:string,label:string,options:array<string,mixed>}>
     */
    public static function layouts(): array
    {
        return apply_filters('lrob_cc_layout_presets', [
            [
                'id'    => 'corner-card',
                'label' => __('Corner card', 'lrob-cookie-consent'),
                'options' => [
                    'position' => 'bottom-right', 'popup_size' => 'small', 'density' => 'cozy',
                    'shape' => 'rounded', 'backdrop' => 'none', 'offset_preset' => 'default',
                    'anim_fade' => 1, 'anim_move' => 'slide', 'anim_direction' => 'bottom', 'anim_speed' => 500,
                ],
            ],
            [
                'id'    => 'center-modal',
                'label' => __('Centered modal', 'lrob-cookie-consent'),
                'options' => [
                    'position' => 'center', 'popup_size' => 'medium', 'density' => 'comfortable',
                    'shape' => 'rounded', 'backdrop' => 'blur', 'backdrop_dim' => 50, 'backdrop_blur' => 6,
                    'anim_fade' => 1, 'anim_move' => 'zoom', 'anim_speed' => 400,
                ],
            ],
            [
                'id'    => 'slim-bar',
                'label' => __('Slim bar', 'lrob-cookie-consent'),
                'options' => [
                    'position' => 'bottom', 'popup_size' => 'large', 'density' => 'compact',
                    'shape' => 'square', 'backdrop' => 'none', 'offset_preset' => 'snug',
                    'anim_fade' => 1, 'anim_move' => 'slide', 'anim_direction' => 'bottom', 'anim_speed' => 350,
                ],
            ],
            [
                'id'    => 'big-bold',
                'label' => __('Big & bold', 'lrob-cookie-consent'),
                'options' => [
                    'position' => 'bottom-left', 'popup_size' => 'large', 'density' => 'comfortable',
                    'shape' => 'pill', 'backdrop' => 'dim', 'backdrop_dim' => 40, 'offset_preset' => 'spacious',
                    'anim_fade' => 1, 'anim_move' => 'slide', 'anim_direction' => 'left', 'anim_speed' => 550,
                ],
            ],
            [
                'id'    => 'discreet',
                'label' => __('Discreet', 'lrob-cookie-consent'),
                'options' => [
                    'position' => 'bottom-left', 'popup_size' => 'small', 'density' => 'compact',
                    'shape' => 'rounded', 'backdrop' => 'none', 'offset_preset' => 'snug',
                    'anim_fade' => 1, 'anim_move' => 'none', 'anim_speed' => 300,
                ],
            ],
        ]);
    }
}
