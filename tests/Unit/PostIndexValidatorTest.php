<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Validators\PostIndexValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PostIndexValidatorTest extends TestCase
{
    private PostIndexValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new PostIndexValidator();
    }

    public function testValidatePostCodePassesForValidCode(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validatePostCode('01001');
    }

    public function testValidatePostCodeThrowsWhenEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('post_code is required.');

        $this->validator->validatePostCode('');
    }

    public function testValidatePostCodeThrowsWhenNotFiveDigits(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be exactly 5 digits');

        $this->validator->validatePostCode('abcde');
    }

    public function testValidatePostCodeThrowsWhenTooShort(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->validatePostCode('0100');
    }

    public function testValidatePostCodeThrowsWhenTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->validator->validatePostCode('010011');
    }

    public function testValidateFieldsPassesWhenAllWithinLimit(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validateFields(['region' => str_repeat('а', 255)]);
    }

    public function testValidateFieldsThrowsWhenRegionTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('region must not exceed 255 characters.');

        $this->validator->validateFields(['region' => str_repeat('а', 256)]);
    }

    public function testValidateFieldsThrowsWhenDistrictTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('district must not exceed 255 characters.');

        $this->validator->validateFields(['district' => str_repeat('а', 256)]);
    }

    public function testValidateFieldsThrowsWhenLocalityTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('locality must not exceed 255 characters.');

        $this->validator->validateFields(['locality' => str_repeat('а', 256)]);
    }

    public function testValidateFiltersPassesForValidPostCode(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validateFilters(['post_code' => '01001']);
    }

    public function testValidateFiltersThrowsOnInvalidPostCode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be exactly 5 digits');

        $this->validator->validateFilters(['post_code' => 'abc']);
    }

    public function testValidateFiltersThrowsWhenAddressTooLong(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('address must not exceed 255 characters.');

        $this->validator->validateFilters(['address' => str_repeat('а', 256)]);
    }

    public function testValidateFiltersThrowsWhenPageNonNumeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('page must be a positive integer.');

        $this->validator->validateFilters(['page' => 'abc']);
    }

    public function testValidateFiltersThrowsWhenPageNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('page must be a positive integer.');

        $this->validator->validateFilters(['page' => '-1']);
    }

    public function testValidateFiltersIgnoresEmptyPostCode(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validateFilters(['post_code' => '']);
    }

    public function testValidateFiltersIgnoresEmptyAddress(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validateFilters(['address' => '']);
    }
}
