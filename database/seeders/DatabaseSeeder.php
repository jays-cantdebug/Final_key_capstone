<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            CourseSeeder::class,
            YearLevelSeeder::class,
            SectionSeeder::class,
        ]);

        app('Database\\Seeders\\StudentSeeder')->run();

        $this->call([
            QuestionnaireSeeder::class,
            QuestionnaireVersionSeeder::class,
            DassQuestionSeeder::class,
            ClassificationThresholdSeeder::class,
            SettingsSeeder::class,
        ]);
    }
}
