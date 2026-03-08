<?php

declare(strict_types=1);

use App\Domain\Shared\Enums\DocumentType;

it('has correct cases, values, labels, and columnName', function () {
    expect(DocumentType::cases())->toHaveCount(5);
    expect(DocumentType::WORK_PERMIT->value)->toBe('doc_work_permit')
        ->and(DocumentType::TAX_CARD->value)->toBe('doc_tax_card')
        ->and(DocumentType::EMPLOYMENT_CONTRACT->value)->toBe('doc_employment_contract');
    expect(DocumentType::WORK_PERMIT->label())->toBe('Work Permit')
        ->and(DocumentType::HEALTH_INSURANCE->label())->toBe('Health Insurance');
    foreach (DocumentType::cases() as $type) {
        expect($type->columnName())->toBe($type->value);
    }
});

it('resolves from string value and returns null for unknown', function () {
    expect(DocumentType::from('doc_work_permit'))->toBe(DocumentType::WORK_PERMIT);
    expect(DocumentType::tryFrom('unknown'))->toBeNull()->and(DocumentType::tryFrom(''))->toBeNull();
});
