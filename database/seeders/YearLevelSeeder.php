<?php

namespace Database\Seeders;

use App\Models\YearLevel;
use Illuminate\Database\Seeder;

class YearLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        YearLevel::query()->updateOrCreate(
            ['display_order' => 1],
            [
                'label' => '1st Year',
                'status' => YearLevel::STATUS_ACTIVE,
            ]
        );

        YearLevel::query()->updateOrCreate(
            ['display_order' => 2],
            [
                'label' => '2nd Year',
                'status' => YearLevel::STATUS_ACTIVE,
            ]
        );
    }
}