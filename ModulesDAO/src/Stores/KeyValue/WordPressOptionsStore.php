<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;

/**
 * WordPress-backed key/value store using options.
 */
class WordPressOptionsStore implements KeyValueStoreInterface
{
    /** @var string */
    private $optionPrefix;

    /** @var bool */
    private $autoload;

    public function __construct(string $optionPrefix = '', bool $autoload = false)
    {
        $this->optionPrefix = $optionPrefix;
        $this->autoload = $autoload;
    }

    public function isAvailable(): bool
    {
        return function_exists('get_option') && function_exists('update_option') && function_exists('delete_option');
    }

    private function opt(string $key): string
    {
        return $this->optionPrefix . $key;
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        $sentinel = '__ksf_modulesdao_missing__';
        $value = get_option($this->opt($key), $sentinel);
        return $value !== $sentinel;
    }

    public function get(string $key, $default = null)
    {
        if (!$this->isAvailable()) {
            return $default;
        }
        return get_option($this->opt($key), $default);
    }

    public function set(string $key, $value): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        update_option($this->opt($key), $value, $this->autoload);
    }

    public function delete(string $key): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        delete_option($this->opt($key));
    }

    public function all(?string $prefix = null): array
    {
        // WordPress does not provide an efficient generic enumeration API for options.
        // Intentionally return empty to avoid accidental full-table scans.
        return [];
    }
}
