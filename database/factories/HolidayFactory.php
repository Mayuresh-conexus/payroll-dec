<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * Default state: a single-day holiday.
     * Use ->spanning($days) for a multi-day one.
     */
    public function definition(): array
    {
        $start = $this->faker->date();

        return [
            'name' => $this->faker->randomElement([
                'New Year\'s Day', 'St Patrick\'s Day', 'Easter Monday',
                'June Bank Holiday', 'August Bank Holiday', 'Christmas Day',
            ]),
            'start_date' => $start,
            'end_date' => $start,
        ];
    }

    /**
     * A holiday running for several consecutive days from start_date.
     */
    public function spanning(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'end_date' => \Carbon\Carbon::parse($attributes['start_date'])->addDays($days - 1)->toDateString(),
        ]);
    }

    /**
     * A holiday pinned to a specific date (or range), which is what most tests want.
     */
    public function on(string $startDate, ?string $endDate = null): static
    {
        return $this->state(fn (): array => [
            'start_date' => $startDate,
            'end_date' => $endDate ?? $startDate,
        ]);
    }
}
