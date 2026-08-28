<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The model the factory corresponds to.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'role_id' => $this->roleId('psychometrician', 'Psychometrician'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Assign the psychometrician role.
     */
    public function psychometrician(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $this->roleId('psychometrician', 'Psychometrician'),
        ]);
    }

    /**
     * Assign the guidance counselor role.
     */
    public function guidanceCounselor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $this->roleId('guidance_counselor', 'Guidance Counselor'),
        ]);
    }

    private function roleId(string $name, string $displayName): int
    {
        return Role::query()->firstOrCreate(
            ['name' => $name],
            ['display_name' => $displayName]
        )->id;
    }
}
