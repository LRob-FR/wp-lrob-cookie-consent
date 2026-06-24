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
            'cookie_days'        => 365,
            'respect_dnt'        => 1,          // honour DNT/GPC as "deny optional"
            'dnt_hide_banner'    => 0,          // still ask by default (DNT is ambiguous)
            'show_to_logged_in'  => 1,          // banner behaves the same for admins

            // Blocking engine
            'block_method'       => 'full',     // 'full' (page scan) | 'enqueued'
            'block_iframes'      => 1,          // off = GDPR risk (warned in UI)
            'categories'         => [],         // optional category defs [{slug,label,desc}]; empty = built-in defaults
            'block_rules'        => '',         // one per line: pattern | category | service
            'rules_mode'         => 'structured', // structured | raw (admin editor preference)
            'inline_scripts'     => [],         // [ ['code' => '', 'category' => ''], ... ]
            'reprompt_on_rule_change' => 0,     // off: re-prompt only when active categories change

            // Proof of consent
            'log_consent'        => 1,          // on by default (advised for GDPR accountability)
            'ip_storage'         => 'hashed',   // hashed | full | none
            'store_user_agent'   => 0,
            'log_retention_days' => 365,

            // Appearance
            'position'           => 'bottom',   // bottom|center|bottom-left|bottom-right
            'theme'              => 'auto',     // auto|light|dark|custom
            'popup_size'         => 'small',    // small|medium|large (width)
            'density'            => 'cozy',     // compact|cozy|comfortable (spacing)
            'font_size'          => 'medium',   // small|medium|large
            'shape'              => 'rounded',  // square|rounded|pill (corner radius)
            'backdrop_blur'      => 0,
            'logo'               => '',

            // Buttons + disclosure
            'show_deny'          => 1,          // symmetric Deny visible by default
            'show_save'          => 1,          // Save-preferences button
            'categories_collapsed' => 1,        // hide category toggles behind a "Customize" link
            'revisit_button'     => 1,          // floating "manage cookies" button after a decision
            'revisit_text'       => '',         // label (empty → translated "Manage cookies")

            // Custom-theme colors (applied only when theme = custom)
            'color_bg'             => '#ffffff',
            'color_text'           => '#1a1a1a',
            'color_title'          => '#111111',
            'color_border'         => '#e2e2e2',
            'color_btn_bg'         => '#1a1a1a',
            'color_btn_text'       => '#ffffff',
            'color_btn_deny_bg'    => '#f0f0f0',
            'color_btn_deny_text'  => '#1a1a1a',

            // Texts (empty → translated fallback at render)
            'text_header'        => '',
            'text_message'       => '',
            'text_accept'        => '',
            'text_deny'          => '',
            'text_save'          => '',
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
