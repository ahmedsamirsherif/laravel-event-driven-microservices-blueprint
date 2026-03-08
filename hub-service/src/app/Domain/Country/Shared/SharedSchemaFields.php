<?php

declare(strict_types=1);

namespace App\Domain\Country\Shared;

final class SharedSchemaFields
{
    /** @return array<string, array{type: string, required: bool}> */
    public static function base(): array
    {
        return [
            'name'      => ['type' => 'string', 'required' => true],
            'last_name' => ['type' => 'string', 'required' => true],
            'salary'    => ['type' => 'number', 'required' => true],
        ];
    }
}
