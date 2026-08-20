<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedDemo(): void
    {
        $this->seed(DemoDataSeeder::class);
    }

    public function test_it_seeds_a_full_payroll_history(): void
    {
        $this->seedDemo();

        $this->assertSame(10, Employee::withoutTrashed()->count());
        $this->assertSame(10, Holiday::count(), 'the Irish calendar for the year');
        $this->assertGreaterThan(0, Leave::count());
        $this->assertSame(DemoDataSeeder::WEEKS_OF_HISTORY, PayrollRun::where('period_type', 'weekly')->count(), 'one run per week of history');

        // Every employee is paid in every week, so the payroll table is never sparse.
        $this->assertSame(10 * DemoDataSeeder::WEEKS_OF_HISTORY, PayrollItem::count());
    }

    public function test_the_seeded_rates_are_realistic(): void
    {
        // Guards against the placeholder figures this replaced, where a day's work
        // was priced at four figures.
        $this->seedDemo();

        foreach (Employee::where('type', 'daily_rate')->get() as $employee) {
            $this->assertGreaterThanOrEqual(100, (float) $employee->daily_rate);
            $this->assertLessThanOrEqual(250, (float) $employee->daily_rate, "{$employee->name} is priced like a contractor");
        }

        foreach (Employee::where('type', 'hourly')->get() as $employee) {
            $this->assertGreaterThanOrEqual(12, (float) $employee->hourly_rate);
            $this->assertLessThanOrEqual(35, (float) $employee->hourly_rate, "{$employee->name} is priced like a contractor");
        }
    }

    public function test_it_produces_a_mix_of_daily_and_hourly_staff_with_a_leaver(): void
    {
        $this->seedDemo();

        $this->assertGreaterThan(1, Employee::where('type', 'daily_rate')->count());
        $this->assertGreaterThan(1, Employee::where('type', 'hourly')->count());
        $this->assertSame(1, Employee::where('is_active', false)->whereNotNull('deactivated_at')->count());
    }

    public function test_every_employee_has_a_working_week_so_no_entitlement_is_assumed(): void
    {
        // The balances page flags employees with no working week set, because their
        // leave entitlement is then a guess. The demo should not be showing that.
        $this->seedDemo();

        $this->assertSame(0, Employee::whereNull('weekly_active_days')->count());
        $this->assertSame(0, Employee::whereNull('joining_date')->count());
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seedDemo();
        $this->seedDemo();

        $this->assertSame(10, Employee::count());
        $this->assertSame(10, Holiday::count());
        $this->assertSame(4, Leave::count());
        $this->assertSame(6, \App\Models\EmployeeRate::count(), 'two history entries for each of three raises');
        $this->assertSame(10 * DemoDataSeeder::WEEKS_OF_HISTORY, PayrollItem::count());
    }

    public function test_the_seeded_weeks_reconcile(): void
    {
        // Cash and bank must account for gross on every row the demo produces.
        $this->seedDemo();

        foreach (PayrollItem::all() as $item) {
            $this->assertEqualsWithDelta(
                (float) $item->weekly_amount + (float) $item->bh_cash,
                (float) $item->gross_amount,
                0.01,
                "gross does not match the parts for item {$item->id}"
            );
        }
    }

    public function test_the_payroll_page_renders_the_seeded_weeks(): void
    {
        $this->seedDemo();
        $this->actingAs($this->admin());

        $run = PayrollRun::where('period_type', 'weekly')->orderByDesc('year')->orderByDesc('week_number')->firstOrFail();

        $rows = $this->get("/payroll?year={$run->year}&week={$run->week_number}")
            ->assertOk()
            ->viewData('rows');

        $this->assertGreaterThan(0, $rows->count());
        $this->assertGreaterThan(0, $rows->sum('weekly_amount'), 'the week actually pays something');
    }

    public function test_a_settlement_week_carries_a_bank_holiday_premium(): void
    {
        // The demo is only worth showing if a bank holiday actually lands in it.
        $this->seedDemo();

        $this->assertGreaterThan(
            0,
            (float) PayrollItem::sum('bh_amount'),
            'no bank holiday fell inside the seeded history — the BH columns would never appear'
        );
    }

    public function test_the_seeded_leave_is_paid(): void
    {
        $this->seedDemo();

        $this->assertGreaterThan(0, (float) PayrollItem::sum('leave_amount'), 'seeded leave reached payroll');
    }

    public function test_the_supervisor_can_sign_in_and_sees_only_their_crew(): void
    {
        $this->seedDemo();

        $supervisor = User::where('email', 'supervisor@example.com')->firstOrFail();

        $this->assertSame('manager', $supervisor->role);
        $this->assertSame(4, $supervisor->assignedEmployees()->count());

        $this->actingAs($supervisor)->get('/attendance')->assertOk();
    }

    public function test_the_leave_and_employee_pages_render_for_the_seeded_data(): void
    {
        $this->seedDemo();
        $this->actingAs($this->admin());

        $this->get('/leaves')->assertOk()->assertViewHas('balances');
        $this->get('/employees')->assertOk();
        $this->get('/holidays')->assertOk();

        $employee = Employee::where('employee_code', 'WWF01')->firstOrFail();
        $this->get("/employees/{$employee->id}")->assertOk()->assertViewHas('leaveBalance');
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        // The seeder rewrites employees and attendance, so it must never be the
        // thing that runs against real books. Invoked directly rather than through
        // db:seed, which has a confirmation prompt of its own in production.
        app()->detectEnvironment(fn (): string => 'production');

        app(DemoDataSeeder::class)->run();

        $this->assertSame(0, Employee::count());
    }
}
