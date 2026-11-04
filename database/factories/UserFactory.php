<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state (matches the `users` table).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'mobile' => '09' . fake()->numerify('#########'),
            'password' => 'password', // hashed by User::setPasswordAttribute
            'status' => User::ACTIVE,
            'role' => User::KARSHENAS_FOROSH,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => User::ADMIN]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => User::INACTIVE]);
    }
}
