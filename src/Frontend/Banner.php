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

    public static function texts(): array
    {
        $get = static function (string $opt, string $fallback): string {
            $value = trim((string) Options::get($opt));
            return $value !== '' ? $value : $fallback;
        };

        return [
            'header'  => $get('text_header', __('We value your privacy', 'lrob-cookie-consent')),
            'message' => $get('text_message', __('We use cookies to improve your experience. Choose which categories you allow.', 'lrob-cookie-consent')),
            'accept'  => $get('text_accept', __('Accept all', 'lrob-cookie-consent')),
            'deny'    => $get('text_deny', __('Deny', 'lrob-cookie-consent')),
            'save'    => $get('text_save', __('Save preferences', 'lrob-cookie-consent')),
            'close'   => __('Close', 'lrob-cookie-consent'),
            'always'  => __('Always active', 'lrob-cookie-consent'),
            'customize' => __('Customize', 'lrob-cookie-consent'),
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
        $optional = Categories::optional();
        $position = (string) Options::get('position');
        $show_deny = (int) Options::get('show_deny') === 1;
        $show_save = (int) Options::get('show_save') === 1;
        $collapsed = (int) Options::get('categories_collapsed') === 1;
        $logo = (string) Options::get('logo');

        ob_start();
        include LROB_CC_PATH . 'views/banner.php';
        return (string) ob_get_clean();
    }
}
