<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PostIndexRepositoryInterface;
use App\Contracts\PostIndexServiceInterface;
use App\Enums\PostIndexSource;
use App\Validators\PostIndexValidator;
use InvalidArgumentException;

final class PostIndexService implements PostIndexServiceInterface
{
    private const PER_PAGE = 50;

    public function __construct(
        private PostIndexRepositoryInterface $repository,
        private PostIndexValidator $validator,
    ) {}

    public function getList(array $filters): array
    {
        $this->validator->validateFilters($filters);

        $page = max(1, (int) ($filters['page'] ?? 1));

        if (isset($filters['post_code']) && $filters['post_code'] !== '') {
            $item  = $this->repository->findByPostCode(trim((string) $filters['post_code']));
            $items = $item ? [$item] : [];

            return ['items' => $items, 'total' => count($items), 'page' => 1, 'per_page' => self::PER_PAGE];
        }

        if (isset($filters['address']) && $filters['address'] !== '') {
            $address = (string) $filters['address'];

            return [
                'items'    => $this->repository->searchByAddress($address, $page, self::PER_PAGE),
                'total'    => $this->repository->countByAddress($address),
                'page'     => $page,
                'per_page' => self::PER_PAGE,
            ];
        }

        return [
            'items'    => $this->repository->paginate($page, self::PER_PAGE),
            'total'    => $this->repository->countAll(),
            'page'     => $page,
            'per_page' => self::PER_PAGE,
        ];
    }

    public function createMany(array $items): int
    {
        if ($items === []) {
            throw new InvalidArgumentException('Items are required.');
        }

        $preparedItems = [];

        foreach ($items as $item) {
            $postCode = trim((string) ($item['post_code'] ?? ''));

            $this->validator->validatePostCode($postCode);
            $this->validator->validateFields($item);

            $preparedItems[] = [
                'post_code' => $postCode,
                'region'    => $item['region']   ?? null,
                'district'  => $item['district'] ?? null,
                'locality'  => $item['locality'] ?? null,
                'address'   => $this->buildAddress($item),
                'source'    => PostIndexSource::Api->value,
            ];
        }

        $this->repository->upsertMany($preparedItems);

        return count($preparedItems);
    }

    public function deleteMany(array $postCodes): int
    {
        $postCodes = array_values(array_filter(array_map('strval', $postCodes)));

        if ($postCodes === []) {
            throw new InvalidArgumentException('At least one post_code is required.');
        }

        foreach ($postCodes as $postCode) {
            $this->validator->validatePostCode($postCode);
        }

        return $this->repository->deleteByPostCodes($postCodes);
    }

    private function buildAddress(array $item): string
    {
        return trim(implode(', ', array_filter([
            $item['region']   ?? null,
            $item['district'] ?? null,
            $item['locality'] ?? null,
        ])));
    }
}
