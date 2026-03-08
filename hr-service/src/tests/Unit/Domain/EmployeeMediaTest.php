<?php

declare(strict_types=1);

use App\Domain\Employee\Models\EmployeeMedia;
use App\Domain\Shared\Enums\DocumentType;

it('has correct pivot config with boolean and enum casts', function () {
    $pivot = new EmployeeMedia();
    expect($pivot->getTable())->toBe('employee_media')
        ->and($pivot->getFillable())->toBe(['employee_id', 'media_id', 'document_type', 'is_current'])
        ->and($pivot)->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\Pivot::class)
        ->and($pivot->getCasts()['is_current'])->toBe('boolean')
        ->and($pivot->getCasts()['document_type'])->toBe(DocumentType::class);
});
