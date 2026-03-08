<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

enum CountryCode: string
{
    case USA = 'USA';
    case DEU = 'DEU';

    public function label(): string
    {
        return match ($this) {
            self::USA => 'United States',
            self::DEU => 'Germany',
        };
    }
}
