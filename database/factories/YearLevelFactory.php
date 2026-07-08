<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<YearLevel>
 */
class YearLevelFactory extends Factory
{
    protected $model = YearLevel::class;

    public function definition(): array
    {
        return [
            'label' => $this->faker->unique()->numerify('Year #'),
            'display_order' => $this->faker->unique()->numberBetween(1, 1000),
            'status' => YearLevel::STATUS_ACTIVE,
        ];
    }
}
