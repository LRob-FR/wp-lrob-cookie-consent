<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Consent;

use LRob\CookieConsent\Support\Bots;
use LRob\CookieConsent\Support\Categories;
use LRob\CookieConsent\Support\Ip;
use LRob\CookieConsent\Support\Options;
use WP_REST_Request;
use WP_REST_Response;

final class RestController
{
    public function __construct(private LogRepository $log)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route('lrob-cc/v1', '/log', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        // No-op unless logging is enabled; never log bots.
        if ((int) Options::get('log_consent') !== 1 || Bots::is_bot()) {
            return new WP_REST_Response(['ok' => true], 200);
        }

        $categories = array_values(array_intersect(
            Categories::all(),
            array_map('sanitize_key', (array) $request->get_param('categories'))
        ));
        $version = sanitize_text_field((string) $request->get_param('version'));

        $ip = Ip::client_ip();
        $stored_ip = match ((string) Options::get('ip_storage')) {
            'full'  => $ip,
            'none'  => '',
            default => Ip::anonymise($ip),
        };

        $ua = '';
        if ((int) Options::get('store_user_agent') === 1 && isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255);
        }

        $this->log->insert([
            'ip_anon'        => (string) $stored_ip,
            'categories'     => implode(',', $categories),
            'config_version' => substr($version, 0, 32),
            'user_agent'     => $ua,
        ]);

        return new WP_REST_Response(['ok' => true], 200);
    }
}
