<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Employee;
use App\Models\EmployeeRate;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmployeeRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_rate_at_returns_effective_rate()
    {
        $emp = Employee::factory()->create([
            'daily_rate' => 100,
        ]);

        EmployeeRate::create([
            'employee_id' => $emp->id,
            'rate_type' => 'daily_rate',
            'amount' => 90,
            'effective_from' => Carbon::parse('2025-12-01')->toDateString(),
            'effective_to' => Carbon::parse('2025-12-31')->toDateString(),
        ]);

        $d = Carbon::parse('2025-12-05');
        $this->assertEquals(90.0, $emp->rateAt($d, 'daily_rate'));

        $d2 = Carbon::parse('2026-02-01');
        // no rate for that date -> fallback to employee.daily_rate
        $this->assertEquals(100.0, $emp->rateAt($d2, 'daily_rate') ?? $emp->daily_rate);
    }
}
