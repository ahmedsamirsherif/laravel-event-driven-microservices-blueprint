<?php

declare(strict_types=1);

use App\Domain\Country\Contracts\CountryModuleInterface;
use App\Domain\Country\DEU\DEUModule;
use App\Domain\Country\Shared\SharedColumns;
use App\Domain\Country\Shared\SharedSchemaFields;
use App\Domain\Country\Shared\SharedSteps;
use App\Domain\Country\Shared\SharedWidgets;
use App\Domain\Country\USA\USAModule;
use App\Domain\Shared\Enums\CountryCode;
use App\Infrastructure\Country\CountryClassResolver;
use App\Infrastructure\Country\CountryRegistry;

beforeEach(fn () => CountryClassResolver::clearCache());

// ━━━ Shared Helpers ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('SharedSteps returns correct structure with customizable order', function () {
    $dashboard = SharedSteps::dashboard();
    expect($dashboard)->toBe(['id' => 1, 'key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home', 'path' => '/dashboard', 'order' => 1]);

    $employees = SharedSteps::employees(10);
    expect($employees['id'])->toBe(10)->and($employees['order'])->toBe(10);
});

it('SharedColumns base returns four sortable columns with salary as currency', function () {
    $columns = SharedColumns::base();
    expect($columns)->toHaveCount(4)
        ->and(array_column($columns, 'key'))->toBe(['id', 'name', 'last_name', 'salary']);

    $salary = collect($columns)->firstWhere('key', 'salary');
    expect($salary['format'])->toBe('currency')->and($salary['sortable'])->toBeTrue();
});

it('SharedWidgets employeeCount interpolates country in data_source and channel', function () {
    $widget = SharedWidgets::employeeCount('DEU');
    expect($widget['id'])->toBe('employee_count')
        ->and($widget['type'])->toBe('stat_card')
        ->and($widget['data_source'])->toBe('/api/v1/employees/DEU')
        ->and($widget['channel'])->toBe('country.DEU');
});

it('SharedSchemaFields base returns three required fields of correct types', function () {
    $fields = SharedSchemaFields::base();
    expect($fields)->toHaveCount(3)
        ->and(array_keys($fields))->toBe(['name', 'last_name', 'salary'])
        ->and($fields['name']['type'])->toBe('string')
        ->and($fields['salary']['type'])->toBe('number');
    foreach ($fields as $def) {
        expect($def['required'])->toBeTrue();
    }
});

// ━━━ USAModule ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('USAModule implements interface and returns correct country', function () {
    $module = new USAModule();
    expect($module)->toBeInstanceOf(CountryModuleInterface::class)
        ->and($module->country())->toBe(CountryCode::USA);
});

it('USAModule navigation, columns, widgets, and schema are correctly shaped', function () {
    $m = new USAModule();

    expect(array_column($m->navigationSteps(), 'key'))->toBe(['dashboard', 'employees']);
    expect(array_column($m->tableColumns(), 'key'))->toBe(['id', 'name', 'last_name', 'salary', 'ssn']);

    $ssn = collect($m->tableColumns())->firstWhere('key', 'ssn');
    expect($ssn['format'])->toBe('masked')->and($ssn['sortable'])->toBeFalse();

    $widgetIds = array_column($m->dashboardWidgets(), 'id');
    expect($widgetIds)->toBe(['employee_count', 'average_salary', 'completion_rate']);

    expect(array_keys($m->schemaFields()))->toBe(['name', 'last_name', 'salary', 'ssn', 'address']);
    expect($m->schemaFields()['ssn']['pattern'])->toBe('^\d{3}-\d{2}-\d{4}$');
});

it('USAModule validation and checklist config is correct', function () {
    $m = new USAModule();

    $rules = $m->validationRules();
    expect($rules)->toHaveKeys(['ssn', 'address', 'salary']);

    $messages = $m->validationMessages();
    expect($messages)->toHaveKeys(['ssn.required', 'ssn.regex', 'address.required', 'salary.required']);

    expect($m->requiredFields())->toBe(['salary', 'ssn', 'address']);

    $steps = $m->checklistSteps();
    expect($steps)->toHaveCount(2)
        ->and($steps[0]['label'])->toBe('Compensation')
        ->and($steps[1]['fields'])->toBe(['ssn', 'address']);
});

// ━━━ DEUModule ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('DEUModule implements interface and returns correct country', function () {
    $module = new DEUModule();
    expect($module)->toBeInstanceOf(CountryModuleInterface::class)
        ->and($module->country())->toBe(CountryCode::DEU);
});

it('DEUModule navigation includes documentation step, columns include goal', function () {
    $m = new DEUModule();

    $stepKeys = array_column($m->navigationSteps(), 'key');
    expect($stepKeys)->toBe(['dashboard', 'employees', 'documentation']);

    $doc = collect($m->navigationSteps())->firstWhere('key', 'documentation');
    expect($doc['icon'])->toBe('file-text')->and($doc['path'])->toBe('/documentation');

    expect(array_column($m->tableColumns(), 'key'))->toBe(['id', 'name', 'last_name', 'salary', 'goal']);

    $widgetIds = array_column($m->dashboardWidgets(), 'id');
    expect($widgetIds)->toBe(['employee_count', 'goal_tracking']);

    $goal = collect($m->dashboardWidgets())->firstWhere('id', 'goal_tracking');
    expect($goal['type'])->toBe('progress_list');
});

it('DEUModule schema, validation, and checklist config is correct', function () {
    $m = new DEUModule();

    expect(array_keys($m->schemaFields()))->toBe(['name', 'last_name', 'salary', 'tax_id', 'goal']);

    $rules = $m->validationRules();
    expect($rules)->toHaveKeys(['salary', 'tax_id', 'goal']);

    $messages = $m->validationMessages();
    expect($messages)->toHaveKeys(['tax_id.required', 'tax_id.regex', 'goal.required', 'salary.required']);

    expect($m->requiredFields())->toBe(['salary', 'tax_id', 'goal']);

    $steps = $m->checklistSteps();
    expect($steps)->toHaveCount(2)
        ->and($steps[0]['label'])->toBe('Compensation')
        ->and($steps[1]['label'])->toBe('Tax & Goals')
        ->and($steps[1]['fields'])->toBe(['tax_id', 'goal']);
});

// ━━━ CountryClassResolver ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('resolver resolves, caches, and discovers country modules', function () {
    $usa = CountryClassResolver::resolve('USA', 'Module', CountryModuleInterface::class);
    expect($usa)->toBeInstanceOf(USAModule::class);

    $cached = CountryClassResolver::resolve('USA', 'Module', CountryModuleInterface::class);
    expect($usa)->toBe($cached);

    $modules = CountryClassResolver::discoverAll('Module', CountryModuleInterface::class);
    expect($modules)->toHaveCount(2)->and($modules)->toHaveKeys(['USA', 'DEU']);
});

it('resolver throws for unknown country, tryResolve returns null', function () {
    expect(fn () => CountryClassResolver::resolve('France', 'Module', CountryModuleInterface::class))
        ->toThrow(InvalidArgumentException::class);
    expect(CountryClassResolver::tryResolve('France', 'Module', CountryModuleInterface::class))->toBeNull();
});

it('clearCache forces new instance creation', function () {
    $first = CountryClassResolver::resolve('USA', 'Module', CountryModuleInterface::class);
    CountryClassResolver::clearCache();
    $second = CountryClassResolver::resolve('USA', 'Module', CountryModuleInterface::class);
    expect($first)->not->toBe($second)->and($first)->toEqual($second);
});

// ━━━ CountryRegistry ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

it('registry discovers, resolves, and validates country support', function () {
    $registry = CountryRegistry::discover();

    $countries = $registry->supportedCountries();
    sort($countries);
    expect($countries)->toBe(['DEU', 'USA']);

    expect($registry->for('USA'))->toBeInstanceOf(USAModule::class);
    expect($registry->for('DEU'))->toBeInstanceOf(DEUModule::class);
    expect($registry->supports('USA'))->toBeTrue();
    expect($registry->supports('France'))->toBeFalse();
});

it('registry throws for unsupported country', function () {
    CountryRegistry::discover()->for('France');
})->throws(InvalidArgumentException::class, 'Unsupported country: France');
