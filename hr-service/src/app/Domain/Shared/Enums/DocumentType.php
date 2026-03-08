<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

enum DocumentType: string
{
    case WORK_PERMIT         = 'doc_work_permit';
    case TAX_CARD            = 'doc_tax_card';
    case HEALTH_INSURANCE    = 'doc_health_insurance';
    case SOCIAL_SECURITY     = 'doc_social_security';
    case EMPLOYMENT_CONTRACT = 'doc_employment_contract';

    public function label(): string
    {
        return match ($this) {
            self::WORK_PERMIT         => 'Work Permit',
            self::TAX_CARD            => 'Tax Card',
            self::HEALTH_INSURANCE    => 'Health Insurance',
            self::SOCIAL_SECURITY     => 'Social Security Letter',
            self::EMPLOYMENT_CONTRACT => 'Employment Contract',
        };
    }

    public function columnName(): string
    {
        return $this->value;
    }
}
