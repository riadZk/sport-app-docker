<?php

namespace Database\Factories;

use App\Models\Field;
use App\Models\SportCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Field>
 */
class FieldFactory extends Factory
{
    protected $model = Field::class;

    public function definition(): array
    {
        return [
            'name'              => fake()->company() . ' Field',
            'address'           => fake()->streetAddress() . ', Casablanca',
            'city'              => 'Casablanca',
            'latitude'          => fake()->latitude(33.50, 33.65),
            'longitude'         => fake()->longitude(-7.70, -7.50),
            'surface'           => fake()->randomElement(['grass', 'artificial', 'vinyl', 'concrete', 'sand']),
            'status'            => fake()->randomElement(['active', 'inactive', 'maintenance']),
            'format'            => fake()->randomElement(['5v5', '6v6', '7v7', '8v8', '11v11']),
            'description'       => fake()->optional()->sentence(),
            'owner_id'          => User::factory(),
            'sport_category_id' => SportCategory::factory(),
        ];
    }
}
