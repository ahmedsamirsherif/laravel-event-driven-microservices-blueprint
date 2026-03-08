<?php

declare(strict_types=1);

namespace App\Application\EventProcessing\Pipeline;

use App\Application\EventProcessing\Handlers\EventHandlerInterface;
use App\Domain\EventProcessing\Models\EventLog;
use App\Domain\EventProcessing\Models\ProcessedEvent;
use Illuminate\Support\Facades\Log;

final class EventProcessingPipeline
{
    /** @var EventHandlerInterface[] */
    private array $handlers = [];

    public function pipe(EventHandlerInterface $handler): static
    {
        $this->handlers[] = $handler;
        return $this;
    }

    public function process(array $payload): void
    {
        $eventId   = $payload['event_id']   ?? null;
        $eventType = $payload['event_type'] ?? 'unknown';
        $country   = $payload['country']    ?? 'unknown';
        $employeeId = $payload['data']['employee_id'] ?? null;

        if ($eventId && ProcessedEvent::where('event_id', $eventId)->exists()) {
            Log::info('EventProcessingPipeline: duplicate event skipped', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
            ]);
            return;
        }

        $logEntry = null;
        if ($eventId) {
            $logEntry = EventLog::updateOrCreate(
                ['event_id' => $eventId],
                [
                    'event_type'    => $eventType,
                    'country'       => $country,
                    'employee_id'   => $employeeId,
                    'status'        => 'received',
                    'payload'       => $payload,
                    'error_message' => null,
                    'received_at'   => now(),
                ],
            );
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($payload)) {
                Log::info('EventProcessingPipeline: dispatching to handler', [
                    'handler'    => get_class($handler),
                    'event_type' => $eventType,
                ]);

                try {
                    $handler->handle($payload);

                    if ($eventId) {
                        ProcessedEvent::create([
                            'event_id'     => $eventId,
                            'event_type'   => $eventType,
                            'processed_at' => now(),
                        ]);

                        $logEntry?->update(['status' => 'processed', 'processed_at' => now()]);
                    }
                } catch (\Throwable $e) {
                    $logEntry?->update([
                        'status'        => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                    throw $e;
                }

                return;
            }
        }

        Log::warning('EventProcessingPipeline: no handler for event', [
            'event_type' => $eventType,
        ]);

        $logEntry?->update(['status' => 'failed', 'error_message' => "No handler for {$eventType}"]);
    }
}
