<?php

declare(strict_types=1);

namespace LRob\CookieConsent;

use RuntimeException;

final class Container
{
    /** @var array<string, object> */
    private array $services = [];

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function get(string $id): object
    {
        if (!isset($this->services[$id])) {
            throw new RuntimeException("Service not found: {$id}");
        }
        return $this->services[$id];
    }
}
