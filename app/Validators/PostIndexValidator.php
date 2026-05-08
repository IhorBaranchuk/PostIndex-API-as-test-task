<?php

declare(strict_types=1);

namespace App\Validators;

use InvalidArgumentException;

final class PostIndexValidator
{
    public function validatePostCode(string $postCode): void
    {
        if ($postCode === '') {
            throw new InvalidArgumentException('post_code is required.');
        }

        if (!preg_match('/^\d{5}$/', $postCode)) {
            throw new InvalidArgumentException(
                "post_code \"{$postCode}\" must be exactly 5 digits."
            );
        }
    }

    public function validateFields(array $item): void
    {
        foreach (['region', 'district', 'locality'] as $field) {
            if (isset($item[$field]) && mb_strlen((string) $item[$field]) > 255) {
                throw new InvalidArgumentException(
                    "{$field} must not exceed 255 characters."
                );
            }
        }
    }

    public function validateFilters(array $filters): void
    {
        if (isset($filters['post_code']) && $filters['post_code'] !== '') {
            $this->validatePostCode(trim((string) $filters['post_code']));
        }

        if (isset($filters['address']) && $filters['address'] !== '') {
            if (mb_strlen((string) $filters['address']) > 255) {
                throw new InvalidArgumentException('address must not exceed 255 characters.');
            }
        }

        if (isset($filters['page']) && !ctype_digit((string) $filters['page'])) {
            throw new InvalidArgumentException('page must be a positive integer.');
        }
    }
}
