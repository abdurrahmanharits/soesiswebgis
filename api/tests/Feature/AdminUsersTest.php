<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->admin = User::factory()->role('admin')->create(['name' => 'Admin']);
        $this->actingAs($this->admin);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Rina Pratiwi',
            'username' => 'Rina.Pratiwi',
            'email' => 'Rina@Example.org',
            'role' => 'curator',
            'is_active' => '1',
            'password' => 'long-enough-1',
            'password_confirmation' => 'long-enough-1',
            ...$overrides,
        ];
    }

    public function test_admin_sees_users_page_and_nav_link(): void
    {
        $this->get('/admin/users')->assertOk()->assertSee('All users')->assertSee('>Users<', false);
    }

    public function test_admin_creates_user_with_hashed_password(): void
    {
        $organization = Organization::create(['name' => 'WWF-Indonesia']);

        $this->post('/admin/users', $this->payload(['organization_id' => $organization->id]))
            ->assertRedirect('/admin/users');

        $user = User::where('username', 'rina.pratiwi')->firstOrFail();

        $this->assertSame('rina@example.org', $user->email);
        $this->assertSame('curator', $user->role);
        $this->assertSame($organization->id, $user->organization_id);
        $this->assertTrue($user->is_active);
        $this->assertNotSame('long-enough-1', $user->password);
        $this->assertTrue(Hash::check('long-enough-1', $user->password));
    }

    public function test_user_validation(): void
    {
        User::factory()->create(['username' => 'taken', 'email' => 'taken@example.org']);

        $this->post('/admin/users', $this->payload([
            'username' => 'Bad Name!',
            'email' => 'TAKEN@example.org',
            'role' => 'superuser',
            'password' => 'short',
            'password_confirmation' => 'other',
        ]))->assertSessionHasErrors(['username', 'email', 'role', 'password']);

        $this->post('/admin/users', $this->payload(['username' => 'TAKEN']))->assertSessionHasErrors('username');
    }

    public function test_admin_changes_role_and_resets_password(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1']);

        $this->put("/admin/users/{$user->id}", $this->payload([
            'username' => $user->username, 'email' => $user->email, 'role' => 'admin',
            'password' => 'new-password-1', 'password_confirmation' => 'new-password-1',
        ]))->assertRedirect('/admin/users');

        $user->refresh();
        $this->assertSame('admin', $user->role);
        $this->assertTrue(Hash::check('new-password-1', $user->password));
    }

    public function test_empty_password_on_edit_keeps_current_one(): void
    {
        $user = User::factory()->create(['password' => 'old-password-1']);

        $this->put("/admin/users/{$user->id}", $this->payload([
            'username' => $user->username, 'email' => $user->email, 'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect('/admin/users');

        $this->assertTrue(Hash::check('old-password-1', $user->fresh()->password));
    }

    public function test_admin_deactivates_user(): void
    {
        $user = User::factory()->create();

        $this->put("/admin/users/{$user->id}", $this->payload([
            'username' => $user->username, 'email' => $user->email, 'role' => 'uploader', 'is_active' => '0', 'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect('/admin/users');

        $this->assertFalse($user->fresh()->is_active);
    }

    public function test_admin_cannot_remove_own_admin_access(): void
    {
        $base = ['username' => $this->admin->username, 'email' => $this->admin->email, 'password' => '', 'password_confirmation' => ''];

        $this->put("/admin/users/{$this->admin->id}", $this->payload([...$base, 'role' => 'curator']))->assertSessionHasErrors('role');
        $this->put("/admin/users/{$this->admin->id}", $this->payload([...$base, 'role' => 'admin', 'is_active' => '0']))->assertSessionHasErrors('role');

        $this->assertSame('admin', $this->admin->fresh()->role);
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_admin_can_demote_another_admin(): void
    {
        $other = User::factory()->role('admin')->create();

        $this->put("/admin/users/{$other->id}", $this->payload([
            'username' => $other->username, 'email' => $other->email, 'role' => 'curator', 'password' => '', 'password_confirmation' => '',
        ]))->assertRedirect('/admin/users');

        $this->assertSame('curator', $other->fresh()->role);
    }

    public function test_admin_manages_organizations(): void
    {
        $this->post('/admin/organizations', ['name' => 'WWF-Indonesia'])->assertRedirect('/admin/users');
        $organization = Organization::where('name', 'WWF-Indonesia')->firstOrFail();

        $this->post('/admin/organizations', ['name' => 'WWF-Indonesia'])->assertSessionHasErrors('name', null, 'organization');

        $this->put("/admin/organizations/{$organization->id}", ['name' => 'WWF Indonesia'])->assertRedirect('/admin/users');
        $this->assertSame('WWF Indonesia', $organization->fresh()->name);
    }
}
