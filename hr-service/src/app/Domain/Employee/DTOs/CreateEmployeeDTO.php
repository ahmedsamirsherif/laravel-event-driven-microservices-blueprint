<?php

declare(strict_types=1);

namespace App\Domain\Employee\DTOs;

final readonly class CreateEmployeeDTO
{
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            lastName: (string) $data['last_name'],
            salary: (float) $data['salary'],
            country: (string) $data['country'],
            ssn: $data['ssn'] ?? null,
            address: $data['address'] ?? null,
            goal: $data['goal'] ?? null,
            taxId: $data['tax_id'] ?? null,
            docWorkPermit: $data['doc_work_permit'] ?? null,
            docTaxCard: $data['doc_tax_card'] ?? null,
            docHealthInsurance: $data['doc_health_insurance'] ?? null,
            docSocialSecurity: $data['doc_social_security'] ?? null,
            docEmploymentContract: $data['doc_employment_contract'] ?? null,
        );
    }

    public function __construct(
        public string $name,
        public string $lastName,
        public float $salary,
        public string $country,
        public ?string $ssn = null,
        public ?string $address = null,
        public ?string $goal = null,
        public ?string $taxId = null,
        public ?string $docWorkPermit = null,
        public ?string $docTaxCard = null,
        public ?string $docHealthInsurance = null,
        public ?string $docSocialSecurity = null,
        public ?string $docEmploymentContract = null,
    ) {}
}
