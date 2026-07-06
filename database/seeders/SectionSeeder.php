<?php

namespace Database\Seeders;

use App\Models\Section;
use Illuminate\Database\Seeder;

class SectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Section::query()->updateOrCreate(
            ['section_name' => 'A'],
            [
                'capacity' => 40,
                'status' => Section::STATUS_ACTIVE,
            ]
        );

        Section::query()->updateOrCreate(
            ['section_name' => 'B'],
            [
                'capacity' => 40,
                'status' => Section::STATUS_ACTIVE,
            ]
        );
    }
}