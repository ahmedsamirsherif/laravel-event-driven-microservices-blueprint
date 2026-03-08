<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use Database\Factories\EmployeeProjectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeProjection extends Model
{
    /** @use HasFactory<EmployeeProjectionFactory> */
    use HasFactory;

    protected $table = 'employee_projections';

    protected $fillable = [
        'employee_id',
        'name',
        'last_name',
        'salary',
        'country',
        'ssn',
        'address',
        'goal',
        'tax_id',
        'doc_work_permit',
        'doc_tax_card',
        'doc_health_insurance',
        'doc_social_security',
        'doc_employment_contract',
        'raw_data',
    ];

    protected $casts = [
        'salary'   => 'float',
        'raw_data' => 'array',
    ];

    protected static function newFactory(): EmployeeProjectionFactory
    {
        return EmployeeProjectionFactory::new();
    }
}
