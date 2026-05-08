<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\PostIndexRepositoryInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\FakeRowIterator;
use Tests\Support\TestableImportService;

final class PostIndexXlsxImportServiceTest extends TestCase
{
    private PostIndexRepositoryInterface $repository;

    private const HEADERS = [
        0 => 'Поштовий індекс відділення зв`язку (Post code of post office)',
        1 => 'Область',
        2 => 'Район (новий)',
        3 => 'Населений пункт',
    ];

    protected function setUp(): void
    {
        $this->repository = $this->createMock(PostIndexRepositoryInterface::class);
    }

    private function makeService(array $rows): TestableImportService
    {
        return new TestableImportService($this->repository, new FakeRowIterator($rows));
    }

    public function testReturnsCorrectStats(): void
    {
        $this->repository->method('deleteArchiveRecordsNotInImport')->willReturn(3);


        $result = $this->makeService([
            self::HEADERS,
            [0 => '01001', 1 => 'Київська',  2 => 'Київський',  3 => 'м. Київ'],
            [0 => '79000', 1 => 'Львівська', 2 => 'Львівський', 3 => 'м. Львів'],
        ])->import('irrelevant');

        $this->assertSame('test-import-id', $result['import_id']);
        $this->assertSame(2, $result['processed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(3, $result['deleted']);
    }

    public function testSkipsRowWithEmptyPostCode(): void
    {
        $result = $this->makeService([
            self::HEADERS,
            [0 => '',      1 => 'Київська', 2 => '', 3 => ''],
            [0 => '01001', 1 => 'Київська', 2 => '', 3 => ''],
        ])->import('irrelevant');

        $this->assertSame(1, $result['processed']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testMapsRowFieldsCorrectly(): void
    {
        $captured = null;
        $this->repository
            ->expects($this->once())
            ->method('bulkUpsertFromArchive')
            ->willReturnCallback(function (array $chunk) use (&$captured): void {
                $captured = $chunk;
            });

        $this->makeService([
            self::HEADERS,
            [0 => '01001', 1 => 'Київська', 2 => 'Київський', 3 => 'м. Київ'],
        ])->import('irrelevant');

        $this->assertSame('01001',                        $captured[0]['post_code']);
        $this->assertSame('Київська',                     $captured[0]['region']);
        $this->assertSame('Київський',                    $captured[0]['district']);
        $this->assertSame('м. Київ',                      $captured[0]['locality']);
        $this->assertSame('Київська, Київський, м. Київ', $captured[0]['address']);
    }

    public function testEmptyFieldsBecomeNull(): void
    {
        $captured = null;
        $this->repository
            ->method('bulkUpsertFromArchive')
            ->willReturnCallback(function (array $chunk) use (&$captured): void {
                $captured = $chunk;
            });

        $this->makeService([
            self::HEADERS,
            [0 => '01001', 1 => '', 2 => '', 3 => ''],
        ])->import('irrelevant');

        $this->assertNull($captured[0]['region']);
        $this->assertNull($captured[0]['district']);
        $this->assertNull($captured[0]['locality']);
        $this->assertSame('', $captured[0]['address']);
    }

    public function testPassesImportIdToUpsertAndDelete(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('bulkUpsertFromArchive')
            ->with($this->anything(), 'test-import-id');

        $this->repository
            ->expects($this->once())
            ->method('deleteArchiveRecordsNotInImport')
            ->with('test-import-id')
            ->willReturn(0);

        $this->makeService([
            self::HEADERS,
            [0 => '01001', 1 => 'Київська', 2 => '', 3 => ''],
        ])->import('irrelevant');
    }

    public function testWrapsInTransaction(): void
    {
        $this->repository->expects($this->once())->method('beginTransaction');
        $this->repository->expects($this->once())->method('commit');
        $this->repository->expects($this->never())->method('rollback');

        $this->makeService([self::HEADERS])->import('irrelevant');
    }

    public function testRollsBackOnException(): void
    {
        $this->repository
            ->method('bulkUpsertFromArchive')
            ->willThrowException(new RuntimeException('DB error'));

        $this->repository->expects($this->once())->method('rollback');
        $this->repository->expects($this->never())->method('commit');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        $this->makeService([
            self::HEADERS,
            [0 => '01001', 1 => 'Київська', 2 => '', 3 => ''],
        ])->import('irrelevant');
    }

    public function testHeaderOnlyFileProcessesZeroRows(): void
    {
        $this->repository->expects($this->never())->method('bulkUpsertFromArchive');

        $result = $this->makeService([self::HEADERS])->import('irrelevant');

        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['skipped']);
    }
}
