<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Bootstraps the first admin account (there is no self-registration). Refuses
 * once an admin exists; further users are created by an admin in the UI.
 * The password is always typed at a hidden prompt, never passed as an option.
 */
class CreateAdmin extends Command
{
    protected $signature = 'soesis:create-admin
        {--name= : Display name}
        {--username= : Login username (3–30 lowercase letters, digits, . or _)}
        {--email= : Login e-mail}';

    protected $description = 'Create the first admin account';

    public function handle(): int
    {
        if (User::where('role', 'admin')->exists()) {
            $this->error('An admin account already exists. Create further users in the admin UI (/admin/users).');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('name') ?: $this->ask('Name'),
            'username' => mb_strtolower(trim($this->option('username') ?: $this->ask('Username'))),
            'email' => mb_strtolower(trim($this->option('email') ?: $this->ask('E-mail'))),
            'password' => $this->secret('Password (min. 8 characters)'),
            'password_confirmation' => $this->secret('Repeat password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'regex:'.User::USERNAME_PATTERN, 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::create([...$validator->validated(), 'role' => 'admin', 'is_active' => true]);

        $this->info("Admin {$data['username']} created. Sign in at /login.");

        return self::SUCCESS;
    }
}
