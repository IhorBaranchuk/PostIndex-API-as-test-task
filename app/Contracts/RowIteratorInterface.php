<?php

declare(strict_types=1);

namespace App\Contracts;

interface RowIteratorInterface
{
    /**
     * Yields rows as associative arrays keyed by column identifier.
     *
     * @return iterable<array<string, string>>
     */
    public function rows(): iterable;
}
