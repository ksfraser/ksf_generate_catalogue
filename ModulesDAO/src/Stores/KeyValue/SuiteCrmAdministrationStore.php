<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;
use RuntimeException;

/**
 * SuiteCRM-backed key/value store using Administration settings.
 */
class SuiteCrmAdministrationStore implements KeyValueStoreInterface
{
    /** @var string */
    private $category;

    public function __construct(string $category = 'ksf')
    {
        $this->category = $category;
    }

    public function isAvailable(): bool
    {
        return class_exists('Administration') && (method_exists('Administration', 'retrieveSettings') || method_exists('Administration', 'saveSetting'));
    }

    private function loadSettings(): array
    {
        if (!class_exists('Administration')) {
            return [];
        }

        $admin = new \Administration();
        if (method_exists($admin, 'retrieveSettings')) {
            $admin->retrieveSettings($this->category, true);
        }

        return isset($admin->settings) && is_array($admin->settings) ? $admin->settings : [];
    }

    private function fullKey(string $key): string
    {
        return $this->category . '_' . $key;
    }

    public function has(string $key): bool
    {
        $settings = $this->loadSettings();
        return array_key_exists($this->fullKey($key), $settings);
    }

    public function get(string $key, $default = null)
    {
        $settings = $this->loadSettings();
        $full = $this->fullKey($key);
        return array_key_exists($full, $settings) ? $settings[$full] : $default;
    }

    public function set(string $key, $value): void
    {
        if (!class_exists('Administration')) {
            throw new RuntimeException('SuiteCRM Administration is not available');
        }
        $admin = new \Administration();
        if (!method_exists($admin, 'saveSetting')) {
            throw new RuntimeException('SuiteCRM Administration saveSetting() not available');
        }
        $admin->saveSetting($this->category, $key, $value);
    }

    public function delete(string $key): void
    {
        if (!class_exists('Administration')) {
            return;
        }

        $admin = new \Administration();

        $candidates = [
            ['deleteSetting', [$this->category, $key]],
            ['removeSetting', [$this->category, $key]],
            ['remove_setting', [$this->category, $key]],
        ];

        foreach ($candidates as [$method, $args]) {
            if (!method_exists($admin, $method)) {
                continue;
            }
            try {
                $admin->$method(...$args);
                return;
            } catch (\Throwable $e) {
                // fall through
            }
        }

        if (method_exists($admin, 'saveSetting')) {
            $admin->saveSetting($this->category, $key, '');
        }
    }

    public function all(?string $prefix = null): array
    {
        $settings = $this->loadSettings();
        $out = [];

        foreach ($settings as $k => $v) {
            if (strncmp($k, $this->category . '_', strlen($this->category) + 1) !== 0) {
                continue;
            }
            $short = substr($k, strlen($this->category) + 1);
            if ($prefix !== null && strncmp($short, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $out[$short] = $v;
        }

        return $out;
    }
}
