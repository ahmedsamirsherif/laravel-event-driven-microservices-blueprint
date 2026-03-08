<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentType;

it('has correct values, labels, and resolves from string', function () {
    expect(DocumentType::WORK_PERMIT->value)->toBe('doc_work_permit')
        ->and(DocumentType::EMPLOYMENT_CONTRACT->value)->toBe('doc_employment_contract')
        ->and(DocumentType::WORK_PERMIT->label())->toBe('Work Permit')
        ->and(DocumentType::WORK_PERMIT->columnName())->toBe('doc_work_permit')
        ->and(DocumentType::from('doc_work_permit'))->toBe(DocumentType::WORK_PERMIT)
        ->and(DocumentType::tryFrom('unknown'))->toBeNull();
});
