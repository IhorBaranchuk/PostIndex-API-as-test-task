<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Contracts\PostIndexServiceInterface;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PostIndexController
{
    private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT;

    public function __construct(
        private PostIndexServiceInterface $service
    ) {}

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        try {
            $result = $this->service->getList($request->getQueryParams());

            return $this->json($response, [
                'data' => $result['items'],
                'meta' => [
                    'total'    => $result['total'],
                    'page'     => $result['page'],
                    'per_page' => $result['per_page'],
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        }
    }

    public function store(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        try {
            $payload = json_decode((string) $request->getBody(), true);

            if (!is_array($payload)) {
                throw new InvalidArgumentException('Invalid JSON payload.');
            }

            $items = isset($payload[0]) ? $payload : [$payload];

            $createdCount = $this->service->createMany($items);

            return $this->json($response, [
                'message' => 'Post indexes saved successfully.',
                'count'   => $createdCount,
            ], 201);
        } catch (InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        }
    }

    public function delete(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        try {
            $payload = json_decode((string) $request->getBody(), true);

            if (!is_array($payload)) {
                throw new InvalidArgumentException('Invalid JSON payload.');
            }

            if (isset($payload['post_code'])) {
                $postCodes = [(string) $payload['post_code']];
            } elseif (isset($payload['post_codes']) && is_array($payload['post_codes'])) {
                $postCodes = $payload['post_codes'];
            } else {
                throw new InvalidArgumentException('post_code or post_codes is required.');
            }

            $deletedCount = $this->service->deleteMany($postCodes);

            return $this->json($response, [
                'message' => 'Post indexes deleted successfully.',
                'count'   => $deletedCount,
            ]);
        } catch (InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        }
    }

    private function json(
        ResponseInterface $response,
        array $data,
        int $statusCode = 200
    ): ResponseInterface {
        $response->getBody()->write(json_encode($data, self::JSON_FLAGS));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }
}
