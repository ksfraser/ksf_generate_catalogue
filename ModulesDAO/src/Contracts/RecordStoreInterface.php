<?php

namespace Ksfraser\ModulesDAO\Contracts;

/**
 * Generic record store abstraction.
 *
 * Intended for tabular-ish backends (DB tables, CSV, some XML formats).
 *
 * This is intentionally minimal scaffolding.
 */
interface RecordStoreInterface extends StoreAvailabilityInterface
{
    /**
     * Fetch a single record by ID.
     *
     * @return array<string,mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * Fetch all records matching optional filters.
     *
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function findAll(array $filters = []): array;

    /**
     * Insert a new record and return its ID.
     *
     * @param array<string,mixed> $record
     */
    public function insert(array $record): string;

    /**
     * Update an existing record.
     *
     * @param array<string,mixed> $record
     */
    public function update(string $id, array $record): void;

    public function delete(string $id): void;
}
