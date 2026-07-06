<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::query()->where('name', 'psychometrician')->firstOrFail();
        
        User::query()->updateOrCreate(
            ['email' => 'superadmin@normi.edu.ph'],
            [
                'role_id' => $role->id,
                'name' => 'Default Super Admin',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'ChangeMe123!')),
                'is_active' => true,
                
            ]
        );
    }
}   