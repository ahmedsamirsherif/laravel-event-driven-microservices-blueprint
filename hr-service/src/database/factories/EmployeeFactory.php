<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Employee\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'salary' => $this->faker->numberBetween(30000, 120000),
            'country' => 'USA',
            'ssn' => sprintf('%03d-%02d-%04d',
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(10, 99),
                $this->faker->numberBetween(1000, 9999)
            ),
            'address' => $this->faker->address(),
            'goal' => null,
            'tax_id' => null,
            'doc_work_permit' => null,
            'doc_tax_card' => null,
            'doc_health_insurance' => null,
            'doc_social_security' => null,
            'doc_employment_contract' => null,
        ];
    }

    public function usa(): static
    {
        return $this->state([
            'country' => 'USA',
            'ssn' => sprintf('%03d-%02d-%04d',
                $this->faker->numberBetween(100, 999),
                $this->faker->numberBetween(10, 99),
                $this->faker->numberBetween(1000, 9999)
            ),
            'address' => $this->faker->address(),
            'goal' => null,
            'tax_id' => null,
        ]);
    }

    public function deu(): static
    {
        return $this->state([
            'country' => 'DEU',
            'ssn' => null,
            'address' => null,
            'goal' => $this->faker->sentence(),
            'tax_id' => 'DE'.$this->faker->numerify('#########'),
        ]);
    }
}
