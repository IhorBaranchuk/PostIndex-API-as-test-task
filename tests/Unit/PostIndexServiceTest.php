<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\PostIndexRepositoryInterface;
use App\Services\PostIndexService;
use App\Validators\PostIndexValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PostIndexServiceTest extends TestCase
{
    private PostIndexRepositoryInterface $repository;
    private PostIndexService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PostIndexRepositoryInterface::class);
        $this->service    = new PostIndexService($this->repository, new PostIndexValidator());
    }

    public function testGetListRoutesToFindByPostCode(): void
    {
        $record = ['post_code' => '01001', 'region' => 'Київська'];

        $this->repository
            ->expects($this->once())
            ->method('findByPostCode')
            ->with('01001')
            ->willReturn($record);

        $result = $this->service->getList(['post_code' => '01001']);

        $this->assertSame([$record], $result['items']);
        $this->assertSame(1, $result['total']);
        $this->assertSame(1, $result['page']);
    }

    public function testGetListReturnsEmptyWhenPostCodeNotFound(): void
    {
        $this->repository->method('findByPostCode')->willReturn(null);

        $result = $this->service->getList(['post_code' => '99999']);

        $this->assertSame([], $result['items']);
        $this->assertSame(0, $result['total']);
    }

    public function testGetListRoutesToSearchByAddress(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('searchByAddress')
            ->with('Київська', 1, 50)
            ->willReturn([]);

        $this->repository->method('countByAddress')->willReturn(0);

        $this->service->getList(['address' => 'Київська']);
    }

    public function testGetListAddressSearchReturnsMeta(): void
    {
        $this->repository->method('searchByAddress')->willReturn([]);
        $this->repository->method('countByAddress')->willReturn(120);

        $result = $this->service->getList(['address' => 'Київська', 'page' => '2']);

        $this->assertSame(120, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertSame(50, $result['per_page']);
    }

    public function testGetListRoutesToPaginateWithPage(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('paginate')
            ->with(3, 50)
            ->willReturn([]);

        $this->repository->method('countAll')->willReturn(0);

        $this->service->getList(['page' => '3']);
    }

    public function testGetListDefaultsToPageOne(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('paginate')
            ->with(1, 50)
            ->willReturn([]);

        $this->repository->method('countAll')->willReturn(27845);

        $result = $this->service->getList([]);

        $this->assertSame(27845, $result['total']);
        $this->assertSame(1, $result['page']);
    }

    public function testCreateManyThrowsWhenItemsEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Items are required.');

        $this->service->createMany([]);
    }

    public function testCreateManyCallsUpsertAndReturnsCount(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('upsertMany');

        $count = $this->service->createMany([
            ['post_code' => '01001', 'region' => 'Київська'],
            ['post_code' => '79000', 'region' => 'Львівська'],
        ]);

        $this->assertSame(2, $count);
    }

    public function testDeleteManyThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one post_code is required.');

        $this->service->deleteMany([]);
    }

    public function testDeleteManyCallsRepository(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('deleteByPostCodes')
            ->with(['01001', '79000'])
            ->willReturn(2);

        $count = $this->service->deleteMany(['01001', '79000']);

        $this->assertSame(2, $count);
    }
}
