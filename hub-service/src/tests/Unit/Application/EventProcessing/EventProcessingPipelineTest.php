<?php

declare(strict_types=1);

use App\Application\EventProcessing\Handlers\EventHandlerInterface;
use App\Application\EventProcessing\Pipeline\EventProcessingPipeline;

it('dispatches to matching handler and skips non-matching', function () {
    $handled = new stdClass();
    $handled->v = false;
    $skipped = new stdClass();
    $skipped->v = false;

    $matching = new class($handled) implements EventHandlerInterface {
        public function __construct(private stdClass $h) {}
        public function supports(array $payload): bool { return ($payload['event_type'] ?? '') === 'EmployeeCreated'; }
        public function handle(array $payload): void { $this->h->v = true; }
    };
    $nonMatching = new class($skipped) implements EventHandlerInterface {
        public function __construct(private stdClass $s) {}
        public function supports(array $payload): bool { return false; }
        public function handle(array $payload): void { $this->s->v = true; }
    };

    $pipeline = new EventProcessingPipeline();
    $pipeline->pipe($nonMatching)->pipe($matching);
    $pipeline->process(['event_type' => 'EmployeeCreated']);

    expect($handled->v)->toBeTrue()->and($skipped->v)->toBeFalse();
});

it('stops at first matching handler and does not double-process', function () {
    $state = new stdClass();
    $state->callCount = 0;

    $make = function () use ($state) {
        return new class($state) implements EventHandlerInterface {
            public function __construct(private stdClass $s) {}
            public function supports(array $payload): bool { return true; }
            public function handle(array $payload): void { $this->s->callCount++; }
        };
    };

    $pipeline = new EventProcessingPipeline();
    $pipeline->pipe($make())->pipe($make());
    $pipeline->process(['event_type' => 'EmployeeCreated']);

    expect($state->callCount)->toBe(1);
});

it('handles unknown events without throwing and supports multiple handler types', function () {
    $pipeline = new EventProcessingPipeline();
    expect(fn () => $pipeline->process(['event_type' => 'UnknownEvent']))->not->toThrow(\Throwable::class);

    $h1 = new class implements EventHandlerInterface {
        public function supports(array $payload): bool { return ($payload['event_type'] ?? '') === 'EmployeeCreated'; }
        public function handle(array $payload): void {}
    };
    $h2 = new class implements EventHandlerInterface {
        public function supports(array $payload): bool { return ($payload['event_type'] ?? '') === 'EmployeeDeleted'; }
        public function handle(array $payload): void {}
    };

    $pipeline->pipe($h1)->pipe($h2);
    expect(fn () => $pipeline->process(['event_type' => 'EmployeeCreated']))->not->toThrow(\Throwable::class);
    expect(fn () => $pipeline->process(['event_type' => 'EmployeeDeleted']))->not->toThrow(\Throwable::class);
});
