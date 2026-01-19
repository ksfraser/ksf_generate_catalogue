<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;
use RuntimeException;

/**
 * FrontAccounting system preferences store (best-effort).
 */
class FrontAccountingSysPrefsStore implements KeyValueStoreInterface
{
    public function isAvailable(): bool
    {
        return function_exists('get_company_prefs') || function_exists('get_company_pref');
    }

    public function has(string $key): bool
    {
        $prefs = $this->all();
        return array_key_exists($key, $prefs);
    }

    public function get(string $key, $default = null)
    {
        if (function_exists('get_company_pref')) {
            $value = get_company_pref($key);
            return $value === null ? $default : $value;
        }

        $prefs = $this->all();
        return array_key_exists($key, $prefs) ? $prefs[$key] : $default;
    }

    public function set(string $key, $value): void
    {
        if (function_exists('set_company_pref')) {
            set_company_pref($key, $value);
            return;
        }

        if (function_exists('update_company_prefs')) {
            update_company_prefs([$key => $value]);
            return;
        }

        throw new RuntimeException('FrontAccounting system preference setter not available');
    }

    public function delete(string $key): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        try {
            $this->set($key, '');
        } catch (RuntimeException $e) {
            // no-op
        }
    }

    public function all(?string $prefix = null): array
    {
        if (function_exists('get_company_prefs')) {
            $prefs = get_company_prefs();
            if (!is_array($prefs)) {
                return [];
            }

            if ($prefix === null) {
                return $prefs;
            }

            $out = [];
            foreach ($prefs as $k => $v) {
                if (strncmp($k, $prefix, strlen($prefix)) === 0) {
                    $out[$k] = $v;
                }
            }
            return $out;
        }

        return [];
    }
}
