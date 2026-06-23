<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

final class Categories
{
    public const FUNCTIONAL = 'functional';

    /** Optional categories, in banner display order. functional is always-on. */
    public const OPTIONAL = ['preferences', 'statistics', 'marketing'];

    /** @return list<string> functional first, then the optional ones. */
    public static function all(): array
    {
        return array_merge([self::FUNCTIONAL], self::OPTIONAL);
    }

    public static function is_valid(string $category): bool
    {
        return in_array($category, self::all(), true);
    }
}
