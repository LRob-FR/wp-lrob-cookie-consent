<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

final class Presets
{
    /** @return list<array<string,mixed>> */
    public static function text(): array
    {
        $presets = [];
        foreach (glob(LROB_CC_PATH . 'presets/text-*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && isset($data['id'])) {
                $presets[] = $data;
            }
        }
        return apply_filters('lrob_cc_text_presets', $presets);
    }

    /** @return array<string,list<array<string,mixed>>> */
    public static function styles(): array
    {
        $data = json_decode((string) file_get_contents(LROB_CC_PATH . 'presets/styles.json'), true);
        if (!is_array($data)) {
            $data = ['colors' => [], 'shape' => [], 'size' => []];
        }
        return apply_filters('lrob_cc_style_presets', $data);
    }
}
