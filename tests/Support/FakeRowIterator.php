<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\RowIteratorInterface;

final class FakeRowIterator implements RowIteratorInterface
{
    /** @param array<array<string, string>> $rows */
    public function __construct(
        private array $rows
    ) {}

    public function rows(): iterable
    {
        yield from $this->rows;
    }
}
