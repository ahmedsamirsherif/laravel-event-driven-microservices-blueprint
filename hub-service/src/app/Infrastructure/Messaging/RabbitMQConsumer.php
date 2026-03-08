<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Application\Checklist\ChecklistService;
use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;
use App\Infrastructure\Broadcasting\Events\EmployeeUpdatedBroadcast;
use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final class RabbitMQConsumer
{
    private const EXCHANGE     = 'employee_events';
    private const EXCHANGE_DLX = 'employee_events_dlx';
    private const QUEUE        = 'hub_employee_events';
    private const QUEUE_DLQ    = 'hub_employee_events_dlq';
    private const MAX_RETRIES  = 3;

    private bool $shouldStop = false;

    public function __construct(
        private readonly EventProcessingPipeline $pipeline,
        private readonly PrometheusMetricsService $metrics,
        private readonly ChecklistService $checklistService,
    ) {}

    public function consume(int $prefetchCount = 1): void
    {
        $this->setupSignalHandlers();

        $connection = new AMQPStreamConnection(
            host: config('rabbitmq.host'),
            port: (int) config('rabbitmq.port'),
            user: config('rabbitmq.user'),
            password: config('rabbitmq.password'),
            vhost: config('rabbitmq.vhost'),
        );

        $channel = $connection->channel();
        $channel->basic_qos(null, $prefetchCount, false);

        // Setup DLX + DLQ
        $channel->exchange_declare(self::EXCHANGE_DLX, 'fanout', false, true, false);
        $channel->queue_declare(self::QUEUE_DLQ, false, true, false, false);
        $channel->queue_bind(self::QUEUE_DLQ, self::EXCHANGE_DLX);

        // Main exchange + queue with DLX
        $channel->exchange_declare(self::EXCHANGE, 'topic', false, true, false);
        $channel->queue_declare(self::QUEUE, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => self::EXCHANGE_DLX,
            'x-message-ttl'          => 86400000, // 24h
        ]));
        $channel->queue_bind(self::QUEUE, self::EXCHANGE, 'employee.#');

        $channel->basic_consume(self::QUEUE, '', false, false, false, false, function (AMQPMessage $msg): void {
            $this->processMessage($msg);
        });

        Log::info('RabbitMQ consumer started', ['queue' => self::QUEUE]);

        while ($channel->is_consuming() && ! $this->shouldStop) {
            try {
                $channel->wait(null, false, 1.0);
            } catch (AMQPTimeoutException) {
                // No message received within timeout — continue polling
            }
        }

        $channel->close();
        $connection->close();

        Log::info('RabbitMQ consumer stopped gracefully');
    }

    /**
     * Returns the current retry count from the x-death header.
     */
    public function getRetryCount(AMQPMessage $msg): int
    {
        $headers = $msg->get_properties()['application_headers'] ?? null;
        if ($headers instanceof AMQPTable) {
            $data = $headers->getNativeData();
            return (int) ($data['x-retry-count'] ?? 0);
        }
        return 0;
    }

    private function processMessage(AMQPMessage $msg): void
    {
        $startTime  = microtime(true);
        $eventType  = 'unknown';
        $retryCount = $this->getRetryCount($msg);

        try {
            $payload   = json_decode($msg->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $eventType = $payload['event_type'] ?? 'unknown';

            $this->pipeline->process($payload);

            // Broadcast via Reverb (ShouldBroadcastNow — bypasses queue, sends immediately)
            $country    = $payload['country'] ?? '';
            $employeeId = $payload['data']['employee_id'] ?? 0;

            // Fetch fresh checklist data (cache was warmed by handler) for non-Delete events 
            $checklistData = $eventType !== 'EmployeeDeleted'
                ? $this->checklistService->getChecklist($employeeId, $country)
                : null;

            try {
                Event::dispatch(new EmployeeUpdatedBroadcast(
                    eventType:     $eventType,
                    country:       $country,
                    employeeId:    $employeeId,
                    employeeData:  $payload['data']['employee'] ?? [],
                    eventId:       $payload['event_id'] ?? '',
                    checklistData: $checklistData,
                ));

                $this->metrics->incrementWebsocketBroadcast($eventType);

                Log::info('Broadcast sent via Reverb', [
                    'event_type'  => $eventType,
                    'employee_id' => $employeeId,
                    'country'     => $country,
                    'channels'    => ['employees', "country.{$country}"],
                ]);
            } catch (\Throwable $broadcastError) {
                $this->metrics->incrementWebsocketBroadcastFailure($eventType);

                Log::warning('Broadcast to Reverb failed (non-fatal)', [
                    'event_type'  => $eventType,
                    'employee_id' => $employeeId,
                    'error'       => $broadcastError->getMessage(),
                ]);
            }

            $this->metrics->incrementEventsProcessed($eventType);
            $this->metrics->recordEventProcessingDuration($eventType, microtime(true) - $startTime);

            Log::info('Event fully processed', [
                'event_type'   => $eventType,
                'employee_id'  => $employeeId,
                'country'      => $country,
                'duration_ms'  => round((microtime(true) - $startTime) * 1000, 2),
                'retry_count'  => $retryCount,
            ]);

            $msg->ack();
        } catch (\Throwable $e) {
            Log::error('Failed to process RabbitMQ message', [
                'event_type'  => $eventType,
                'retry_count' => $retryCount,
                'error'       => $e->getMessage(),
            ]);
            $this->metrics->incrementEventProcessingErrors($eventType);

            if ($retryCount < self::MAX_RETRIES) {
                // Exponential backoff: 2^retry seconds (1s, 2s, 4s)
                $delay = (int) (1000 * (2 ** $retryCount)); // ms
                $this->requeueWithDelay($msg, $retryCount + 1, $delay);
                $this->metrics->incrementEventRetry($eventType);
            } else {
                // Max retries exceeded — send to DLQ
                Log::warning('Message exhausted retries, sending to DLQ', [
                    'event_type'  => $eventType,
                    'retry_count' => $retryCount,
                ]);
                $this->metrics->incrementEventDlqRouted($eventType);
                $msg->nack(false, false);
            }
        }
    }

    /**
     * Republishes the message with an incremented x-retry-count header, then
     * acks the original. This avoids double-processing while allowing retry
     * ordering via the broker.
     */
    private function requeueWithDelay(AMQPMessage $msg, int $newRetryCount, int $delayMs): void
    {
        $channel = $msg->getChannel();
        $body    = $msg->getBody();

        $properties = array_merge($msg->get_properties(), [
            'application_headers' => new AMQPTable([
                'x-retry-count' => $newRetryCount,
                'x-retry-delay' => $delayMs,
            ]),
        ]);

        $newMsg = new AMQPMessage($body, $properties);
        $channel->basic_publish($newMsg, self::EXCHANGE, $msg->getRoutingKey());
        $msg->ack(); // ack original before republishing

        Log::info('Message re-queued for retry', [
            'retry_count' => $newRetryCount,
            'delay_ms'    => $delayMs,
        ]);
    }

    private function setupSignalHandlers(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, function (): void {
                Log::info('Received SIGTERM - stopping consumer gracefully');
                $this->shouldStop = true;
            });
            pcntl_signal(SIGINT, function (): void {
                Log::info('Received SIGINT - stopping consumer gracefully');
                $this->shouldStop = true;
            });
        }
    }
}
