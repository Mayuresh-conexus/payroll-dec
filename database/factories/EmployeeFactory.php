<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * Default state: daily_rate employee.
     * Use ->hourly() state for hourly employees.
     */
    public function definition(): array
    {
        return [
            'employee_code' => strtoupper($this->faker->unique()->bothify('E###')),
            'name'          => $this->faker->name(),
            'joining_date'  => $this->faker->date(),
            'department'    => $this->faker->randomElement(['HR', 'Accounts', 'Production', 'Sales']),
            'type'          => 'daily_rate',
            'daily_rate'    => $this->faker->randomFloat(2, 50, 200),
            'hourly_rate'   => null,
            'hours_per_day' => null,
            'is_active'     => true,
        ];
    }

    /**
     * Hourly employee state.
     */
    public function hourly(): static
    {
        return $this->state(fn () => [
            'type'          => 'hourly',
            'daily_rate'    => null,
            'hourly_rate'   => $this->faker->randomFloat(2, 5, 30),
            'hours_per_day' => $this->faker->randomFloat(2, 6, 9),
        ]);
    }
}
