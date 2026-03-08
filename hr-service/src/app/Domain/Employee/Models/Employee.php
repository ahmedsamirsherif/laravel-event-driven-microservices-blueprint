<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use App\Domain\Employee\Models\EmployeeMedia;
use App\Domain\Employee\Models\Media;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    protected $fillable = [
        'name', 'last_name', 'salary', 'country',
        'ssn', 'address', 'goal', 'tax_id',
        'doc_work_permit', 'doc_tax_card', 'doc_health_insurance',
        'doc_social_security', 'doc_employment_contract',
    ];

    protected $casts = ['salary' => 'float'];

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'employee_media')
            ->using(EmployeeMedia::class)
            ->withPivot(['document_type', 'is_current'])
            ->wherePivot('is_current', true);
    }
}
