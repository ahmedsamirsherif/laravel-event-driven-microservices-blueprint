<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Infrastructure\Country\CountryFieldsRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $registry = app(CountryFieldsRegistry::class);
        $country = $this->input('country');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'salary' => ['required', 'numeric', 'min:0'],
            'country' => ['required', 'string', Rule::in($registry->supportedCountries())],
        ];

        if ($country !== null && $registry->supports($country)) {
            $rules = array_merge($rules, $registry->for($country)->storeRules());
        }

        return $rules;
    }

    public function messages(): array
    {
        $registry = app(CountryFieldsRegistry::class);
        $country = $this->input('country');

        if ($country !== null && $registry->supports($country)) {
            return $registry->for($country)->storeMessages();
        }

        return [];
    }
}
