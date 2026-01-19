<?php

namespace Ksfraser\ModulesDAO\Stores\Record;


/**
 * CSV-backed record store (scaffold).
 *
 * Intended for simple portable exports/imports.
 *
 * NOTE: This is scaffolding; implement schema/primary-key semantics per use-case.
 */
class CsvRecordStore extends AbstractFileRecordStore
{
    /** @var string */
    private $idColumn;

    public function __construct(string $filePath, string $idColumn = 'id')
    {
        parent::__construct($filePath);
        $this->idColumn = $idColumn;
    }


    public function find(string $id): ?array
    {
        foreach ($this->findAll() as $row) {
            if (isset($row[$this->idColumn]) && (string)$row[$this->idColumn] === (string)$id) {
                return $row;
            }
        }
        return null;
    }

    public function findAll(array $filters = []): array
    {
        if (!is_file($this->filePath)) {
            return [];
        }

        $fh = fopen($this->filePath, 'rb');
        if ($fh === false) {
            return [];
        }

        $rows = [];
        $headers = null;

        while (($data = fgetcsv($fh)) !== false) {
            if ($headers === null) {
                $headers = $data;
                continue;
            }

            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = $data[$i] ?? null;
            }

            if ($filters) {
                $ok = true;
                foreach ($filters as $k => $v) {
                    if (!array_key_exists($k, $row) || (string)$row[$k] !== (string)$v) {
                        $ok = false;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }

            $rows[] = $row;
        }

        fclose($fh);
        return $rows;
    }

    public function insert(array $record): string
    {
        // Scaffold: implement append + id generation.
        // Returning empty string for now.
        return '';
    }

    public function update(string $id, array $record): void
    {
        // Scaffold: implement read-modify-write.
    }

    public function delete(string $id): void
    {
        // Scaffold: implement read-filter-write.
    }
}
