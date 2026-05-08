<?php

declare(strict_types=1);

use App\Database;
use App\Repositories\PostIndexMysqlRepository;
use App\Services\PostIndexXlsxImportService;

require __DIR__ . '/../vendor/autoload.php';

$zipPath = $argv[1] ?? __DIR__ . '/../storage/imports/postindex.zip';

$repository = new PostIndexMysqlRepository(Database::connect());
$service = new PostIndexXlsxImportService($repository);

$result = $service->import($zipPath);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;