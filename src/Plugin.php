<?php

declare(strict_types=1);

namespace LRob\CookieConsent;

final class Plugin
{
    private static ?Plugin $instance = null;

    public readonly Container $container;

    private bool $booted = false;

    private function __construct()
    {
        $this->container = new Container();
    }

    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        load_plugin_textdomain(
            'lrob-cookie-consent',
            false,
            dirname(LROB_CC_BASENAME) . '/languages'
        );

        (new \LRob\CookieConsent\AutoUpdate\Updater())->register();

        \LRob\CookieConsent\Consent\Schema::maybe_upgrade();

        $log = new \LRob\CookieConsent\Consent\LogRepository();
        $log->register();
        (new \LRob\CookieConsent\Consent\RestController($log))->register();

        if (is_admin()) {
            (new \LRob\CookieConsent\Admin\SettingsPage($log))->register();
            return;
        }

        (new \LRob\CookieConsent\Frontend\Assets())->register();
        (new \LRob\CookieConsent\Frontend\Banner())->register();
        (new \LRob\CookieConsent\Blocking\Blocker())->register();
    }
}
