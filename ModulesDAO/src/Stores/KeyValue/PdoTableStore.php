<?php

namespace Ksfraser\ModulesDAO\Stores\KeyValue;

use Ksfraser\ModulesDAO\Contracts\KeyValueStoreInterface;
use PDO;

/**
 * Generic DB-table key/value store using PDO.
 *
 * Expects a table with columns like: pref_name (PK/unique), pref_value.
 *
 * Values are stored and returned as raw strings.
 * (Higher layers can apply encoding/decoding as needed.)
 */
class PdoTableStore implements KeyValueStoreInterface
{
    /** @var PDO */
    private $pdo;

    /** @var string */
    private $table;

    /** @var string */
    private $nameCol;

    /** @var string */
    private $valueCol;

    public function __construct(PDO $pdo, string $table, string $nameCol = 'pref_name', string $valueCol = 'pref_value')
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->nameCol = $nameCol;
        $this->valueCol = $valueCol;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function has(string $key): bool
    {
        $sql = "SELECT 1 FROM {$this->table} WHERE {$this->nameCol} = :k";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':k' => $key]);
        return (bool)$stmt->fetchColumn();
    }

    public function get(string $key, $default = null)
    {
        $sql = "SELECT {$this->valueCol} FROM {$this->table} WHERE {$this->nameCol} = :k";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':k' => $key]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            return $default;
        }
        return (string)$raw;
    }

    public function set(string $key, $value): void
    {
        $raw = (string)$value;

        // Portable upsert: update then insert.
        $sqlUpdate = "UPDATE {$this->table} SET {$this->valueCol} = :v WHERE {$this->nameCol} = :k";
        $stmt = $this->pdo->prepare($sqlUpdate);
        $stmt->execute([':k' => $key, ':v' => $raw]);
        $updated = $stmt->rowCount() > 0;

        if (!$updated) {
            $sqlInsert = "INSERT INTO {$this->table} ({$this->nameCol}, {$this->valueCol}) VALUES (:k, :v)";
            $stmt = $this->pdo->prepare($sqlInsert);
            $stmt->execute([':k' => $key, ':v' => $raw]);
        }
    }

    public function delete(string $key): void
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->nameCol} = :k";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':k' => $key]);
    }

    public function all(?string $prefix = null): array
    {
        $out = [];

        if ($prefix === null) {
            $sql = "SELECT {$this->nameCol}, {$this->valueCol} FROM {$this->table}";
            $stmt = $this->pdo->query($sql);
        } else {
            $sql = "SELECT {$this->nameCol}, {$this->valueCol} FROM {$this->table} WHERE {$this->nameCol} LIKE :p";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':p' => $prefix . '%']);
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = isset($row[$this->nameCol]) ? (string)$row[$this->nameCol] : '';
            if ($name === '') {
                continue;
            }
            $out[$name] = isset($row[$this->valueCol]) ? (string)$row[$this->valueCol] : '';
        }

        return $out;
    }
}
