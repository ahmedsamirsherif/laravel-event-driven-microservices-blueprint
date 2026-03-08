<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Employee\Repositories\EmployeeRepositoryInterface;
use App\Infrastructure\Country\CountryFieldsRegistry;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $registry = app(CountryFieldsRegistry::class);
        $rules = [
            'name'                    => ['sometimes', 'string', 'max:255'],
            'last_name'               => ['sometimes', 'string', 'max:255'],
            'salary'                  => ['sometimes', 'numeric', 'min:0'],
        ];

        $country = $this->employeeCountry();

        if ($country !== null && $registry->supports($country)) {
            $rules = array_merge($rules, $registry->for($country)->updateRules());
        }

        return $rules;
    }

    public function messages(): array
    {
        $registry = app(CountryFieldsRegistry::class);
        $country = $this->employeeCountry();

        if ($country !== null && $registry->supports($country)) {
            return $registry->for($country)->storeMessages();
        }

        return [];
    }

    private function employeeCountry(): ?string
    {
        $employeeId = $this->routeEmployeeId();

        if ($employeeId === null) {
            return null;
        }

        return app(EmployeeRepositoryInterface::class)->findOrFail($employeeId)->country;
    }

    private function routeEmployeeId(): ?int
    {
        $route = $this->route();

        if ($route === null) {
            return null;
        }

        $parameter = $route->parameter('employee')
            ?? $route->parameter('id')
            ?? (array_values($route->parameters())[0] ?? null);

        if (is_object($parameter) && isset($parameter->id)) {
            return (int) $parameter->id;
        }

        return is_numeric($parameter) ? (int) $parameter : null;
    }
}
