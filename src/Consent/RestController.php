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

        // Granular per-purpose decision (one explicit allow/deny per category —
        // never a single blanket flag). functional is implicit, never a choice.
        $raw_choices = (array) $request->get_param('choices');
        $choices = [];
        foreach (Categories::optional() as $cat) {
            $choices[$cat] = !empty($raw_choices[$cat]) ? 1 : 0;
        }

        $method = sanitize_key((string) $request->get_param('method'));
        if (!in_array($method, ['accept_all', 'deny_all', 'save', 'service'], true)) {
            $method = 'save';
        }
        $event_type = sanitize_key((string) $request->get_param('event'));
        if (!in_array($event_type, ['consent', 'update', 'withdraw'], true)) {
            $event_type = 'consent';
        }

        $consent_id = substr((string) preg_replace('/[^a-z0-9]/', '', strtolower((string) $request->get_param('consent_id'))), 0, 40);
        $banner_version = substr((string) preg_replace('/[^a-f0-9]/', '', strtolower((string) $request->get_param('banner_version'))), 0, 40);
        $version = substr(sanitize_text_field((string) $request->get_param('version')), 0, 32);

        $ip = Ip::client_ip();
        $stored_ip = ((string) Options::get('ip_storage')) === 'full' ? $ip : Ip::hash($ip);

        $ua = '';
        if ((int) Options::get('store_user_agent') === 1 && isset($_SERVER['HTTP_USER_AGENT'])) {
            $ua = substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255);
        }

        $user_id = (int) Options::get('store_wp_user') === 1 ? get_current_user_id() : 0;

        $this->log->insert([
            'consent_id'     => $consent_id,
            'user_id'        => $user_id,
            'event_type'     => $event_type,
            'method'         => $method,
            'choices'        => (string) wp_json_encode($choices),
            'payload'        => substr((string) $request->get_body(), 0, 2000),
            'banner_version' => $banner_version,
            'config_version' => $version,
            'ip'             => (string) $stored_ip,
            'user_agent'     => $ua,
        ]);

        return new WP_REST_Response(['ok' => true], 200);
    }
}
