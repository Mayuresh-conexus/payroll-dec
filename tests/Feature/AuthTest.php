<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'password' => bcrypt('password')]);
    }

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff', 'password' => bcrypt('password')]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager', 'password' => bcrypt('password')]);
    }

    // ── Guest access ─────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_employees(): void
    {
        $this->get('/employees')->assertRedirect('/login');
    }

    public function test_guest_cannot_access_payroll(): void
    {
        $this->get('/payroll')->assertRedirect('/login');
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertOk();
    }

    // ── Login success / failure ───────────────────────────────────────────────

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $user = $this->admin();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = $this->admin();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $this->post('/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password',
        ])->assertSessionHasErrors();
    }

    // ── Logout ───────────────────────────────────────────────────────────────

    public function test_logout_works(): void
    {
        $user = $this->admin();
        $this->actingAs($user);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertGuest();
    }

    // ── Role-based restrictions ───────────────────────────────────────────────

    public function test_staff_cannot_access_payroll(): void
    {
        $this->actingAs($this->staff());

        $this->get('/payroll')->assertForbidden();
    }

    public function test_staff_cannot_access_employees(): void
    {
        $this->actingAs($this->staff());

        $this->get('/employees')->assertForbidden();
    }

    public function test_manager_cannot_access_employees(): void
    {
        // Route is admin-only in routes/web.php — manager gets 403
        $this->actingAs($this->manager());

        $this->get('/employees')->assertForbidden();
    }

    public function test_manager_cannot_access_users_page(): void
    {
        $this->actingAs($this->manager());

        $this->get('/users')->assertForbidden();
    }

    public function test_admin_can_access_users_page(): void
    {
        $this->actingAs($this->admin());

        $this->get('/users')->assertOk();
    }
}
