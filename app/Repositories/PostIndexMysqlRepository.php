<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\PostIndexRepositoryInterface;
use App\Enums\PostIndexSource;
use PDO;

final class PostIndexMysqlRepository implements PostIndexRepositoryInterface
{
    private const COLUMNS = 'post_code, region, district, locality, address, source, created_at, updated_at';

    public function __construct(
        private PDO $pdo
    ) {}

    public function paginate(int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;

        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . '
               FROM post_indexes
               ORDER BY post_code ASC
               LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM post_indexes')->fetchColumn();
    }

    public function findByPostCode(string $postCode): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . '
               FROM post_indexes
               WHERE post_code = :post_code
               LIMIT 1'
        );

        $stmt->execute(['post_code' => $postCode]);

        return $stmt->fetch() ?: null;
    }

    public function searchByAddress(string $address, int $page = 1, int $limit = 50): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $address);
        $offset  = ($page - 1) * $limit;

        $stmt = $this->pdo->prepare(
            'SELECT ' . self::COLUMNS . '
               FROM post_indexes
               WHERE address LIKE :address ESCAPE \'!\'
               ORDER BY address ASC
               LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':address', '%' . $escaped . '%');
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByAddress(string $address): int
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $address);

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM post_indexes WHERE address LIKE :address ESCAPE '!'"
        );

        $stmt->execute([':address' => '%' . $escaped . '%']);

        return (int) $stmt->fetchColumn();
    }

    public function upsertMany(array $items): void
    {
        if ($items === []) {
            return;
        }

        $placeholders = [];
        $values       = [];

        foreach ($items as $item) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?)';
            $values[]       = $item['post_code'];
            $values[]       = $item['region']    ?? null;
            $values[]       = $item['district']  ?? null;
            $values[]       = $item['locality']  ?? null;
            $values[]       = $item['address'];
            $values[]       = $item['source'];
        }

        $sql = 'INSERT INTO post_indexes
                    (post_code, region, district, locality, address, source)
                VALUES ' . implode(', ', $placeholders) . '
                ON DUPLICATE KEY UPDATE
                    region     = VALUES(region),
                    district   = VALUES(district),
                    locality   = VALUES(locality),
                    address    = VALUES(address),
                    source     = VALUES(source),
                    updated_at = CURRENT_TIMESTAMP';

        $this->pdo->prepare($sql)->execute($values);
    }

    public function deleteByPostCodes(array $postCodes): int
    {
        if ($postCodes === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($postCodes), '?'));

        $stmt = $this->pdo->prepare(
            "DELETE FROM post_indexes WHERE post_code IN ({$placeholders})"
        );

        $stmt->execute($postCodes);

        return $stmt->rowCount();
    }

    public function bulkUpsertFromArchive(array $items, string $importId): void
    {
        if ($items === []) {
            return;
        }

        $placeholders = [];
        $values       = [];

        foreach ($items as $item) {
            $placeholders[] = '(?, ?, ?, ?, ?, ?, ?)';
            $values[]       = $item['post_code'];
            $values[]       = $item['region'];
            $values[]       = $item['district'];
            $values[]       = $item['locality'];
            $values[]       = $item['address'];
            $values[]       = PostIndexSource::Archive->value;
            $values[]       = $importId;
        }

        $sql = 'INSERT INTO post_indexes
                    (post_code, region, district, locality, address, source, last_import_id)
                VALUES ' . implode(', ', $placeholders) . '
                ON DUPLICATE KEY UPDATE
                    region         = VALUES(region),
                    district       = VALUES(district),
                    locality       = VALUES(locality),
                    address        = VALUES(address),
                    source         = VALUES(source),
                    last_import_id = VALUES(last_import_id),
                    updated_at     = CURRENT_TIMESTAMP';

        $this->pdo->prepare($sql)->execute($values);
    }

    public function deleteArchiveRecordsNotInImport(string $importId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM post_indexes
               WHERE source = :source
               AND last_import_id <> :import_id'
        );

        $stmt->execute([
            'source'    => PostIndexSource::Archive->value,
            'import_id' => $importId,
        ]);

        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
