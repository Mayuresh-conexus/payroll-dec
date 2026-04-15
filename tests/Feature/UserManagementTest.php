<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->admin());

        $this->post('/users', [
            'name'                  => 'New Manager',
            'email'                 => 'manager@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'manager',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'manager@example.com', 'role' => 'manager']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->actingAs($this->admin());

        User::factory()->create(['email' => 'existing@example.com']);

        $this->post('/users', [
            'name'     => 'Duplicate',
            'email'    => 'existing@example.com',
            'password' => 'password123',
            'role'     => 'staff',
        ])->assertSessionHasErrors('email');
    }

    public function test_manager_cannot_access_users_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->get('/users')->assertForbidden();
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->delete("/users/{$admin->id}")->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_other_user(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin);

        $this->delete("/users/{$other->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_change_user_role(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($admin);

        $this->patch("/users/{$staff->id}", [
            'name'  => $staff->name,
            'email' => $staff->email,
            'role'  => 'manager',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'manager']);
    }
}
