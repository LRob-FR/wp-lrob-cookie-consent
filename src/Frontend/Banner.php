<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Frontend;

use LRob\CookieConsent\Support\Categories;
use LRob\CookieConsent\Support\Options;

final class Banner
{
    public function register(): void
    {
        add_shortcode('lrob_cc_manage', [$this, 'manage_shortcode']);
    }

    /** @param array<string,mixed>|string $atts */
    public function manage_shortcode($atts = []): string
    {
        $atts = shortcode_atts(['text' => __('Manage cookie preferences', 'lrob-cookie-consent')], (array) $atts, 'lrob_cc_manage');
        ob_start();
        $label = (string) $atts['text'];
        include LROB_CC_PATH . 'views/manage-link.php';
        return (string) ob_get_clean();
    }

    /**
     * The translated default texts (the fallbacks used when a field is empty).
     * Used for admin placeholders so they show the real default, not whatever is
     * currently saved.
     *
     * @return array<string,string>
     */
    public static function default_texts(): array
    {
        return [
            'header'    => __('We value your privacy', 'lrob-cookie-consent'),
            'message'   => __('We use cookies to improve your experience. Choose which categories you allow.', 'lrob-cookie-consent'),
            'accept'    => __('Accept all', 'lrob-cookie-consent'),
            'deny'      => __('Deny', 'lrob-cookie-consent'),
            'save'      => __('Save preferences', 'lrob-cookie-consent'),
            'close'     => __('Close', 'lrob-cookie-consent'),
            'always'    => __('Always active', 'lrob-cookie-consent'),
            'customize' => __('Customize', 'lrob-cookie-consent'),
            'continue'  => __('Continue without accepting', 'lrob-cookie-consent'),
        ];
    }

    public static function texts(): array
    {
        $d = self::default_texts();
        $get = static function (string $opt, string $fallback): string {
            $value = trim((string) Options::get($opt));
            return $value !== '' ? $value : $fallback;
        };

        return [
            'header'    => $get('text_header', $d['header']),
            'message'   => $get('text_message', $d['message']),
            'accept'    => $get('text_accept', $d['accept']),
            'deny'      => $get('text_deny', $d['deny']),
            'save'      => $get('text_save', $d['save']),
            'close'     => $d['close'],
            'always'    => $d['always'],
            'customize' => $get('text_customize', $d['customize']),
            'continue'  => $get('text_continue', $d['continue']),
        ];
    }

    /** @return array<string,array{title:string,desc:string}> */
    public static function category_labels(): array
    {
        return Categories::labels();
    }

    public static function render(): string
    {
        $texts = self::texts();
        $labels = self::category_labels();
        $position = (string) Options::get('position');
        $show_accept = (int) Options::get('show_accept') === 1;
        $show_deny = (int) Options::get('show_deny') === 1;
        $show_save = (int) Options::get('show_save') === 1;
        $show_customize = (int) Options::get('show_customize') === 1;
        $deny_style = (string) Options::get('deny_style');
        $deny_link_position = (string) Options::get('deny_link_position');
        $collapsed = (int) Options::get('categories_collapsed') === 1;
        $logo = (string) Options::get('logo');
        $footer_links = is_array(Options::get('footer_links')) ? Options::get('footer_links') : [];
        $watermark = (int) Options::get('watermark') === 1;

        // Map each category to what it blocks (services + inline scripts).
        $compiled = \LRob\CookieConsent\Support\Rules::compiled();
        $sources = [];
        foreach ($compiled['rules'] as $r) {
            $sources[$r['category']][] = $r['service'] !== '' ? $r['service'] : $r['pattern'];
        }
        foreach ($compiled['inline'] as $in) {
            $sources[$in['category']][] = $in['name'] !== '' ? $in['name'] : __('Inline script', 'lrob-cookie-consent');
        }
        foreach ($sources as $cat => $list) {
            $sources[$cat] = array_values(array_unique($list));
        }

        // Only offer optional categories that actually block something — an empty
        // category is meaningless to consent to and just adds clutter.
        $optional = \LRob\CookieConsent\Support\Rules::active_categories();

        $show_sources = (int) Options::get('show_sources') === 1;

        ob_start();
        include LROB_CC_PATH . 'views/banner.php';
        return (string) ob_get_clean();
    }
}
