<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Employee;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition()
    {
        $type = $this->faker->randomElement(['daily_rate', 'hourly']);

        return [
            'employee_code' => strtoupper($this->faker->bothify('E###')),
            'name' => $this->faker->name(),
            'joining_date' => $this->faker->date(),
            'department' => $this->faker->randomElement(['HR','Accounts','Production','Sales']),
            'type' => $type,
            'daily_rate' => $type === 'daily_rate' ? $this->faker->randomFloat(2, 50, 200) : null,
            'hourly_rate' => $type === 'hourly' ? $this->faker->randomFloat(2, 5, 30) : null,
            'hours_per_day' => $type === 'hourly' ? $this->faker->randomFloat(2, 6, 9) : null,
            'is_active' => true,
        ];
    }
}
