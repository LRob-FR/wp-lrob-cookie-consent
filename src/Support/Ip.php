<?php

declare(strict_types=1);

namespace LRob\CookieConsent\Support;

final class Ip
{
    public static function client_ip(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /** IPv4 → /24, IPv6 → /48. Returns '' on invalid input. */
    public static function anonymise(string $ip): string
    {
        if ($ip === '') {
            return '';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) {
                return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.0';
            }
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $bin = inet_pton($ip);
            if ($bin !== false) {
                $truncated = substr($bin, 0, 6) . str_repeat("\x00", 10);
                $packed = inet_ntop($truncated);
                if (is_string($packed)) {
                    return $packed;
                }
            }
        }
        return '';
    }
}
