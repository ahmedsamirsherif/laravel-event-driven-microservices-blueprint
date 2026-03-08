<?php

declare(strict_types=1);

namespace App\Domain\Employee\ValueObjects;

use App\Domain\Shared\Enums\CountryCode;

final readonly class Country
{
    public CountryCode $code;

    public function __construct(string|CountryCode $value)
    {
        $this->code = $value instanceof CountryCode ? $value : CountryCode::from($value);
    }

    public function __toString(): string
    {
        return $this->code->value;
    }
}
