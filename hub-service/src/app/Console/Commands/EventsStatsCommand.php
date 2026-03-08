<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\EventProcessing\Models\EventLog;
use App\Domain\EventProcessing\Models\ProcessedEvent;
use Illuminate\Console\Command;

class EventsStatsCommand extends Command
{
    protected $signature = 'events:stats {--country= : Filter by country}';
    protected $description = 'Show event processing statistics';

    public function handle(): int
    {
        $country = $this->option('country');

        $this->info('Event Processing Statistics');
        $this->line(str_repeat('─', 60));

        $query = EventLog::query();
        if ($country) {
            $query->where('country', $country);
        }

        $total     = (clone $query)->count();
        $processed = (clone $query)->where('status', 'processed')->count();
        $failed    = (clone $query)->where('status', 'failed')->count();
        $received  = (clone $query)->where('status', 'received')->count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total events logged',    $total],
                ['Processed successfully', $processed],
                ['Failed',                 $failed],
                ['In progress (received)', $received],
                ['Idempotency dedup',      ProcessedEvent::count()],
            ]
        );

        // Per country breakdown
        $byCountry = EventLog::query()
            ->selectRaw('country, status, count(*) as cnt')
            ->groupBy('country', 'status')
            ->orderBy('country')
            ->get();

        if ($byCountry->isNotEmpty()) {
            $this->newLine();
            $this->info('Breakdown by Country & Status:');
            $rows = $byCountry->map(fn ($r) => [$r->country, $r->status, $r->cnt])->toArray();
            $this->table(['Country', 'Status', 'Count'], $rows);
        }

        // Per event type
        $byType = EventLog::query()
            ->selectRaw('event_type, count(*) as cnt')
            ->groupBy('event_type')
            ->orderByDesc('cnt')
            ->get();

        if ($byType->isNotEmpty()) {
            $this->newLine();
            $this->info('Breakdown by Event Type:');
            $rows = $byType->map(fn ($r) => [$r->event_type, $r->cnt])->toArray();
            $this->table(['Event Type', 'Count'], $rows);
        }

        // Recent failures
        $recent = EventLog::where('status', 'failed')
            ->latest('received_at')
            ->take(5)
            ->get(['event_id', 'event_type', 'country', 'error_message', 'received_at']);

        if ($recent->isNotEmpty()) {
            $this->newLine();
            $this->warn('Recent Failures (last 5):');
            $rows = $recent->map(fn ($r) => [
                substr($r->event_id, 0, 8) . '...',
                $r->event_type,
                $r->country,
                substr($r->error_message ?? '', 0, 50),
                $r->received_at?->diffForHumans() ?? '-',
            ])->toArray();
            $this->table(['Event ID', 'Type', 'Country', 'Error', 'When'], $rows);
        }

        return Command::SUCCESS;
    }
}
