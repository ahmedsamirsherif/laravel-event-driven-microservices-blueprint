<?php

declare(strict_types=1);

namespace App\Domain\Employee\ValueObjects;

use InvalidArgumentException;

final readonly class TaxId
{
    public string $value;

    public function __construct(string $value)
    {
        if (! preg_match('/^DE\d{9}$/', $value)) {
            throw new InvalidArgumentException("Invalid German TaxId format. Expected DE+9 digits, got: {$value}");
        }
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
