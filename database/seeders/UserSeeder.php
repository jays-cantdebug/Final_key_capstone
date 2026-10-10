<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public const EMAIL = 'superadmin@normi.edu.ph';

    /**
     * @throws RuntimeException if ADMIN_DEFAULT_PASSWORD is not set — there
     *                          is no hardcoded fallback, weak or otherwise, for the default Super
     *                          Admin Psychometrician account's password.
     */
    public function run(): void
    {
        // Create only when missing: re-running the seeders after go-live
        // must never reset a changed password or re-activate a deactivated
        // account.
        if (User::query()->where('email', self::EMAIL)->exists()) {
            return;
        }

        $password = env('ADMIN_DEFAULT_PASSWORD');

        if (empty($password)) {
            throw new RuntimeException(
                'ADMIN_DEFAULT_PASSWORD is not set in .env. Set it to a strong password before running this seeder.'
            );
        }

        $role = Role::query()->where('name', 'psychometrician')->firstOrFail();

        User::query()->create([
            'email' => self::EMAIL,
            'role_id' => $role->id,
            'name' => 'Default Super Admin',
            'password' => Hash::make($password),
            'is_active' => true,
        ]);
    }
}
