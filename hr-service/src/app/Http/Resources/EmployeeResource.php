<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Infrastructure\Country\CountryFieldsRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $base = [
            'id' => $this->id,
            'name' => $this->name,
            'last_name' => $this->last_name,
            'salary' => $this->salary,
            'country' => $this->country,
        ];

        $registry = app(CountryFieldsRegistry::class);

        if ($this->country !== null && $registry->supports($this->country)) {
            $base = array_merge($base, $registry->for($this->country)->resourceFields($this->resource));
        }

        $base['created_at'] = $this->created_at?->toIso8601String();
        $base['updated_at'] = $this->updated_at?->toIso8601String();

        return $base;
    }
}
