<?php

declare(strict_types=1);

use App\Domain\Employee\ValueObjects\Salary;

it('accepts zero and positive amounts, rejects negative', function () {
    expect((new Salary(0.0))->amount)->toBe(0.0)
        ->and((new Salary(75000.0))->amount)->toBe(75000.0)
        ->and(fn () => new Salary(-1.0))->toThrow(InvalidArgumentException::class);
});

it('reports positive status and converts to string', function () {
    expect((new Salary(0.0))->isPositive())->toBeFalse()
        ->and((new Salary(75000.0))->isPositive())->toBeTrue()
        ->and((string) new Salary(75000.0))->toBe('75000');
});
