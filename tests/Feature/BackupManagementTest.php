<?php

namespace Tests\Feature;

use App\Models\BackupSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Access control ──────────────────────────────────────────────────────

    public function test_manager_cannot_access_backups_index(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->get('/backups')->assertForbidden();
    }

    public function test_manager_cannot_trigger_backup_run(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->post('/backups/run')->assertForbidden();
    }

    public function test_manager_cannot_restore_backup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->post('/backups/manual_test.sql.gz/restore')->assertForbidden();
    }

    public function test_manager_cannot_delete_backup(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->delete('/backups/manual_test.sql.gz')->assertForbidden();
    }

    public function test_manager_cannot_update_schedule(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->put('/backups/schedule', ['enabled' => 1, 'frequency' => 'daily', 'run_at' => '02:00'])
            ->assertForbidden();
    }

    // ── Index ────────────────────────────────────────────────────────────────

    public function test_admin_can_view_backups_index(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $this->get('/backups')
            ->assertOk()
            ->assertViewHas('backups')
            ->assertViewHas('schedule')
            ->assertViewHas('history');
    }

    // ── Download ─────────────────────────────────────────────────────────────

    public function test_download_rejects_nonexistent_filename(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $this->get('/backups/manual_20260101_000000.sql.gz/download')->assertNotFound();
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_destroy_rejects_invalid_filename_format(): void
    {
        // Passes the (permissive) route regex but fails the service's stricter
        // allow-list — defense-in-depth against path-traversal-style filenames.
        Storage::fake('local');
        $this->actingAs($this->admin());

        $this->delete('/backups/evil..sql.gz')->assertSessionHasErrors('delete');
    }

    public function test_destroy_returns_error_for_nonexistent_backup(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $this->delete('/backups/manual_20260101_000000.sql.gz')->assertSessionHasErrors('delete');
    }

    // ── Restore ──────────────────────────────────────────────────────────────

    public function test_restore_rejects_nonexistent_backup(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $this->post('/backups/manual_20260101_000000.sql.gz/restore', ['confirmation' => 'manual_20260101_000000.sql.gz'])
            ->assertNotFound();
    }

    public function test_restore_requires_matching_confirmation_text(): void
    {
        // The confirmation-mismatch check short-circuits before Artisan::call('backup:restore', ...)
        // is ever reached, so this is safe to run under the sqlite test database.
        Storage::fake('local');
        Storage::disk('local')->put('backups/manual_test.sql.gz', 'fake-dump-content');
        $this->actingAs($this->admin());

        $this->post('/backups/manual_test.sql.gz/restore', ['confirmation' => 'not-the-filename'])
            ->assertSessionHasErrors('confirmation');
    }

    public function test_runtime_tables_are_excluded_from_backups(): void
    {
        // Regression guard: including `sessions` meant a restore swapped the session
        // store mid-request, so the page's CSRF token stopped matching and the next
        // restore was rejected with a 419 that looked like "restore is broken".
        $excluded = config('backup.excluded_tables');

        $this->assertContains('sessions', $excluded);
        $this->assertContains('cache', $excluded);
        $this->assertNotContains('employees', $excluded, 'business data must still be backed up');
        $this->assertNotContains('payroll_items', $excluded);
        $this->assertNotContains('migrations', $excluded, 'schema state is needed to restore coherently');
    }

    // ── Schedule settings ────────────────────────────────────────────────────

    public function test_admin_can_update_schedule_settings(): void
    {
        $this->actingAs($this->admin());

        $this->put('/backups/schedule', [
            'enabled' => 1,
            'frequency' => 'daily',
            'run_at' => '03:30',
            'retention_days' => 14,
        ])->assertRedirect();

        $this->assertDatabaseHas('backup_schedules', [
            'enabled' => 1,
            'frequency' => 'daily',
            'retention_days' => 14,
        ]);
    }

    public function test_schedule_update_requires_day_of_week_when_weekly(): void
    {
        $this->actingAs($this->admin());

        $this->put('/backups/schedule', [
            'enabled' => 1,
            'frequency' => 'weekly',
            'run_at' => '03:30',
        ])->assertSessionHasErrors('day_of_week');
    }

    public function test_schedule_update_rejects_invalid_frequency(): void
    {
        $this->actingAs($this->admin());

        $this->put('/backups/schedule', [
            'enabled' => 1,
            'frequency' => 'monthly',
            'run_at' => '03:30',
        ])->assertSessionHasErrors('frequency');
    }

    public function test_retention_days_is_nullable_and_persists(): void
    {
        $this->actingAs($this->admin());

        // An empty string mirrors what a real browser submits for a blank
        // number input — the global ConvertEmptyStringsToNull middleware turns
        // it into null before validation, which `nullable` then accepts.
        $this->put('/backups/schedule', [
            'enabled' => 0,
            'frequency' => 'daily',
            'run_at' => '02:00',
            'retention_days' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('backup_schedules', ['retention_days' => null]);

        $this->put('/backups/schedule', [
            'enabled' => 0,
            'frequency' => 'daily',
            'run_at' => '02:00',
            'retention_days' => 45,
        ])->assertRedirect();

        $this->assertDatabaseHas('backup_schedules', ['retention_days' => 45]);
    }

    // ── BackupSchedule model ─────────────────────────────────────────────────

    public function test_backup_schedule_current_returns_singleton_row(): void
    {
        $first = BackupSchedule::current();
        $second = BackupSchedule::current();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, BackupSchedule::count());
    }

    public function test_is_due_now_false_when_disabled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 02:00:00'));

        $schedule = BackupSchedule::current();
        $schedule->update(['enabled' => false, 'frequency' => 'daily', 'run_at' => '02:00:00']);

        $this->assertFalse($schedule->fresh()->isDueNow());
    }

    public function test_is_due_now_false_when_already_ran_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 02:00:00'));

        $schedule = BackupSchedule::current();
        $schedule->update([
            'enabled' => true,
            'frequency' => 'daily',
            'run_at' => '02:00:00',
            'last_run_at' => now(),
        ]);

        $this->assertFalse($schedule->fresh()->isDueNow());
    }

    public function test_is_due_now_true_when_enabled_and_matching_minute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 02:00:00'));

        $schedule = BackupSchedule::current();
        $schedule->update([
            'enabled' => true,
            'frequency' => 'daily',
            'run_at' => '02:00:00',
            'last_run_at' => null,
        ]);

        $this->assertTrue($schedule->fresh()->isDueNow());
    }
}
