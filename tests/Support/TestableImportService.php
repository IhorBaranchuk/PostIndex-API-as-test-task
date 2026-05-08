<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\PostIndexRepositoryInterface;
use App\Contracts\RowIteratorInterface;
use App\Services\PostIndexXlsxImportService;

final class TestableImportService extends PostIndexXlsxImportService
{
    public function __construct(
        PostIndexRepositoryInterface $repository,
        private RowIteratorInterface $iterator
    ) {
        parent::__construct($repository);
    }

    public function import(string $source): array
    {
        $importId = 'test-import-id';
        $stats    = $this->processIterator($this->iterator, $importId);

        return array_merge(['import_id' => $importId], $stats);
    }
}
