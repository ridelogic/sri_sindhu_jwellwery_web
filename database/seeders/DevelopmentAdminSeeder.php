<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT ONLY — local administrator account.
 *
 *   Email:    admin@example.test
 *   Password: DevAdmin!2026   (development password — never use in production)
 *
 * Refuses to run in production. Create real administrators with:
 *   php artisan admin:create
 */
class DevelopmentAdminSeeder extends Seeder
{
    public const EMAIL = 'admin@example.test';

    public const PASSWORD = 'DevAdmin!2026';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevelopmentAdminSeeder must never run in production. Use "php artisan admin:create" instead.');
        }

        $admin = User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            ['name' => 'Development Admin', 'password' => self::PASSWORD], // hashed by the model cast
        );

        if (! $admin->isAdmin()) {
            $admin->makeAdmin();
        }
    }
}
