<?php

declare(strict_types=1);

use App\Domain\Employee\ValueObjects\SSN;

it('accepts valid SSN and converts to string', function () {
    $vo = new SSN('123-45-6789');
    expect($vo->value)->toBe('123-45-6789')
        ->and((string) $vo)->toBe('123-45-6789');
});

it('rejects invalid SSN formats', function () {
    expect(fn () => new SSN('123456789'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SSN('ABC-DE-FGHI'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new SSN(''))->toThrow(InvalidArgumentException::class);
});

it('masks SSN showing only last 4 digits', function () {
    expect((new SSN('123-45-6789'))->masked())->toBe('***-**-6789');
});
