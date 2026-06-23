<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

final class Bots
{
    private const PATTERNS = [
        'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'lighthouse',
        'pingdom', 'gtmetrix', 'pagespeed', 'headlesschrome', 'phantomjs',
        'facebookexternalhit', 'feedfetcher', 'preview', 'monitor',
    ];

    public static function is_bot(): bool
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT'])
            ? strtolower((string) $_SERVER['HTTP_USER_AGENT'])
            : '';
        if ($ua === '') {
            return false;
        }
        foreach (self::PATTERNS as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }
        return false;
    }
}
