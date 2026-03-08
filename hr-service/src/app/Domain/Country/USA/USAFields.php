<?php

declare(strict_types=1);

namespace App\Domain\Country\USA;

use App\Domain\Country\Contracts\CountryFieldsInterface;
use App\Domain\Shared\Enums\CountryCode;

final class USAFields implements CountryFieldsInterface
{
    public function country(): CountryCode
    {
        return CountryCode::USA;
    }

    public function storeRules(): array
    {
        return [
            'ssn' => ['nullable', 'string', 'regex:/^\d{3}-\d{2}-\d{4}$/'],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function updateRules(): array
    {
        return [
            'ssn' => ['sometimes', 'nullable', 'string', 'regex:/^\d{3}-\d{2}-\d{4}$/'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function storeMessages(): array
    {
        return [
            'ssn.regex' => 'SSN must be in the format XXX-XX-XXXX.',
        ];
    }

    public function resourceFields(object $employee): array
    {
        return [
            'ssn' => $employee->ssn
                ? '***-**-' . substr($employee->ssn, -4)
                : null,
            'address' => $employee->address,
        ];
    }

    public function steps(): array
    {
        return [
            'section_title' => 'USA-Specific Fields',
            'fields' => [
                [
                    'key'         => 'ssn',
                    'label'       => 'SSN',
                    'type'        => 'text',
                    'placeholder' => '123-45-6789',
                    'required'    => true,
                    'input_mode'  => 'numeric',
                ],
                [
                    'key'          => 'address',
                    'label'        => 'Address',
                    'type'         => 'text',
                    'placeholder'  => '123 Main St, New York, NY 10001',
                    'required'     => true,
                    'autocomplete' => 'street-address',
                ],
            ],
        ];
    }
}
