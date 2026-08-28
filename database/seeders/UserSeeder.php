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
    /**
     * @throws RuntimeException if ADMIN_DEFAULT_PASSWORD is not set — there
     *                          is no hardcoded fallback, weak or otherwise, for the default Super
     *                          Admin Psychometrician account's password.
     */
    public function run(): void
    {
        $password = env('ADMIN_DEFAULT_PASSWORD');

        if (empty($password)) {
            throw new RuntimeException(
                'ADMIN_DEFAULT_PASSWORD is not set in .env. Set it to a strong password before running this seeder.'
            );
        }

        $role = Role::query()->where('name', 'psychometrician')->firstOrFail();

        User::query()->updateOrCreate(
            ['email' => 'superadmin@normi.edu.ph'],
            [
                'role_id' => $role->id,
                'name' => 'Default Super Admin',
                'password' => Hash::make($password),
                'is_active' => true,
            ]
        );
    }
}
