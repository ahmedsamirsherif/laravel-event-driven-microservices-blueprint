<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;
use App\Domain\EventProcessing\Models\EventLog;
use App\Domain\EventProcessing\Models\ProcessedEvent;
use Illuminate\Console\Command;

class RetryFailedEventsCommand extends Command
{
    protected $signature = 'events:retry-failed {--limit=100 : Max events to retry}';
    protected $description = 'Retry events that failed during processing';

    public function handle(EventProcessingPipeline $pipeline): int
    {
        $limit = (int) $this->option('limit');

        $failedEvents = EventLog::where('status', 'failed')
            ->whereNotNull('payload')
            ->limit($limit)
            ->orderBy('received_at')
            ->get();

        if ($failedEvents->isEmpty()) {
            $this->info('No failed events to retry.');
            $managementUrl = config('rabbitmq.management_url', 'http://localhost:15672');
            $this->line("Check DLQ in RabbitMQ Management UI: {$managementUrl}");
            $this->line('DLQ name: hub_employee_events_dlq');
            return Command::SUCCESS;
        }

        $this->info("Retrying {$failedEvents->count()} failed events...");
        $bar = $this->output->createProgressBar($failedEvents->count());
        $bar->start();

        $retried  = 0;
        $failures = 0;

        foreach ($failedEvents as $event) {
            // Clear old processed event to allow re-processing
            ProcessedEvent::where('event_id', $event->event_id)->delete();

            try {
                $pipeline->process($event->payload);
                $retried++;
            } catch (\Throwable $e) {
                $this->warn("Retry failed for {$event->event_id}: {$e->getMessage()}");
                $failures++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Retry complete: {$retried} succeeded, {$failures} still failed.");

        return $failures > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
