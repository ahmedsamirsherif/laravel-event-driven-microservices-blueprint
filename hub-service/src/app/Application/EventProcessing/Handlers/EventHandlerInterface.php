<?php

declare(strict_types=1);

namespace App\Application\EventProcessing\Handlers;

interface EventHandlerInterface
{
    public function supports(array $payload): bool;
    public function handle(array $payload): void;
}
