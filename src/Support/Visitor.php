<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

final class Visitor
{
    /** Whether the consent system (banner + JS) should run for this visitor. */
    public static function consent_active(): bool
    {
        if ((int) Options::get('enabled') !== 1) {
            return false;
        }
        if ((int) Options::get('show_to_logged_in') !== 1 && is_user_logged_in()) {
            return false;
        }
        // A consent banner with nothing to block/manage is misleading — suppress it.
        return self::has_managed_content();
    }

    /** True when at least one block rule or inline script is configured. */
    public static function has_managed_content(): bool
    {
        $compiled = Rules::compiled();
        return $compiled['rules'] !== [] || $compiled['inline'] !== [];
    }
}
