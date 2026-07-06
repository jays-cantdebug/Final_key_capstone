<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => 'psychometrician',
            'display_name' => 'Psychometrician',
        ];
    }

    public function psychometrician(): static
    {
        return $this->state(fn (): array => [
            'name' => 'psychometrician',
            'display_name' => 'Psychometrician',
        ]);
    }

    public function guidanceCounselor(): static
    {
        return $this->state(fn (): array => [
            'name' => 'guidance_counselor',
            'display_name' => 'Guidance Counselor',
        ]);
    }
}