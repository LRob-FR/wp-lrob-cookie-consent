<?php

declare(strict_types=1);

namespace LRob\CookieConsent;

final class Deactivator
{
    public static function deactivate(): void
    {
        // Data (options, log table, capability) is preserved on deactivate;
        // removal happens only in uninstall.php.
        wp_clear_scheduled_hook(\LRob\CookieConsent\Consent\LogRepository::PURGE_HOOK);
    }
}
