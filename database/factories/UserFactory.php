<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
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
            'first_name'   => fake()->firstName(),
            'last_name'    => fake()->lastName(),
            'username'     => fake()->unique()->userName(),
            'phone_number' => fake()->unique()->numerify('06########'),
            'city'         => fake()->city(),
            'country'      => fake()->country(),
            'email'        => fake()->unique()->safeEmail(),
            'password'     => static::$password ??= Hash::make('password'),
            'gender'       => fake()->randomElement(['male', 'female']),
            'birthday'     => fake()->dateTimeBetween('-40 years', '-16 years')->format('Y-m-d'),
            'cin'          => fake()->unique()->numerify('########'),
            'height'       => fake()->numberBetween(155, 200),
            'weight'       => fake()->numberBetween(50, 110),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
