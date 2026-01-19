<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;
use RuntimeException;

/**
 * FrontAccounting-backed DB table key/value store.
 *
 * Uses FrontAccounting db_* helpers and TB_PREF.
 *
 * Values are stored/returned as raw strings.
 */
class FrontAccountingDbTableStore implements KeyValueStoreInterface
{
    /** @var string */
    private $table;

    /** @var string */
    private $nameCol;

    /** @var string */
    private $valueCol;

    public function __construct(string $table, string $nameCol = 'pref_name', string $valueCol = 'pref_value')
    {
        $this->table = $table;
        $this->nameCol = $nameCol;
        $this->valueCol = $valueCol;
    }

    public function isAvailable(): bool
    {
        $hasQuery = function_exists('db_query') || function_exists('\\db_query');
        $hasFetch = function_exists('db_fetch') || function_exists('\\db_fetch');
        $hasPrefix = defined('TB_PREF') || defined('\\TB_PREF');
        return $hasQuery && $hasFetch && $hasPrefix;
    }

    private function esc(string $value): string
    {
        if (function_exists('db_escape') || function_exists('\\db_escape')) {
            return (string)db_escape($value);
        }
        return addslashes($value);
    }

    private function fullTable(): string
    {
        $prefix = (defined('TB_PREF') || defined('\\TB_PREF')) ? (string)TB_PREF : '';
        return $prefix . $this->table;
    }

    public function has(string $key): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }
        $tbl = $this->fullTable();
        $k = $this->esc($key);
        $sql = "SELECT 1 FROM {$tbl} WHERE {$this->nameCol} = '{$k}'";
        $res = db_query($sql);
        $row = db_fetch($res);
        return (bool)$row;
    }

    public function get(string $key, $default = null)
    {
        if (!$this->isAvailable()) {
            return $default;
        }
        $tbl = $this->fullTable();
        $k = $this->esc($key);
        $sql = "SELECT {$this->valueCol} FROM {$tbl} WHERE {$this->nameCol} = '{$k}'";
        $res = db_query($sql);
        $row = db_fetch($res);
        if (!$row) {
            return $default;
        }
        return isset($row[$this->valueCol]) ? (string)$row[$this->valueCol] : $default;
    }

    public function set(string $key, $value): void
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException('FrontAccounting DB functions not available');
        }
        $tbl = $this->fullTable();
        $k = $this->esc($key);
        $v = $this->esc((string)$value);

        if ($this->has($key)) {
            $sql = "UPDATE {$tbl} SET {$this->valueCol} = '{$v}' WHERE {$this->nameCol} = '{$k}'";
        } else {
            $sql = "INSERT INTO {$tbl} ({$this->nameCol}, {$this->valueCol}) VALUES ('{$k}', '{$v}')";
        }

        db_query($sql);
    }

    public function delete(string $key): void
    {
        if (!$this->isAvailable()) {
            return;
        }
        $tbl = $this->fullTable();
        $k = $this->esc($key);
        $sql = "DELETE FROM {$tbl} WHERE {$this->nameCol} = '{$k}'";
        db_query($sql);
    }

    public function all(?string $prefix = null): array
    {
        if (!$this->isAvailable()) {
            return [];
        }
        $tbl = $this->fullTable();

        if ($prefix === null) {
            $sql = "SELECT {$this->nameCol}, {$this->valueCol} FROM {$tbl}";
        } else {
            $p = $this->esc($prefix . '%');
            $sql = "SELECT {$this->nameCol}, {$this->valueCol} FROM {$tbl} WHERE {$this->nameCol} LIKE '{$p}'";
        }

        $res = db_query($sql);
        $out = [];
        while ($row = db_fetch($res)) {
            $name = isset($row[$this->nameCol]) ? (string)$row[$this->nameCol] : '';
            if ($name === '') {
                continue;
            }
            $out[$name] = isset($row[$this->valueCol]) ? (string)$row[$this->valueCol] : '';
        }
        return $out;
    }
}
