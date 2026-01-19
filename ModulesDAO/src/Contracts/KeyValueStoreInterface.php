<?php

namespace Ksfraser\ModulesDAO\Contracts;

/**
 * Simple key/value store abstraction.
 *
 * Intended for preferences/settings/config values.
 */
interface KeyValueStoreInterface extends StoreAvailabilityInterface
{
    public function has(string $key): bool;

    /**
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void;

    public function delete(string $key): void;

    /**
     * @return array<string,mixed>
     */
    public function all(?string $prefix = null): array;
}
