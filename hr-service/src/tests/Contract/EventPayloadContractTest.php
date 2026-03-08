<?php

uses()->group('contract');

it('contract schema defines all required fields and DEU doc fields as nullable', function () {
    $schemaPath = base_path('../../contracts/employee-event.schema.json');
    expect(file_exists($schemaPath))->toBeTrue();

    $schema = json_decode(file_get_contents($schemaPath), true);
    expect($schema['required'])->toContain('event_type', 'event_id', 'timestamp', 'country', 'schema_version', 'data');

    $employeeProps = $schema['properties']['data']['properties']['employee']['properties'] ?? [];
    foreach (['doc_work_permit', 'doc_tax_card', 'doc_employment_contract'] as $field) {
        expect($employeeProps)->toHaveKey($field);
        expect($employeeProps[$field]['type'])->toContain('null');
    }
});
