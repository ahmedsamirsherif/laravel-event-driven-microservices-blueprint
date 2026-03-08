<?php

declare(strict_types=1);

namespace App\Domain\Employee\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Media extends Model
{
    protected $fillable = ['original_name', 'url', 'mime_type', 'size', 'disk'];

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'employee_media')
            ->withPivot(['document_type', 'is_current'])
            ->withTimestamps();
    }
}
