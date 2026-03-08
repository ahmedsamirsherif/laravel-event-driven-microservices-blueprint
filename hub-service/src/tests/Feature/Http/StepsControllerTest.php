<?php

declare(strict_types=1);

it('returns USA steps with dashboard and employees in correct order', function () {
    $response = $this->getJson('/api/v1/steps/USA');
    $response->assertOk()->assertJsonPath('meta.country', 'USA')
        ->assertJsonStructure(['data' => [['id', 'key', 'label', 'icon', 'path', 'order']], 'meta' => ['country', 'label']]);

    $steps = $response->json('data');
    expect($steps)->toHaveCount(2);
    expect($steps[0]['key'])->toBe('dashboard')->and($steps[1]['key'])->toBe('employees');
    expect(array_column($steps, 'key'))->not->toContain('documentation');
});

it('returns DEU steps with documentation and more steps than USA', function () {
    $response = $this->getJson('/api/v1/steps/DEU');
    $response->assertOk()->assertJsonPath('meta.country', 'DEU');

    $steps = $response->json('data');
    expect($steps)->toHaveCount(3);
    expect(array_column($steps, 'key'))->toContain('documentation');
});

it('returns 404 for unsupported country codes', function () {
    $this->getJson('/api/v1/steps/France')->assertNotFound()->assertJson(['error' => ['code' => 'NOT_FOUND']]);
    $this->getJson('/api/v1/steps/XYZ')->assertNotFound();
});
