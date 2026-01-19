<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;

/**
 * Generic DB-table key/value store using injected callables.
 *
 * Useful for platforms with their own DB layer.
 *
 * Callables:
 * - query(string $sql): mixed
 * - fetch(mixed $result): array|false
 * - escape(string $value): string
 * - tablePrefix(): string
 *
 * Values are stored/returned as raw strings.
 */
class CallableDbTableStore implements KeyValueStoreInterface
{
    /** @var callable */
    private $query;

    /** @var callable */
    private $fetch;

    /** @var callable */
    private $escape;

    /** @var callable */
    private $tablePrefix;

    /** @var string */
    private $table;

    /** @var string */
    private $nameCol;

    /** @var string */
    private $valueCol;

    /** @var bool */
    private $available;

    /**
     * @param callable $query
     * @param callable $fetch
     * @param callable $escape
     * @param callable $tablePrefix
     */
    public function __construct(callable $query, callable $fetch, callable $escape, callable $tablePrefix, string $table, string $nameCol = 'pref_name', string $valueCol = 'pref_value', bool $available = true)
    {
        $this->query = $query;
        $this->fetch = $fetch;
        $this->escape = $escape;
        $this->tablePrefix = $tablePrefix;
        $this->table = $table;
        $this->nameCol = $nameCol;
        $this->valueCol = $valueCol;
        $this->available = $available;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    private function tbl(): string
    {
        $prefix = (string)call_user_func($this->tablePrefix);
        return $prefix . $this->table;
    }

    private function esc(string $value): string
    {
        return (string)call_user_func($this->escape, $value);
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        $tbl = $this->tbl();
        $k = $this->esc($key);
        $sql = "SELECT 1 FROM {$tbl} WHERE {$this->nameCol} = '{$k}'";
        $res = call_user_func($this->query, $sql);
        $row = call_user_func($this->fetch, $res);
        return (bool)$row;
    }

    public function get(string $key, $default = null)
    {
        if (!$this->isAvailable()) {
            return $default;
        }
        $tbl = $this->tbl();
        $k = $this->esc($key);
        $sql = "SELECT {$this->valueCol} FROM {$tbl} WHERE {$this->nameCol} = '{$k}'";
        $res = call_user_func($this->query, $sql);
        $row = call_user_func($this->fetch, $res);
        if (!$row) {
            return $default;
        }
        return isset($row[$this->valueCol]) ? (string)$row[$this->valueCol] : $default;
    }

    public function set(string $key, $value): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        $tbl = $this->tbl();
        $k = $this->esc($key);
        $v = $this->esc((string)$value);

        if ($this->has($key)) {
            $sql = "UPDATE {$tbl} SET {$this->valueCol} = '{$v}' WHERE {$this->nameCol} = '{$k}'";
        } else {
            $sql = "INSERT INTO {$tbl} ({$this->nameCol}, {$this->valueCol}) VALUES ('{$k}', '{$v}')";
        }

        call_user_func($this->query, $sql);
    }

    public function delete(string $key): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        $tbl = $this->tbl();
        $k = $this->esc($key);
        $sql = "DELETE FROM {$tbl} WHERE {$this->nameCol} = '{$k}'";
        call_user_func($this->query, $sql);
    }

    public function all(?string $prefix = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        $tbl = $this->tbl();

        if ($prefix === null) {
            $sql = "SELECT {$this->nameCol}, {$this->valueCol} FROM {$tbl}";
        } else {
            $p = $this->esc($prefix . '%');
            $sql = "SELECT {$this->nameCol}, {$this->valueCol} FROM {$tbl} WHERE {$this->nameCol} LIKE '{$p}'";
        }

        $res = call_user_func($this->query, $sql);
        $out = [];
        while ($row = call_user_func($this->fetch, $res)) {
            $name = isset($row[$this->nameCol]) ? (string)$row[$this->nameCol] : '';
            if ($name === '') {
                continue;
            }
            $out[$name] = isset($row[$this->valueCol]) ? (string)$row[$this->valueCol] : '';
        }
        return $out;
    }
}
