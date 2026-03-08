<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Employee\Models\EmployeeProjection;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EmployeeProjection> */
class EmployeeProjectionFactory extends Factory
{
    protected $model = EmployeeProjection::class;

    public function definition(): array
    {
        return [
            'employee_id' => $this->faker->unique()->numberBetween(1, 99999),
            'name'        => $this->faker->firstName(),
            'last_name'   => $this->faker->lastName(),
            'salary'      => $this->faker->numberBetween(30000, 120000),
            'country'     => 'USA',
            'ssn'         => sprintf('%03d-%02d-%04d',
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(10, 99),
                $this->faker->numberBetween(1000, 9999)
            ),
            'address' => $this->faker->address(),
            'goal'    => null,
            'tax_id'  => null,
            'raw_data' => [],
        ];
    }

    public function usa(): static
    {
        return $this->state([
            'country' => 'USA',
            'ssn'     => sprintf('%03d-%02d-%04d',
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(10, 99),
                $this->faker->numberBetween(1000, 9999)
            ),
            'address' => $this->faker->address(),
            'goal'    => null,
            'tax_id'  => null,
        ]);
    }

    public function deu(): static
    {
        return $this->state([
            'country' => 'DEU',
            'ssn'     => null,
            'address' => null,
            'goal'    => $this->faker->sentence(),
            'tax_id'  => 'DE'.$this->faker->numerify('#########'),
        ]);
    }
}
