<?php

declare(strict_types=1);

namespace App\Domain\Country\DEU;

use App\Domain\Country\Contracts\CountryFieldsInterface;
use App\Domain\Shared\Enums\CountryCode;

final class DEUFields implements CountryFieldsInterface
{
    public function country(): CountryCode
    {
        return CountryCode::DEU;
    }

    public function storeRules(): array
    {
        return [
            'tax_id'                  => ['required', 'string', 'regex:/^DE\d{9}$/'],
            'goal'                    => ['required', 'string', 'max:1000'],
            'doc_work_permit'         => ['nullable', 'url'],
            'doc_tax_card'            => ['nullable', 'url'],
            'doc_health_insurance'    => ['nullable', 'url'],
            'doc_social_security'     => ['nullable', 'url'],
            'doc_employment_contract' => ['nullable', 'url'],
        ];
    }

    public function updateRules(): array
    {
        return [
            'tax_id'                  => ['sometimes', 'nullable', 'string', 'regex:/^DE\d{9}$/'],
            'goal'                    => ['sometimes', 'nullable', 'string', 'max:1000'],
            'doc_work_permit'         => ['sometimes', 'nullable', 'url'],
            'doc_tax_card'            => ['sometimes', 'nullable', 'url'],
            'doc_health_insurance'    => ['sometimes', 'nullable', 'url'],
            'doc_social_security'     => ['sometimes', 'nullable', 'url'],
            'doc_employment_contract' => ['sometimes', 'nullable', 'url'],
        ];
    }

    public function storeMessages(): array
    {
        return [
            'tax_id.required' => 'Tax ID is required for Germany employees.',
            'tax_id.regex'    => 'Tax ID must be in the format DEXXXXXXXXX (DE followed by 9 digits).',
            'goal.required'   => 'Goal is required for Germany employees.',
        ];
    }

    public function resourceFields(object $employee): array
    {
        return [
            'goal'                    => $employee->goal,
            'tax_id'                  => $employee->tax_id,
            'doc_work_permit'         => $employee->doc_work_permit,
            'doc_tax_card'            => $employee->doc_tax_card,
            'doc_health_insurance'    => $employee->doc_health_insurance,
            'doc_social_security'     => $employee->doc_social_security,
            'doc_employment_contract' => $employee->doc_employment_contract,
        ];
    }

    public function steps(): array
    {
        return [
            'section_title' => 'Germany-Specific Fields',
            'fields' => [
                [
                    'key'         => 'tax_id',
                    'label'       => 'Tax ID',
                    'type'        => 'text',
                    'placeholder' => 'DE123456789',
                    'required'    => true,
                ],
                [
                    'key'         => 'goal',
                    'label'       => 'Goal',
                    'type'        => 'text',
                    'placeholder' => 'Outline how this employee supports the company processes',
                    'required'    => true,
                ],
                [
                    'key'         => 'doc_work_permit',
                    'label'       => 'Work permit URL',
                    'type'        => 'url',
                    'placeholder' => 'https://files.example.com/work-permit.pdf',
                    'required'    => false,
                ],
                [
                    'key'         => 'doc_tax_card',
                    'label'       => 'Tax card URL',
                    'type'        => 'url',
                    'placeholder' => 'https://files.example.com/tax-card.pdf',
                    'required'    => false,
                ],
                [
                    'key'         => 'doc_health_insurance',
                    'label'       => 'Health insurance URL',
                    'type'        => 'url',
                    'placeholder' => 'https://files.example.com/health-insurance.pdf',
                    'required'    => false,
                ],
                [
                    'key'         => 'doc_social_security',
                    'label'       => 'Social security URL',
                    'type'        => 'url',
                    'placeholder' => 'https://files.example.com/social-security.pdf',
                    'required'    => false,
                ],
                [
                    'key'         => 'doc_employment_contract',
                    'label'       => 'Employment contract URL',
                    'type'        => 'url',
                    'placeholder' => 'https://files.example.com/employment-contract.pdf',
                    'required'    => false,
                ],
            ],
        ];
    }
}
