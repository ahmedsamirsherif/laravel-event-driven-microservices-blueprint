<?php

declare(strict_types=1);

use App\Application\Employee\Listeners\PublishEmployeeEventToRabbitMQ;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(fn () => Event::listen(PublishEmployeeEventToRabbitMQ::class, fn () => null));

it('EmployeeUpdated event captures changed fields accurately', function () {
    $resp = $this->postJson('/api/v1/employees', [
        'name' => 'Field', 'last_name' => 'Changed', 'salary' => 50000,
        'country' => 'USA', 'ssn' => '222-33-4444', 'address' => '222 St',
    ]);
    $id = $resp->json('data.id');

    $capturedFields = null;
    Event::listen(\App\Domain\Employee\Events\EmployeeUpdated::class, function ($e) use (&$capturedFields) {
        $capturedFields = $e->changedFields;
    });

    $this->putJson("/api/v1/employees/{$id}", ['salary' => 99000])->assertOk();
    expect($capturedFields)->toContain('salary')->and($capturedFields)->not->toContain('name');
});

it('EmployeeDeleted event contains the employee snapshot', function () {
    $resp = $this->postJson('/api/v1/employees', [
        'name' => 'Snap', 'last_name' => 'Shot', 'salary' => 60000,
        'country' => 'USA', 'ssn' => '444-55-6666', 'address' => '444 St',
    ]);
    $id = $resp->json('data.id');

    $captured = null;
    Event::listen(\App\Domain\Employee\Events\EmployeeDeleted::class, function ($e) use (&$captured) {
        $captured = $e->employee;
    });

    $this->deleteJson("/api/v1/employees/{$id}")->assertNoContent();
    expect($captured)->not->toBeNull()
        ->and($captured->id)->toBe($id)
        ->and($captured->name)->toBe('Snap');
});
