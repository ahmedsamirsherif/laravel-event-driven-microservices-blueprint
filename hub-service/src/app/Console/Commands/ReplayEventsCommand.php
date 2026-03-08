<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\EventProcessing\Models\EventLog;
use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;
use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use Illuminate\Console\Command;

class ReplayEventsCommand extends Command
{
    protected $signature = 'events:replay
                            {--country=  : Country to replay events for (USA|DEU)}
                            {--from=     : Start datetime (ISO 8601)}
                            {--to=       : End datetime (ISO 8601)}
                            {--force     : Skip confirmation prompt}
                            {--dry-run   : Preview events to replay without processing them}';

    protected $description = 'Replay events from event_log to rebuild projections and caches';

    public function handle(
        EmployeeProjectionRepositoryInterface $repository,
        EventProcessingPipeline $pipeline,
    ): int {
        $country = $this->option('country');
        $from    = $this->option('from');
        $to      = $this->option('to');
        $dryRun  = $this->option('dry-run');

        $this->info('Events Replay' . ($dryRun ? ' [DRY RUN — no changes will be made]' : ''));
        $this->line('Country: ' . ($country ?? 'ALL'));
        $this->line('From: '    . ($from ?? 'beginning'));
        $this->line('To: '      . ($to   ?? 'now'));

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Proceed with replay?', true)) {
            $this->warn('Replay cancelled.');
            return Command::FAILURE;
        }

        // Try replaying from event_log if it has data
        $query = EventLog::query()->orderBy('received_at');

        if ($country) {
            $query->where('country', $country);
        }
        if ($from) {
            $query->where('received_at', '>=', $from);
        }
        if ($to) {
            $query->where('received_at', '<=', $to);
        }

        $events = $query->get();

        if ($events->isNotEmpty()) {
            if ($dryRun) {
                $this->info("[DRY RUN] Would replay {$events->count()} events:");
                $rows = $events->map(fn ($e) => [
                    substr($e->event_id, 0, 8) . '...',
                    $e->event_type,
                    $e->country,
                    $e->received_at?->toDateTimeString() ?? '-',
                ])->toArray();
                $this->table(['Event ID', 'Type', 'Country', 'Received At'], $rows);
                $this->info('Dry run complete — no changes made.');
                return Command::SUCCESS;
            }

            $bar = $this->output->createProgressBar($events->count());
            $bar->start();

            foreach ($events as $event) {
                try {
                    $pipeline->process($event->payload);
                } catch (\Throwable $e) {
                    $this->warn("Failed to replay event {$event->event_id}: {$e->getMessage()}");
                }
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Replayed {$events->count()} events from event_log.");
        } else {
            // Fall back to cache rebuild from current projections
            $countries = $country ? [$country] : ['USA', 'DEU'];
            foreach ($countries as $c) {
                $employees = $repository->allByCountry($c);
                $this->info("Invalidating checklist cache for {$c}: {$employees->count()} employees");

                foreach ($employees as $emp) {
                    cache()->forget("checklist:{$c}:{$emp->employee_id}");
                }

                // Bump employee list version + clear summary
                $v = (int) cache()->get("employees:{$c}:v", 0);
                cache()->put("employees:{$c}:v", $v + 1, 86400);
                cache()->forget("checklist_summary:{$c}");
            }
        }

        $this->info('Replay complete.');
        return Command::SUCCESS;
    }
}
