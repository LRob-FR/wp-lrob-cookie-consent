<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

final class Options
{
    public const OPTION_KEY = 'lrob_cc_options';

    /**
     * Default option set. Defaults encode GDPR-mandated / best-practice
     * behaviour; everything beyond that is opt-in. Texts are left empty and
     * fall back to translated strings at render time.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'enabled'            => 1,
            'consent_type'       => 'optin',   // v1: opt-in only (strict EU model)
            'accept_days'        => 365,        // remember an acceptance ~12 months before re-asking
            'deny_days'          => 180,        // remember a refusal 6 months (CNIL July 2025 minimum)
            'respect_dnt'        => 1,          // honour DNT/GPC as "deny optional"
            'dnt_hide_banner'    => 0,          // still ask by default (DNT is ambiguous)
            'show_to_logged_in'  => 1,          // banner behaves the same for admins

            // Blocking engine
            'block_iframes'      => 1,          // off = GDPR risk (warned in UI)
            'categories'         => [],         // optional category defs [{slug,label,desc}]; empty = built-in defaults
            'cat_desc_overrides' => [],         // built-in slug => custom description (names stay fixed)
            'block_rules'        => '',         // one per line: pattern | category | service
            'rules_mode'         => 'structured', // structured | raw (admin editor preference)
            'inline_scripts'     => [],         // [ ['code' => '', 'category' => ''], ... ]
            'reprompt_on_rule_change' => 0,     // off: re-prompt only when active categories change

            // Proof of consent
            'log_consent'        => 1,          // on by default (advised for GDPR accountability)
            'ip_storage'         => 'hashed',   // hashed (default) | full | none — a subject_id is always logged regardless
            'store_user_agent'   => 0,
            'store_wp_user'      => 1,          // record the logged-in WP user id
            'log_retention_days' => 395,        // proof kept ≥ consent lifetime (warned if below)
            'keep_data_on_uninstall' => 1,      // preserve the consent-proof tables on uninstall (legal default)

            // Appearance
            'position'           => 'bottom-right', // top-left|top|top-right|center|bottom-left|bottom|bottom-right
            'offset_preset'      => 'default',  // snug|default|spacious|custom (custom reveals value+unit)
            'offset_x'           => 24,         // custom: distance from the side edge
            'offset_y'           => 24,         // custom: distance from the top/bottom edge
            'offset_unit'        => 'px',       // px|rem|em|vw|% — scalable units recommended
            'theme'              => 'auto',     // auto|light|dark|custom|...palettes
            'popup_size'         => 'small',    // small|medium|large (width)
            'density'            => 'cozy',     // compact|cozy|comfortable (spacing)
            'font_size'          => 'medium',   // small|medium|large
            'shape'              => 'rounded',  // square|rounded|pill (corner radius)
            'backdrop'           => 'none',     // none | dim | blur — overlay behind the banner (any position)
            'backdrop_dim'       => 50,         // overlay darkness (% black) when backdrop = dim or blur
            'backdrop_blur'      => 6,          // blur strength (px) when backdrop = blur
            'logo'               => '',
            'align_title'        => 'left',     // left|center|right
            'align_text'         => 'left',
            'align_buttons'      => 'left',
            'align_footer'       => 'center',   // footer links default centred
            'logo_height'        => 36,         // px max-height of the banner logo
            'footer_links'       => [],         // [ ['label'=>'', 'url'=>''], ... ]

            // Buttons + disclosure
            'show_accept'        => 1,          // one-click "Accept all" visible by default
            'show_deny'          => 1,          // symmetric Deny visible by default
            'show_customize'     => 1,          // "Customize" button (collapsed view only)
            'deny_style'         => 'button',   // button | link ("Continue without accepting")
            'deny_link_position' => 'under-buttons', // under-buttons | under-box | top | near-close
            'continue_align'     => 'center',   // left | center | right — alignment of the Continue link
            'continue_arrow'     => 1,          // append a "→" to the Continue link
            'button_order'       => ['accept', 'deny', 'customize'], // Save is contextual, not reorderable
            'categories_collapsed' => 1,        // hide category toggles behind a "Customize" link
            'revisit_button'     => 1,          // floating "manage cookies" button after a decision
            'revisit_text'       => '',         // label (empty → translated "Manage cookies")
            'revisit_position'   => 'follow',   // follow (banner corner) | bottom-left | bottom-right | top-left | top-right
            'show_sources'       => 1,          // show visitors what each category blocks
            'watermark'          => 1,          // "Cookie Consent by LRob" footer credit

            // Animation + timing
            'show_delay'         => 1000,       // ms before the banner first appears
            'anim_fade'          => 1,          // fade in
            'anim_move'          => 'none',     // none | slide | zoom (combines with fade)
            'anim_direction'     => 'bottom',   // slide origin: top | bottom | left | right
            'anim_speed'         => 500,        // ms animation duration

            // Custom-theme colors (applied only when theme = custom)
            'color_bg'             => '#ffffff',
            'color_text'           => '#1a1a1a',
            'color_title'          => '#111111',
            'color_border'         => '#e2e2e2',
            'color_btn_bg'         => '#1a1a1a',
            'color_btn_text'       => '#ffffff',
            'color_btn_deny_bg'    => '#f0f0f0',
            'color_btn_deny_text'  => '#1a1a1a',
            'color_btn_hover_bg'   => '',        // empty → auto (darken on hover)
            'color_btn_deny_hover_bg' => '',     // empty → auto (darken on hover)

            // Texts (empty → translated fallback at render)
            'text_header'        => '',
            'text_message'       => '',
            'text_accept'        => '',
            'text_deny'          => '',
            'text_save'          => '',
            'text_customize'     => '',
            'text_continue'      => '',         // empty → "Continue without accepting"
            'text_preset'        => '',         // remembered preset id, or 'custom'
        ];
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge(self::defaults(), $stored);
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }
}
