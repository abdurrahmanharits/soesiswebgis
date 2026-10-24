<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Fallback when no admin can sign in to reset a password in the UI.
 * The new password is typed at a hidden prompt.
 */
class ResetPassword extends Command
{
    protected $signature = 'soesis:reset-password {login : E-mail or username}';

    protected $description = 'Set a new password for a user';

    public function handle(): int
    {
        $login = mb_strtolower(trim($this->argument('login')));
        $user = User::where(str_contains($login, '@') ? 'email' : 'username', $login)->first();

        if (! $user) {
            $this->error('No user with that e-mail or username.');

            return self::FAILURE;
        }

        $data = [
            'password' => $this->secret('New password (min. 8 characters)'),
            'password_confirmation' => $this->secret('Repeat password'),
        ];

        $validator = Validator::make($data, ['password' => ['required', 'confirmed', Password::min(8)]]);

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $user->update(['password' => $data['password']]);

        $this->info("Password for {$user->username} updated.");

        return self::SUCCESS;
    }
}
