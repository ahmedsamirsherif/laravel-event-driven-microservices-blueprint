<?php

declare(strict_types=1);

namespace App\Application\EventProcessing\Handlers;

trait InvalidatesCache
{
    private function invalidateCache(int $employeeId, string $country): void
    {
        $version = (int) cache()->get("employees:{$country}:v", 0);
        cache()->put("employees:{$country}:v", $version + 1, 86400);
        cache()->forget("checklist:{$country}:{$employeeId}");
        cache()->forget("checklist_summary:{$country}");
    }
}
