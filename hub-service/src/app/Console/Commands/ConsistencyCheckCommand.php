<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConsistencyCheckCommand extends Command
{
    protected $signature = 'consistency:check
        {--fix : Automatically remove orphaned Hub projections}
        {--hr-url= : HR service base URL (default: from config)}';

    protected $description = 'Check data consistency between HR and Hub services — detects orphaned projections';

    public function handle(): int
    {
        $this->info('Consistency Check: HR ↔ Hub');
        $this->line(str_repeat('─', 60));

        $hrBaseUrl = $this->option('hr-url') ?: config('services.hr.url', 'http://hr-service');

        // 1. Get Hub projection count
        $hubTotal = EmployeeProjection::count();
        $this->line("Hub projections: {$hubTotal}");

        // 2. Try to reach HR service
        try {
            $response = Http::timeout(5)->get("{$hrBaseUrl}/api/v1/employees", ['per_page' => 1]);

            if (! $response->successful()) {
                $this->error("Cannot reach HR service at {$hrBaseUrl} (HTTP {$response->status()})");

                return Command::FAILURE;
            }

            $hrData = $response->json();
            $hrTotal = $hrData['meta']['total'] ?? $hrData['total'] ?? null;

            if ($hrTotal === null) {
                $this->error('Cannot determine HR employee count from response');

                return Command::FAILURE;
            }

            $this->line("HR employees:    {$hrTotal}");
        } catch (\Exception $e) {
            $this->error("Cannot connect to HR service: {$e->getMessage()}");
            $this->line('');
            $this->warn('Falling back to Hub-only analysis...');

            return $this->hubOnlyCheck();
        }

        $this->newLine();

        // 3. Compare counts
        if ($hrTotal === 0 && $hubTotal > 0) {
            $this->error("INCONSISTENCY DETECTED: HR has 0 employees but Hub has {$hubTotal} projections");
            $this->warn('   Likely cause: "make hr-fresh" was run without "make hub-fresh"');
            $this->warn('   Fix: Run "make fresh" to reset both services, or "make hub-fresh" to clear Hub');

            if ($this->option('fix')) {
                $this->fixOrphanedProjections();
            }

            Log::warning('[ConsistencyCheckCommand][handle] Consistency check failed', [
                'hr_total' => 0,
                'hub_total' => $hubTotal,
            ]);

            return Command::FAILURE;
        }

        if ($hrTotal > 0 && $hubTotal === 0) {
            $this->warn("HR has {$hrTotal} employees but Hub has 0 projections");
            $this->warn('   Events may not have propagated yet, or Hub was freshly reset');
            $this->warn('   Fix: Run "make replay-events" to rebuild projections from event log');

            return Command::FAILURE;
        }

        if ($hrTotal !== $hubTotal) {
            $this->warn("Count mismatch: HR has {$hrTotal} employees, Hub has {$hubTotal} projections");
            $this->warn('   Some events may still be in the queue or failed processing');
            $this->line('   Run "php artisan events:stats" to check for failed events');

            return Command::FAILURE;
        }

        $this->info("Consistent: HR ({$hrTotal}) and Hub ({$hubTotal}) are in sync");

        // 4. Spot-check: verify each Hub projection has a matching HR employee
        $orphans = $this->findOrphanedProjections($hrBaseUrl);
        if (count($orphans) > 0) {
            $this->warn("Found ".count($orphans).' orphaned Hub projections (no matching HR employee):');
            $this->table(
                ['Projection ID', 'Employee ID', 'Name', 'Country'],
                collect($orphans)->map(fn ($p) => [$p->id, $p->employee_id, "{$p->name} {$p->last_name}", $p->country])->toArray()
            );

            if ($this->option('fix')) {
                $this->fixOrphanedProjections($orphans);
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function hubOnlyCheck(): int
    {
        $hubTotal = EmployeeProjection::count();

        if ($hubTotal === 0) {
            $this->info('Hub has 0 projections — nothing to check');

            return Command::SUCCESS;
        }

        $this->line("Hub has {$hubTotal} projections but HR service is unreachable");
        $this->warn('Cannot verify consistency without HR service access');

        return Command::FAILURE;
    }

    private function findOrphanedProjections(string $hrBaseUrl): array
    {
        $orphans = [];
        $projections = EmployeeProjection::all();

        foreach ($projections as $projection) {
            try {
                $response = Http::timeout(3)->get("{$hrBaseUrl}/api/v1/employees/{$projection->employee_id}");

                if ($response->status() === 404) {
                    $orphans[] = $projection;
                }
            } catch (\Exception) {
                // If we can't reach HR, skip the spot-check
                break;
            }
        }

        return $orphans;
    }

    private function fixOrphanedProjections(?array $orphans = null): void
    {
        if ($orphans === null) {
            $count = EmployeeProjection::count();
            $this->warn("Removing all {$count} orphaned projections...");
            EmployeeProjection::truncate();
            $this->info("Removed {$count} orphaned projections");

            return;
        }

        foreach ($orphans as $projection) {
            $projection->delete();
            $this->line("  Removed projection for employee #{$projection->employee_id} ({$projection->name} {$projection->last_name})");
        }

        $this->info('Removed '.count($orphans).' orphaned projections');
    }
}
