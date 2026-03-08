<?php

declare(strict_types=1);

use App\Domain\Employee\ValueObjects\TaxId;

it('accepts valid German TaxId and converts to string', function () {
    $vo = new TaxId('DE123456789');
    expect($vo->value)->toBe('DE123456789')
        ->and((string) $vo)->toBe('DE123456789');
});

it('rejects invalid TaxId formats', function () {
    expect(fn () => new TaxId('123456789'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TaxId('de123456789'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new TaxId(''))->toThrow(InvalidArgumentException::class);
});
