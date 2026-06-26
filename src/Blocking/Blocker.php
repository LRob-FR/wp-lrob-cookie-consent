<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Blocking;

use LRob\CookieConsent\Support\Bots;
use LRob\CookieConsent\Support\Categories;
use LRob\CookieConsent\Support\Options;
use LRob\CookieConsent\Support\Rules;

/**
 * Neutralizes third-party <script>/<iframe> tags before consent. Scripts become
 * type="text/plain" with src→data-src; iframes get src→data-src + a marker class
 * and category. consent.js re-activates them on consent and builds the iframe
 * placeholders client-side.
 */
final class Blocker
{
    /** @var list<array{pattern:string,category:string,service:string}> */
    private array $rules = [];

    public function register(): void
    {
        if ((int) Options::get('enabled') !== 1) {
            return;
        }

        if ((string) Options::get('block_method') === 'enqueued') {
            add_filter('script_loader_tag', [$this, 'filter_loader_tag'], 10, 3);
            return;
        }

        add_action('template_redirect', [$this, 'maybe_start_buffer'], 0);
    }

    public function maybe_start_buffer(): void
    {
        if (!$this->should_buffer()) {
            return;
        }
        $this->rules = Rules::compiled()['rules'];
        if ($this->rules === []) {
            return;
        }
        ob_start([$this, 'filter_html']);
    }

    private function should_buffer(): bool
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }
        if ((int) Options::get('show_to_logged_in') !== 1 && is_user_logged_in()) {
            return false;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return false;
        }
        if (is_feed() || is_robots() || is_trackback()) {
            return false;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }
        if (Bots::is_bot()) {
            return false;
        }
        return true;
    }

    public function filter_html(string $html): string
    {
        if (strlen($html) < 256 || stripos($html, '</html>') === false) {
            return $html;
        }
        if (stripos($html, '<script') !== false) {
            $html = (string) preg_replace_callback(
                '/<script\b([^>]*)>(.*?)<\/script>/is',
                [$this, 'transform_script'],
                $html
            );
        }
        if (stripos($html, '<iframe') !== false) {
            $html = (string) preg_replace_callback(
                '/<iframe\b([^>]*)>/i',
                [$this, 'transform_iframe'],
                $html
            );
        }
        return $html;
    }

    /** @param array{0:string,1:string,2:string} $m */
    private function transform_script(array $m): string
    {
        $attrs = $m[1];
        $body = $m[2];

        $src = $this->attr_value($attrs, 'src');
        $haystack = $src !== '' ? $src : $body;

        $rule = $this->match($haystack);
        if ($rule === null) {
            return $m[0];
        }

        $is_module = (bool) preg_match('/(?<![-\w])type\s*=\s*["\']?module["\']?/i', $attrs);

        // Drop src + type; we re-prefix our own.
        $attrs = (string) preg_replace('/(?<![-\w])src(\s*=)/i', 'data-src$1', $attrs, 1);
        $attrs = (string) preg_replace('/(?<![-\w])type\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $attrs);

        $new = ' type="text/plain" data-category="' . esc_attr($rule['category']) . '"';
        if ($rule['service'] !== '') {
            $new .= ' data-service="' . esc_attr($rule['service']) . '"';
        }
        if ($is_module) {
            $new .= ' data-script-type="module"';
        }

        return '<script' . $new . ' ' . trim($attrs) . '>' . $body . '</script>';
    }

    /** @param array{0:string,1:string} $m */
    private function transform_iframe(array $m): string
    {
        $attrs = $m[1];
        $src = $this->attr_value($attrs, 'src');
        if ($src === '') {
            return $m[0];
        }

        $rule = $this->match($src);
        if ($rule === null) {
            return $m[0];
        }

        $attrs = (string) preg_replace('/(?<![-\w])src(\s*=)/i', 'data-src$1', $attrs, 1);
        $attrs = $this->add_class($attrs, 'lrob-cc-blocked');
        $extra = ' data-category="' . esc_attr($rule['category']) . '"';
        if ($rule['service'] !== '') {
            $extra .= ' data-service="' . esc_attr($rule['service']) . '"';
        }

        return '<iframe' . $extra . ' ' . trim($attrs) . '>';
    }

    public function filter_loader_tag(string $tag, string $handle, string $src): string
    {
        if ($this->rules === []) {
            $this->rules = self::enforceable_rules();
        }
        $rule = $this->match($src);
        if ($rule === null) {
            return $tag;
        }
        return $this->transform_script([$tag, $this->between_script_attrs($tag), '']);
    }

    private function between_script_attrs(string $tag): string
    {
        return preg_match('/<script\b([^>]*)>/i', $tag, $m) ? $m[1] : '';
    }

    /**
     * Rules actually enforced: everything except functional, which is only
     * referenced for transparency (necessary cookies are never blocked).
     *
     * @return list<array{pattern:string,category:string,service:string}>
     */
    private static function enforceable_rules(): array
    {
        return array_values(array_filter(
            Rules::compiled()['rules'],
            static fn (array $r): bool => $r['category'] !== Categories::FUNCTIONAL
        ));
    }

    /** @return array{pattern:string,category:string,service:string}|null */
    private function match(string $haystack): ?array
    {
        if ($haystack === '') {
            return null;
        }
        foreach ($this->rules as $rule) {
            if (str_contains($haystack, $rule['pattern'])) {
                return $rule;
            }
        }
        return null;
    }

    private function attr_value(string $attrs, string $name): string
    {
        $pattern = '/(?<![-\w])' . preg_quote($name, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i';
        if (preg_match($pattern, $attrs, $m)) {
            return $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : ($m[4] ?? ''));
        }
        return '';
    }

    private function add_class(string $attrs, string $class): string
    {
        if (preg_match('/(?<![-\w])class\s*=\s*"([^"]*)"/i', $attrs)) {
            return (string) preg_replace('/((?<![-\w])class\s*=\s*")([^"]*)(")/i', '$1$2 ' . $class . '$3', $attrs, 1);
        }
        if (preg_match("/(?<![-\w])class\s*=\s*'([^']*)'/i", $attrs)) {
            return (string) preg_replace("/((?<![-\w])class\s*=\s*')([^']*)(')/i", '$1$2 ' . $class . '$3', $attrs, 1);
        }
        return ' class="' . $class . '"' . $attrs;
    }
}
