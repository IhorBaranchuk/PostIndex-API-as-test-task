<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PostIndexImportServiceInterface;
use App\Contracts\PostIndexRepositoryInterface;
use App\Contracts\RowIteratorInterface;
use App\Support\XlsxRowIterator;
use RuntimeException;
use ZipArchive;

class PostIndexXlsxImportService implements PostIndexImportServiceInterface
{
    private const CHUNK_SIZE = 1000;

    private const COL_POST_CODE = 'Поштовий індекс відділення зв`язку (Post code of post office)';
    private const COL_REGION    = 'Область';
    private const COL_DISTRICT  = 'Район (новий)';
    private const COL_LOCALITY  = 'Населений пункт';

    public function __construct(
        private PostIndexRepositoryInterface $repository
    ) {}

    public function import(string $source): array
    {
        if (!file_exists($source)) {
            throw new RuntimeException('Archive not found: ' . $source);
        }

        $importId    = bin2hex(random_bytes(16));
        $extractPath = __DIR__ . '/../../storage/imports/' . $importId;

        mkdir($extractPath, 0777, true);

        try {
            $xlsxPath = $this->extractXlsxFromZip($source, $extractPath);
            $iterator = $this->createIterator($xlsxPath);

            $stats = $this->processIterator($iterator, $importId);

            return array_merge(['import_id' => $importId], $stats);
        } finally {
            $this->cleanupDirectory($extractPath);
        }
    }

    protected function createIterator(string $filePath): RowIteratorInterface
    {
        return new XlsxRowIterator($filePath);
    }

    protected function processIterator(RowIteratorInterface $iterator, string $importId): array
    {
        $processed = 0;
        $skipped   = 0;
        $chunk     = [];
        $headers   = null;

        $this->repository->beginTransaction();

        try {
            foreach ($iterator->rows() as $row) {
                if ($headers === null) {
                    $headers = $this->detectHeaders($row);
                    continue;
                }

                $item = $this->mapRow($headers, $row);

                if ($item === null) {
                    $skipped++;
                    continue;
                }

                $chunk[] = $item;
                $processed++;

                if (count($chunk) >= self::CHUNK_SIZE) {
                    $this->repository->bulkUpsertFromArchive($chunk, $importId);
                    $chunk = [];
                }
            }

            if ($chunk !== []) {
                $this->repository->bulkUpsertFromArchive($chunk, $importId);
            }

            $deleted = $this->repository->deleteArchiveRecordsNotInImport($importId);

            $this->repository->commit();

            return ['processed' => $processed, 'skipped' => $skipped, 'deleted' => $deleted];
        } catch (\Throwable $e) {
            $this->repository->rollback();
            throw $e;
        }
    }

    private function detectHeaders(array $row): ?array
    {
        $headers = $this->normalizeHeaders($row);

        return array_filter($headers) !== [] ? $headers : null;
    }

    private function extractXlsxFromZip(string $zipPath, string $extractPath): string
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Cannot open zip archive.');
        }

        $zip->extractTo($extractPath);

        $xlsxPath = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $fileName = $zip->getNameIndex($i);

            if ($fileName !== false && str_ends_with(strtolower($fileName), '.xlsx')) {
                $xlsxPath = $extractPath . '/' . $fileName;
                break;
            }
        }

        $zip->close();

        if ($xlsxPath === null || !file_exists($xlsxPath)) {
            throw new RuntimeException('XLSX file not found in archive.');
        }

        return $xlsxPath;
    }

    private function normalizeHeaders(array $row): array
    {
        $headers = [];

        foreach ($row as $column => $value) {
            $headers[$column] = trim((string) $value);
        }

        return $headers;
    }

    private function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }

        rmdir($path);
    }

    private function mapRow(array $headers, array $row): ?array
    {
        $data = [];

        foreach ($headers as $column => $headerName) {
            $data[$headerName] = trim((string) ($row[$column] ?? ''));
        }

        $postCode = $data[self::COL_POST_CODE] ?? '';

        if ($postCode === '') {
            return null;
        }

        $region   = $data[self::COL_REGION]   ?? '';
        $district = $data[self::COL_DISTRICT] ?? '';
        $locality = $data[self::COL_LOCALITY] ?? '';

        $address = trim(implode(', ', array_filter([$region, $district, $locality])));

        return [
            'post_code' => $postCode,
            'region'    => $region   ?: null,
            'district'  => $district ?: null,
            'locality'  => $locality ?: null,
            'address'   => $address,
        ];
    }
}
