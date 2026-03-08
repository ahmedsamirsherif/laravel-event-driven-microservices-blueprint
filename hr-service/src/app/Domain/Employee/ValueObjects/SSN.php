<?php

declare(strict_types=1);

namespace App\Domain\Employee\ValueObjects;

use InvalidArgumentException;

final readonly class SSN
{
    public string $value;

    public function __construct(string $value)
    {
        if (! preg_match('/^\d{3}-\d{2}-\d{4}$/', $value)) {
            throw new InvalidArgumentException("Invalid SSN format. Expected XXX-XX-XXXX, got: {$value}");
        }
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function masked(): string
    {
        return '***-**-'.substr($this->value, -4);
    }
}
