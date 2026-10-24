<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_admin_bootstraps_the_first_admin(): void
    {
        $this->artisan('soesis:create-admin', ['--name' => 'Admin SoESIS', '--username' => 'Admin', '--email' => 'Admin@Example.org'])
            ->expectsQuestion('Password (min. 8 characters)', 'secret-pass-1')
            ->expectsQuestion('Repeat password', 'secret-pass-1')
            ->assertSuccessful();

        $admin = User::where('username', 'admin')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertSame('admin@example.org', $admin->email);
        $this->assertTrue(Hash::check('secret-pass-1', $admin->password));
    }

    public function test_create_admin_refuses_when_an_admin_exists(): void
    {
        User::factory()->role('admin')->create();

        $this->artisan('soesis:create-admin')
            ->expectsOutputToContain('An admin account already exists')
            ->assertFailed();

        $this->assertSame(1, User::count());
    }

    public function test_create_admin_validates_input(): void
    {
        $this->artisan('soesis:create-admin', ['--name' => 'A', '--username' => 'bad name', '--email' => 'not-an-email'])
            ->expectsQuestion('Password (min. 8 characters)', 'short')
            ->expectsQuestion('Repeat password', 'different')
            ->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_reset_password_by_username_or_email(): void
    {
        $user = User::factory()->create(['username' => 'rina', 'email' => 'rina@example.org', 'password' => 'old-pass-1']);

        $this->artisan('soesis:reset-password', ['login' => 'RINA@example.org'])
            ->expectsQuestion('New password (min. 8 characters)', 'new-pass-12')
            ->expectsQuestion('Repeat password', 'new-pass-12')
            ->assertSuccessful();

        $this->assertTrue(Hash::check('new-pass-12', $user->fresh()->password));

        $this->artisan('soesis:reset-password', ['login' => 'nobody'])->assertFailed();
    }
}
