<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Leave;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    /**
     * Default state: a single day of leave for a new daily-rate employee.
     * Use ->on($start, $end) to pin it to specific dates.
     */
    public function definition(): array
    {
        $start = $this->faker->date();

        return [
            'employee_id' => Employee::factory(),
            'start_date' => $start,
            'end_date' => $start,
            'hours_per_day' => null,
            'reason' => $this->faker->randomElement(['Annual leave', 'Family', 'Sick', null]),
        ];
    }

    /**
     * Leave pinned to a specific date (or range), which is what most tests want.
     */
    public function on(string $startDate, ?string $endDate = null): static
    {
        return $this->state(fn (): array => [
            'start_date' => $startDate,
            'end_date' => $endDate ?? $startDate,
        ]);
    }

    /**
     * Leave for a named employee, taking their standard day for hourly staff.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (): array => [
            'employee_id' => $employee->id,
            'hours_per_day' => $employee->type === 'hourly' ? $employee->hours_per_day : null,
        ]);
    }
}
