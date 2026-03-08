<?php

declare(strict_types=1);

namespace App\Domain\Employee\DTOs;

final readonly class UpdateEmployeeDTO
{
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            lastName: $data['last_name'] ?? null,
            salary: array_key_exists('salary', $data) ? (float) $data['salary'] : null,
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
        public ?string $name = null,
        public ?string $lastName = null,
        public ?float $salary = null,
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
