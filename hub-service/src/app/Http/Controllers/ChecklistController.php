<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Checklist\ChecklistService;
use App\Infrastructure\Country\CountryRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly CountryRegistry $registry,
    ) {}

    public function show(Request $request, string $country): JsonResponse
    {
        abort_if(! $this->registry->supports($country), 404, "No checklist configuration for country: {$country}");

        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 15);

        $summary = $this->checklistService->getSummary($country);
        $paginated = $this->checklistService->getPaginatedChecklists($country, $page, $perPage);

        Log::debug('[ChecklistController][show] Checklist served', [
            'country' => $country,
            'page' => $page,
            'per_page' => $perPage,
            'total_employees' => $summary['total_employees'],
        ]);

        return response()->json([
            'data' => [
                'country'             => $country,
                'total_employees'     => $summary['total_employees'],
                'complete_employees'  => $summary['complete_employees'],
                'overall_percentage'  => $summary['overall_percentage'],
                'has_employees'       => $summary['total_employees'] > 0,
                'employee_checklists' => $paginated['checklists'],
            ],
            'meta' => $paginated['pagination'],
        ]);
    }
}
