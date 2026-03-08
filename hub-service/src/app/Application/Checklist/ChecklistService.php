<?php

declare(strict_types=1);

namespace App\Application\Checklist;

use App\Domain\Employee\Repositories\EmployeeProjectionRepositoryInterface;
use App\Infrastructure\Country\CountryRegistry;
use App\Infrastructure\Metrics\PrometheusMetricsService;
use Illuminate\Support\Facades\Validator;

/**
 * Pure business logic for computing employee checklist completeness.
 * Caching is handled externally by controllers and event handlers
 * using Laravel's cache() helper directly.
 */
final class ChecklistService
{
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly CountryRegistry $registry,
        private readonly EmployeeProjectionRepositoryInterface $repository,
        private readonly PrometheusMetricsService $metrics,
    ) {}

    /**
     * Get a single employee's checklist, using cache() transparently.
     */
    public function getChecklist(int $employeeId, string $country): array
    {
        return cache()->remember(
            "checklist:{$country}:{$employeeId}",
            self::CACHE_TTL,
            fn () => $this->buildChecklist($employeeId, $country),
        );
    }

    /**
     * Get the country-level summary (total employees, complete count, overall %).
     */
    public function getSummary(string $country): array
    {
        return cache()->remember(
            "checklist_summary:{$country}",
            self::CACHE_TTL,
            fn () => $this->buildSummary($country),
        );
    }

    /**
     * Get paginated checklists for a country.
     * Uses per-employee cache entries where available.
     */
    public function getPaginatedChecklists(string $country, int $page, int $perPage): array
    {
        $paginator = $this->repository->paginateByCountry($country, $page, $perPage);

        $checklists = [];

        foreach ($paginator->items() as $employee) {
            $key = "checklist:{$country}:{$employee->employee_id}";

            $cached = cache()->get($key);

            if ($cached !== null) {
                $this->metrics->incrementCacheHit('checklist');
                $checklists[] = $cached;
                continue;
            }

            $this->metrics->incrementCacheMiss('checklist');

            // Compute + cache using the already-loaded model data (no extra query)
            $checklist = $this->assembleChecklist($employee->employee_id, $country, $employee->toArray());
            cache()->put($key, $checklist, self::CACHE_TTL);
            $checklists[] = $checklist;
        }

        return [
            'checklists' => $checklists,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    /**
     * Assemble a checklist array from in-memory employee data.
     * Public so event handlers can use it for eager cache warming.
     */
    public function assembleChecklist(int $employeeId, string $country, array $employeeData): array
    {
        $completeness = $this->computeCompleteness($employeeData, $country);

        return array_merge(
            [
                'employee_id' => $employeeId,
                'country'     => $country,
                'name'        => $employeeData['name'] ?? null,
                'last_name'   => $employeeData['last_name'] ?? null,
            ],
            $completeness,
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function buildChecklist(int $employeeId, string $country): array
    {
        if (! $this->registry->supports($country)) {
            return $this->emptyChecklist($employeeId, $country);
        }

        $projection = $this->repository->findByEmployeeId($employeeId);

        if (! $projection) {
            return $this->emptyChecklist($employeeId, $country);
        }

        return $this->assembleChecklist($employeeId, $country, $projection->toArray());
    }

    /**
     * Compute completion items, step groupings, and percentage from raw
     * employee data using the country module's declared validation rules
     * and required fields list.
     */
    private function computeCompleteness(array $employeeData, string $country): array
    {
        $module   = $this->registry->for($country);
        $rules    = $module->validationRules();
        $messages = $module->validationMessages();

        $validator = Validator::make($employeeData, $rules, $messages);
        $errors    = $validator->errors();

        // Build flat items map keyed by field name — start with validated fields
        $itemsMap = [];
        foreach (array_keys($rules) as $field) {
            $completed       = ! $errors->has($field);
            $itemsMap[$field] = [
                'field'     => $field,
                'completed' => $completed,
                'label'     => $this->formatLabel($field),
                'message'   => $completed ? null : $errors->first($field),
            ];
        }

        // Add required fields that have no validation rules (e.g. doc_ fields).
        // These are "completed" when present and non-empty.
        foreach ($module->requiredFields() as $field) {
            if (isset($itemsMap[$field])) {
                continue;
            }
            $value     = $employeeData[$field] ?? null;
            $completed = $value !== null && $value !== '';
            $itemsMap[$field] = [
                'field'     => $field,
                'completed' => $completed,
                'label'     => $this->formatLabel($field),
                'message'   => $completed ? null : ucfirst(str_replace('_', ' ', $field)) . ' is required.',
            ];
        }

        // Group items into checklist steps defined by the country module
        $steps = [];
        foreach ($module->checklistSteps() as $step) {
            $stepItems     = [];
            $stepCompleted = 0;

            foreach ($step['fields'] as $fieldName) {
                if (! isset($itemsMap[$fieldName])) {
                    continue;
                }
                $stepItems[] = $itemsMap[$fieldName];
                if ($itemsMap[$fieldName]['completed']) {
                    $stepCompleted++;
                }
            }

            $stepTotal = count($stepItems);
            $steps[]   = [
                'id'                         => $step['id'],
                'label'                      => $step['label'],
                'fields'                     => $stepItems,
                'step_completion_percentage' => $stepTotal > 0
                    ? (int) round(($stepCompleted / $stepTotal) * 100)
                    : 0,
            ];
        }

        $items         = array_values($itemsMap);
        $totalFields   = count($items);
        $doneFields    = count(array_filter($items, fn ($i) => $i['completed']));
        $overallPct    = $totalFields > 0 ? (int) round(($doneFields / $totalFields) * 100) : 0;

        return [
            'items'                 => $items,
            'steps'                 => $steps,
            'completion_percentage' => $overallPct,
        ];
    }

    /**
     * Build the country-level summary without hitting the DB per employee.
     */
    private function buildSummary(string $country): array
    {
        $employees = $this->repository->allByCountry($country);
        $total     = $employees->count();
        $complete  = 0;

        foreach ($employees as $employee) {
            $completeness = $this->computeCompleteness($employee->toArray(), $country);
            if ($completeness['completion_percentage'] >= 100) {
                $complete++;
            }
        }

        return [
            'total_employees'    => $total,
            'complete_employees' => $complete,
            'overall_percentage' => $total > 0 ? round(($complete / $total) * 100, 2) : 0,
        ];
    }

    private function emptyChecklist(int $employeeId, string $country): array
    {
        return [
            'employee_id'           => $employeeId,
            'country'               => $country,
            'items'                 => [],
            'steps'                 => [],
            'completion_percentage' => 0,
        ];
    }

    private function formatLabel(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }
}
