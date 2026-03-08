<?php

declare(strict_types=1);

it('returns full USA schema with correct fields, widgets, and columns', function () {
    $response = $this->getJson('/api/v1/schema/USA');
    $response->assertOk()->assertJsonPath('data.country', 'USA')
        ->assertJsonStructure(['data' => ['country', 'form_fields', 'widgets', 'columns']]);

    $formFields = $response->json('data.form_fields');
    expect($formFields)->toHaveKey('ssn')->and($formFields)->toHaveKey('address');
    expect($formFields['ssn']['pattern'])->toBe('^\d{3}-\d{2}-\d{4}$');

    $widgetIds = array_column($response->json('data.widgets'), 'id');
    expect($widgetIds)->toContain('employee_count')->and($widgetIds)->toContain('average_salary')->and($widgetIds)->toContain('completion_rate');

    $columnKeys = array_column($response->json('data.columns'), 'key');
    expect($columnKeys)->toContain('ssn');
});

it('returns full DEU schema with correct fields, widgets, and columns', function () {
    $response = $this->getJson('/api/v1/schema/DEU');
    $response->assertOk()->assertJsonPath('data.country', 'DEU');

    $formFields = $response->json('data.form_fields');
    expect($formFields)->toHaveKey('tax_id')->and($formFields)->toHaveKey('goal');
    expect($formFields['tax_id']['required'])->toBeTrue();

    $widgetIds = array_column($response->json('data.widgets'), 'id');
    expect($widgetIds)->toContain('goal_tracking')->and($widgetIds)->not->toContain('completion_rate');

    $columnKeys = array_column($response->json('data.columns'), 'key');
    expect($columnKeys)->toContain('goal')->and($columnKeys)->not->toContain('ssn');
});

it('neither country schema includes document URL fields', function () {
    $docFields = ['doc_work_permit', 'doc_tax_card', 'doc_health_insurance', 'doc_social_security', 'doc_employment_contract'];
    foreach (['USA', 'DEU'] as $country) {
        $formFields = $this->getJson("/api/v1/schema/{$country}")->json('data.form_fields');
        foreach ($docFields as $field) {
            expect($formFields)->not->toHaveKey($field);
        }
    }
});

it('returns 404 for unsupported country', function () {
    $this->getJson('/api/v1/schema/France')->assertNotFound()->assertJson(['error' => ['code' => 'NOT_FOUND']]);
});
