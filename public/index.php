<?php

declare(strict_types=1);

use App\Controllers\PostIndexController;
use App\Database;
use App\Repositories\PostIndexMysqlRepository;
use App\Services\PostIndexService;
use App\Validators\PostIndexValidator;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    return $response
      ->withHeader('Access-Control-Allow-Origin', '*')
      ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
      ->withHeader('Access-Control-Allow-Methods', 'GET, POST, DELETE, OPTIONS');
});

$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});

$app->get('/ping', function ($request, $response) {
    $response->getBody()->write('pong');

    return $response;
});

$pdo = Database::connect();

$repository = new PostIndexMysqlRepository($pdo);
$service    = new PostIndexService($repository, new PostIndexValidator());
$controller = new PostIndexController($service);

$app->get('/post-indexes', [$controller, 'index']);
$app->post('/post-indexes', [$controller, 'store']);
$app->delete('/post-indexes', [$controller, 'delete']);

$app->run();