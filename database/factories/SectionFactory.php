<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    public function definition(): array
    {
        return [
            'section_name' => $this->faker->unique()->lexify('Section ?'),
            'capacity' => 40,
            'status' => Section::STATUS_ACTIVE,
        ];
    }
}
