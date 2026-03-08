<?php

declare(strict_types=1);

namespace App\Domain\Country\Shared;

final class SharedValidationRules
{
    public static function base(): array
    {
        return [
            'name'      => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'salary'    => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public static function baseMessages(): array
    {
        return [
            'name.required'      => 'Name is required.',
            'last_name.required' => 'Last name is required.',
            'salary.required'    => 'Salary is required.',
            'salary.numeric'     => 'Salary must be a number.',
            'salary.min'         => 'Salary must be greater than zero.',
        ];
    }
}
