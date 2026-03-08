<?php

declare(strict_types=1);

namespace App\Domain\EventProcessing\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $table = 'event_log';

    protected $fillable = [
        'event_id',
        'event_type',
        'country',
        'employee_id',
        'status',
        'payload',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload'       => 'array',
        'received_at'   => 'datetime',
        'processed_at'  => 'datetime',
    ];
}
