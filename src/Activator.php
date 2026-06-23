<?php

declare(strict_types=1);

namespace LRob\CookieConsent;

use LRob\CookieConsent\Consent\Schema;
use LRob\CookieConsent\Support\Options;

final class Activator
{
    public static function activate(): void
    {
        self::grant_capability();
        self::seed_options();
        Schema::create();
    }

    private static function grant_capability(): void
    {
        $role = get_role('administrator');
        if ($role !== null && !$role->has_cap(LROB_CC_CAPABILITY)) {
            $role->add_cap(LROB_CC_CAPABILITY);
        }
    }

    /** Merge defaults under any existing options — never clobber a live config. */
    private static function seed_options(): void
    {
        $existing = get_option(Options::OPTION_KEY, []);
        if (!is_array($existing)) {
            $existing = [];
        }
        update_option(Options::OPTION_KEY, array_merge(Options::defaults(), $existing));
    }
}
