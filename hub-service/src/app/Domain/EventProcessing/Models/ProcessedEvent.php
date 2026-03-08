<?php

declare(strict_types=1);

namespace App\Domain\EventProcessing\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedEvent extends Model
{
    public $timestamps = false;
    protected $table   = 'processed_events';

    protected $fillable = [
        'event_id',
        'event_type',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
