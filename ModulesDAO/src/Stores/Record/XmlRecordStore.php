<?php

namespace Ksfraser\ModulesDAO\Stores\Record;


/**
 * XML-backed record store (scaffold).
 *
 * XML has many possible schemas; this is intentionally a stub.
 */
class XmlRecordStore extends AbstractFileRecordStore
{
    public function find(string $id): ?array
    {
        // Scaffold
        return null;
    }

    public function findAll(array $filters = []): array
    {
        // Scaffold
        return [];
    }

    public function insert(array $record): string
    {
        // Scaffold
        return '';
    }

    public function update(string $id, array $record): void
    {
        // Scaffold
    }

    public function delete(string $id): void
    {
        // Scaffold
    }
}
