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
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'newadmin@example.com', 'role' => 'admin']);
    }

    public function test_admin_can_create_manager(): void
    {
        $this->actingAs($this->admin());

        $this->post('/users', [
            'name' => 'New Manager',
            'email' => 'newmanager@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'manager',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['email' => 'newmanager@example.com', 'role' => 'manager']);
    }

    public function test_invalid_role_is_rejected_on_create(): void
    {
        $this->actingAs($this->admin());

        $this->post('/users', [
            'name' => 'Fake Superuser',
            'email' => 'fakesuperuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'superadmin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'fakesuperuser@example.com']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->actingAs($this->admin());

        User::factory()->create(['email' => 'existing@example.com']);

        $this->post('/users', [
            'name' => 'Duplicate',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
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
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->delete("/users/{$other->id}")->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_user_name_and_email(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->patch("/users/{$other->id}", [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'admin',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Updated Name', 'email' => 'updated@example.com', 'role' => 'admin']);
    }

    public function test_admin_can_promote_user_to_manager(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->patch("/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => 'manager',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'role' => 'manager']);
    }

    public function test_invalid_role_is_rejected_on_update(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->patch("/users/{$other->id}", [
            'name' => $other->name,
            'email' => $other->email,
            'role' => 'superadmin',
        ])->assertSessionHasErrors('role');
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->patch("/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'manager',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }
}
