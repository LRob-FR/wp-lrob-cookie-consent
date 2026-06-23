<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

final class Rules
{
    private const TRANSIENT = 'lrob_cc_block_rules';
    private const TTL = 1800; // 30 min

    /** @var array{rules: list<array{pattern:string,category:string,service:string}>, inline: list<array{code:string,category:string}>, version: string}|null */
    private static ?array $cache = null;

    /**
     * Compiled, validated block config. Cached per-request and in a transient.
     *
     * @return array{rules: list<array{pattern:string,category:string,service:string}>, inline: list<array{code:string,category:string}>, version: string}
     */
    public static function compiled(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $cached = get_transient(self::TRANSIENT);
        if (is_array($cached) && isset($cached['rules'], $cached['inline'], $cached['version'])) {
            self::$cache = $cached;
            return $cached;
        }

        $rules = self::parse_rules((string) Options::get('block_rules'));
        $rules = apply_filters('lrob_cc_block_rules', $rules);

        $inline = self::parse_inline(Options::get('inline_scripts'));
        $version = self::compute_version($rules, $inline);

        $compiled = ['rules' => $rules, 'inline' => $inline, 'version' => $version];
        set_transient(self::TRANSIENT, $compiled, self::TTL);
        self::$cache = $compiled;
        return $compiled;
    }

    public static function flush(): void
    {
        self::$cache = null;
        delete_transient(self::TRANSIENT);
    }

    public static function version(): string
    {
        return self::compiled()['version'];
    }

    /**
     * @return list<array{pattern:string,category:string,service:string}>
     */
    private static function parse_rules(string $text): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            $pattern = $parts[0] ?? '';
            $category = $parts[1] ?? '';
            $service = $parts[2] ?? '';
            if ($pattern === '' || !Categories::is_valid($category) || $category === Categories::FUNCTIONAL) {
                continue;
            }
            $out[] = ['pattern' => $pattern, 'category' => $category, 'service' => $service];
        }
        return $out;
    }

    /**
     * @param mixed $raw
     * @return list<array{code:string,category:string}>
     */
    private static function parse_inline(mixed $raw): array
    {
        $out = [];
        if (!is_array($raw)) {
            return $out;
        }
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $code = (string) ($item['code'] ?? '');
            $category = (string) ($item['category'] ?? '');
            if ($code === '' || !Categories::is_valid($category) || $category === Categories::FUNCTIONAL) {
                continue;
            }
            $out[] = ['code' => $code, 'category' => $category];
        }
        return $out;
    }

    /**
     * Default: hash the set of categories actually in play (re-prompt only when
     * that set changes). Advanced: hash the full rule text so any edit re-prompts.
     *
     * @param list<array{pattern:string,category:string,service:string}> $rules
     * @param list<array{code:string,category:string}> $inline
     */
    private static function compute_version(array $rules, array $inline): string
    {
        if ((int) Options::get('reprompt_on_rule_change') === 1) {
            $parts = [$rules, $inline];
        } else {
            $cats = [];
            foreach ($rules as $r) {
                $cats[$r['category']] = true;
            }
            foreach ($inline as $i) {
                $cats[$i['category']] = true;
            }
            $keys = array_keys($cats);
            sort($keys);
            $parts = $keys;
        }
        return substr(md5((string) wp_json_encode($parts)), 0, 8);
    }
}
