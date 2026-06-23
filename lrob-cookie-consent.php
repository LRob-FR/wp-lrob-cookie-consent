<?php
/**
 * Plugin Name: LRob - Cookie Consent
 * Plugin URI: https://www.lrob.fr/wordpress/plugins/lrob-cookie-consent/
 * Description: Lean, opinionated GDPR / ePrivacy cookie consent — opt-in banner, script & iframe blocking engine, proof of consent. No scanner, no bloat.
 * Version: 0.0.1
 * Author: LRob
 * Author URI: https://www.lrob.fr
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: lrob-cookie-consent
 * Domain Path: /languages
 * Requires PHP: 8.2
 * Requires at least: 6.8
 * Update URI: https://github.com/LRob-FR/wp-lrob-cookie-consent
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LROB_CC_VERSION', '0.0.1');
define('LROB_CC_FILE', __FILE__);
define('LROB_CC_PATH', plugin_dir_path(__FILE__));
define('LROB_CC_URL', plugin_dir_url(__FILE__));
define('LROB_CC_BASENAME', plugin_basename(__FILE__));
define('LROB_CC_PLUGIN_URL', 'https://www.lrob.fr/wordpress/plugins/lrob-cookie-consent/');
define('LROB_CC_GITHUB_URL', 'https://github.com/LRob-FR/wp-lrob-cookie-consent');
define('LROB_CC_GITHUB_ISSUES_URL', LROB_CC_GITHUB_URL . '/issues');
define('LROB_CC_CAPABILITY', 'manage_lrob_cc');

// PSR-4 autoloader: LRob\CookieConsent\Foo\Bar -> src/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    $prefix = 'LRob\\CookieConsent\\';
    $base_dir = LROB_CC_PATH . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative = substr($class, $len);
    $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, [\LRob\CookieConsent\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [\LRob\CookieConsent\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    \LRob\CookieConsent\Plugin::instance()->boot();
});
