<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Frontend;

use LRob\CookieConsent\Support\Categories;
use LRob\CookieConsent\Support\Options;
use LRob\CookieConsent\Support\Rules;
use LRob\CookieConsent\Support\Visitor;

final class Assets
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_head', [$this, 'print_inline_scripts'], 99);
        add_action('wp_footer', [$this, 'render_banner']);
    }

    /** Admin-declared snippets injected inert (text/plain); consent.js runs them on consent. */
    public function print_inline_scripts(): void
    {
        if (!Visitor::consent_active()) {
            return;
        }
        foreach (Rules::compiled()['inline'] as $i => $script) {
            printf(
                '<script type="text/plain" data-category="%s" data-service="%s">%s</script>' . "\n",
                esc_attr($script['category']),
                esc_attr($script['name'] !== '' ? $script['name'] : 'inline-' . $i),
                $script['code'] // Trusted (manage_lrob_cc) input; inert until consent.
            );
        }
    }

    public function enqueue(): void
    {
        if (!Visitor::consent_active()) {
            return;
        }

        wp_enqueue_style('lrob-cc-banner', LROB_CC_URL . 'assets/css/banner.css', [], lrob_cc_asset_ver('assets/css/banner.css'));
        wp_add_inline_style('lrob-cc-banner', Appearance::inline_css());

        wp_enqueue_script('lrob-cc-consent', LROB_CC_URL . 'assets/js/consent.js', [], lrob_cc_asset_ver('assets/js/consent.js'), true);
        wp_localize_script('lrob-cc-consent', 'lrobCcData', $this->data());
    }

    public function render_banner(): void
    {
        if (!Visitor::consent_active()) {
            return;
        }
        echo Banner::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup escaped in view.
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return [
            'cookieName'    => 'lrob_cc_consent',
            'statusCookie'  => 'lrob_cc_status',
            'cookieDays'    => (int) Options::get('cookie_days'),
            'version'       => Rules::version(),
            // Only categories actually proposed (that block something) are tracked,
            // so consent isn't asked or recorded for empty categories.
            'categories'    => array_merge(['functional'], Rules::active_categories()),
            'optional'      => Rules::active_categories(),
            'catLabels'     => array_map(static fn (array $l): string => $l['title'], Categories::labels()),
            'respectDnt'    => (int) Options::get('respect_dnt') === 1,
            'dntHideBanner' => (int) Options::get('dnt_hide_banner') === 1,
            'revisitButton' => (int) Options::get('revisit_button') === 1,
            'revisitText'   => (string) Options::get('revisit_text'),
            'position'      => (string) Options::get('position'),
            'showDelay'     => (int) Options::get('show_delay'),
            'bannerVersion' => \LRob\CookieConsent\Consent\BannerVersion::ensure(),
            'rest'          => [
                'url'        => esc_url_raw(rest_url('lrob-cc/v1/log')),
                'nonce'      => wp_create_nonce('wp_rest'),
                'logConsent' => (int) Options::get('log_consent') === 1,
            ],
            'i18n'          => [
                /* translators: %s: name of the blocked service (e.g. YouTube). */
                'embedTitle'   => __('%s content blocked', 'lrob-cookie-consent'),
                /* translators: %s: cookie category name (e.g. External content). */
                'embedNote'    => __('Loads once you accept “%s”.', 'lrob-cookie-consent'),
                'acceptLoad'   => __('Accept & load', 'lrob-cookie-consent'),
                'manageCookies' => __('Manage cookies', 'lrob-cookie-consent'),
            ],
        ];
    }
}
