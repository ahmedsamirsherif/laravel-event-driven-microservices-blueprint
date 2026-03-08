<?php

declare(strict_types=1);

namespace App\Domain\Employee\ValueObjects;

use InvalidArgumentException;

final readonly class Salary
{
    public float $amount;

    public function __construct(float $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException("Salary must be >= 0, got: {$amount}");
        }
        $this->amount = $amount;
    }

    public function __toString(): string
    {
        return (string) $this->amount;
    }

    public function isPositive(): bool
    {
        return $this->amount > 0;
    }
}
