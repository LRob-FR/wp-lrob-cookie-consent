<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Scanning;

/**
 * A source of detected third-party resources. The built-in LocalScanner crawls
 * public pages server-side; a future LRob-hosted headless provider can register
 * via the `lrob_cc_scan_providers` filter to also catch JS-injected trackers.
 *
 * @phpstan-type ScanResource array{pattern:string,host:string,type:string,category:string,service:string,known:bool,sample:string}
 */
interface ScanProvider
{
    public function id(): string;

    public function label(): string;

    /**
     * @param list<string> $urls
     * @return array{resources: list<array<string,mixed>>, cookies: list<string>, error: string}
     */
    public function scan(array $urls): array;
}
