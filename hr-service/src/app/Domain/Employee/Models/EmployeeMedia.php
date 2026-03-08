<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Domain\Shared\Enums\DocumentType;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeMedia extends Pivot
{
    public $timestamps = false;

    protected $table = 'employee_media';

    protected $fillable = ['employee_id', 'media_id', 'document_type', 'is_current'];

    protected $casts = [
        'is_current'    => 'boolean',
        'document_type' => DocumentType::class,
    ];
}
