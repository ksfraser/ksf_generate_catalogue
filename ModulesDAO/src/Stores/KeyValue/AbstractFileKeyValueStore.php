<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;
use RuntimeException;

/**
 * Common base for file-backed key/value stores.
 */
abstract class AbstractFileKeyValueStore implements KeyValueStoreInterface
{
    /** @var string */
    protected $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function isAvailable(): bool
    {
        return is_string($this->filePath) && $this->filePath !== '';
    }

    final public function has(string $key): bool
    {
        $data = $this->load();
        return array_key_exists($key, $data);
    }

    final public function get(string $key, $default = null)
    {
        $data = $this->load();
        return array_key_exists($key, $data) ? $data[$key] : $default;
    }

    final public function set(string $key, $value): void
    {
        $data = $this->load();
        $data[$key] = (string)$value;
        $this->save($data);
    }

    final public function delete(string $key): void
    {
        $data = $this->load();
        if (array_key_exists($key, $data)) {
            unset($data[$key]);
            $this->save($data);
        }
    }

    final public function all(?string $prefix = null): array
    {
        $data = $this->load();
        if ($prefix === null) {
            return $data;
        }

        $out = [];
        foreach ($data as $k => $v) {
            if (strncmp((string)$k, $prefix, strlen($prefix)) === 0) {
                $out[(string)$k] = $v;
            }
        }
        return $out;
    }

    /**
     * @return array<string,string>
     */
    abstract protected function decode(string $contents): array;

    /**
     * @param array<string,string> $data
     */
    abstract protected function encode(array $data): string;

    /**
     * @return array<string,string>
     */
    protected function load(): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $contents = @file_get_contents($this->filePath);
        if ($contents === false) {
            return [];
        }

        return $this->decode($contents);
    }

    /**
     * @param array<string,string> $data
     */
    protected function save(array $data): void
    {
        $dir = dirname($this->filePath);
        if ($dir && !is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $contents = $this->encode($data);

        $tmp = $this->filePath . '.tmp.' . bin2hex(random_bytes(6));
        $bytes = @file_put_contents($tmp, $contents, LOCK_EX);
        if ($bytes === false) {
            throw new RuntimeException('Failed to write temp file');
        }

        if (is_file($this->filePath)) {
            @unlink($this->filePath);
        }

        if (!@rename($tmp, $this->filePath)) {
            @unlink($tmp);
            throw new RuntimeException('Failed to replace file: ' . $this->filePath);
        }
    }
}
