<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\PostIndexServiceInterface;
use App\Controllers\PostIndexController;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\Response;

final class PostIndexControllerTest extends TestCase
{
    private PostIndexServiceInterface $service;
    private PostIndexController $controller;

    protected function setUp(): void
    {
        $this->service    = $this->createMock(PostIndexServiceInterface::class);
        $this->controller = new PostIndexController($this->service);
    }

    private function makeRequest(string $method, array $query = [], mixed $body = null): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, '/post-indexes');

        if ($query !== []) {
            $request = $request->withQueryParams($query);
        }

        if ($body !== null) {
            $stream  = (new StreamFactory())->createStream(json_encode($body, JSON_UNESCAPED_UNICODE));
            $request = $request->withBody($stream);
        }

        return $request;
    }

    private function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true);
    }

    private function makeGetListResult(array $items = [], int $total = 0, int $page = 1): array
    {
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => 50];
    }

    public function testIndexReturns200WithDataKey(): void
    {
        $items = [['post_code' => '01001', 'region' => 'Київська']];
        $this->service->method('getList')->willReturn($this->makeGetListResult($items, 1));

        $response = $this->controller->index($this->makeRequest('GET'), new Response());
        $body     = $this->decode($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($items, $body['data']);
        $this->assertSame(1, $body['meta']['total']);
        $this->assertSame(1, $body['meta']['page']);
        $this->assertSame(50, $body['meta']['per_page']);
    }

    public function testIndexReturnsEmptyDataArray(): void
    {
        $this->service->method('getList')->willReturn($this->makeGetListResult());

        $response = $this->controller->index($this->makeRequest('GET'), new Response());
        $body     = $this->decode($response);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $body['data']);
        $this->assertArrayHasKey('meta', $body);
    }

    public function testIndexPassesQueryParamsToService(): void
    {
        $this->service
            ->expects($this->once())
            ->method('getList')
            ->with(['post_code' => '01001'])
            ->willReturn($this->makeGetListResult());

        $this->controller->index($this->makeRequest('GET', ['post_code' => '01001']), new Response());
    }

    public function testIndexReturns422OnServiceException(): void
    {
        $this->service
            ->method('getList')
            ->willThrowException(new InvalidArgumentException('must be exactly 5 digits.'));

        $response = $this->controller->index(
            $this->makeRequest('GET', ['post_code' => 'abc']),
            new Response()
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('error', $this->decode($response));
    }


    public function testStoreReturns201OnSuccess(): void
    {
        $this->service->method('createMany')->willReturn(1);

        $response = $this->controller->store(
            $this->makeRequest('POST', [], ['post_code' => '01001']),
            new Response()
        );

        $this->assertSame(201, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame(1, $body['count']);
        $this->assertArrayHasKey('message', $body);
    }

    public function testStoreWrapsObjectInArray(): void
    {
        $this->service
            ->expects($this->once())
            ->method('createMany')
            ->with([['post_code' => '01001']])
            ->willReturn(1);

        $this->controller->store(
            $this->makeRequest('POST', [], ['post_code' => '01001']),
            new Response()
        );
    }

    public function testStorePassesArrayAsIs(): void
    {
        $items = [['post_code' => '01001'], ['post_code' => '79000']];

        $this->service
            ->expects($this->once())
            ->method('createMany')
            ->with($items)
            ->willReturn(2);

        $response = $this->controller->store(
            $this->makeRequest('POST', [], $items),
            new Response()
        );

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(2, $this->decode($response)['count']);
    }

    public function testStoreReturns422OnServiceException(): void
    {
        $this->service
            ->method('createMany')
            ->willThrowException(new InvalidArgumentException('post_code is required.'));

        $response = $this->controller->store(
            $this->makeRequest('POST', [], ['post_code' => '']),
            new Response()
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('post_code is required.', $this->decode($response)['error']);
    }

    public function testStoreReturns422OnInvalidJson(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', '/post-indexes')
            ->withBody((new StreamFactory())->createStream('not-json'));

        $response = $this->controller->store($request, new Response());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('error', $this->decode($response));
    }

    public function testDeleteReturns200WithCount(): void
    {
        $this->service->method('deleteMany')->willReturn(1);

        $response = $this->controller->delete(
            $this->makeRequest('DELETE', [], ['post_code' => '01001']),
            new Response()
        );

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->decode($response);
        $this->assertSame(1, $body['count']);
        $this->assertArrayHasKey('message', $body);
    }

    public function testDeletePassesSinglePostCode(): void
    {
        $this->service
            ->expects($this->once())
            ->method('deleteMany')
            ->with(['01001'])
            ->willReturn(1);

        $this->controller->delete(
            $this->makeRequest('DELETE', [], ['post_code' => '01001']),
            new Response()
        );
    }

    public function testDeletePassesPostCodesArray(): void
    {
        $this->service
            ->expects($this->once())
            ->method('deleteMany')
            ->with(['01001', '79000'])
            ->willReturn(2);

        $this->controller->delete(
            $this->makeRequest('DELETE', [], ['post_codes' => ['01001', '79000']]),
            new Response()
        );
    }

    public function testDeleteReturns422WhenNoPostCodeKey(): void
    {
        $response = $this->controller->delete(
            $this->makeRequest('DELETE', [], ['wrong_key' => '01001']),
            new Response()
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('error', $this->decode($response));
    }

    public function testDeleteReturns422OnServiceException(): void
    {
        $this->service
            ->method('deleteMany')
            ->willThrowException(new InvalidArgumentException('must be exactly 5 digits.'));

        $response = $this->controller->delete(
            $this->makeRequest('DELETE', [], ['post_code' => 'abc']),
            new Response()
        );

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('must be exactly 5 digits.', $this->decode($response)['error']);
    }

    public function testDeleteReturns422OnInvalidJson(): void
    {
        $request = (new ServerRequestFactory())
            ->createServerRequest('DELETE', '/post-indexes')
            ->withBody((new StreamFactory())->createStream('not-json'));

        $response = $this->controller->delete($request, new Response());

        $this->assertSame(422, $response->getStatusCode());
        $this->assertArrayHasKey('error', $this->decode($response));
    }
}
