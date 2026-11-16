<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Create (or promote) an administrator — the production-safe alternative to
 * the development admin seeder. The password is asked for interactively and
 * is never echoed or logged.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email : Administrator email address} {--name= : Display name (defaults to "Administrator")}';

    protected $description = 'Create a new administrator, or grant admin rights to an existing user';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:150']])->fails()) {
            $this->error('Please provide a valid email address.');

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing) {
            if ($existing->isAdmin()) {
                $this->info('This user is already an administrator.');

                return self::SUCCESS;
            }

            if (! $this->confirm("A customer account exists for {$email}. Grant it admin rights?")) {
                return self::FAILURE;
            }

            $existing->makeAdmin();
            $this->info('Admin rights granted.');

            return self::SUCCESS;
        }

        $password = (string) $this->secret('Password (min 12 characters, mixed case, numbers and symbols)');
        $confirmation = (string) $this->secret('Confirm password');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $this->option('name') ?: 'Administrator',
            'email' => $email,
            'password' => $password, // hashed by the model cast
        ])->makeAdmin();

        $this->info("Administrator {$email} created.");

        return self::SUCCESS;
    }
}
