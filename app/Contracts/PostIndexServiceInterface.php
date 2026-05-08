<?php

declare(strict_types=1);

namespace App\Contracts;

interface PostIndexServiceInterface
{
    public function getList(array $filters): array;

    public function createMany(array $items): int;

    public function deleteMany(array $postCodes): int;
}
