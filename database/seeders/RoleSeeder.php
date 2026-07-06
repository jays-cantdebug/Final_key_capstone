<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::query()->updateOrCreate(
            ['name' => 'psychometrician'],
            ['display_name' => 'Psychometrician']
        );

        Role::query()->updateOrCreate(
            ['name' => 'guidance_counselor'],
            ['display_name' => 'Guidance Counselor']
        );
    }
}