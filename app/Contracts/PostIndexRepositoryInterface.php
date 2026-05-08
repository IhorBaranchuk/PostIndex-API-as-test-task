<?php

declare(strict_types=1);

namespace App\Contracts;

interface PostIndexRepositoryInterface
{
    public function paginate(int $page = 1, int $limit = 50): array;

    public function countAll(): int;

    public function findByPostCode(string $postCode): ?array;

    public function searchByAddress(string $address, int $page = 1, int $limit = 50): array;

    public function countByAddress(string $address): int;

    public function upsertMany(array $items): void;

    public function deleteByPostCodes(array $postCodes): int;

    public function bulkUpsertFromArchive(array $items, string $importId): void;

    public function deleteArchiveRecordsNotInImport(string $importId): int;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;
}
