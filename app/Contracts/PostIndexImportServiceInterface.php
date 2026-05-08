<?php

declare(strict_types=1);

namespace App\Contracts;

interface PostIndexImportServiceInterface
{
    /**
     * Import post indexes from the given source path (e.g. a zip archive).
     *
     * @return array{import_id: string, processed: int, skipped: int, deleted: int}
     */
    public function import(string $source): array;
}
