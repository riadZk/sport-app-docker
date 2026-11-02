<?php

namespace Database\Factories;

use App\Models\SportCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SportCategory>
 */
class SportCategoryFactory extends Factory
{
    protected $model = SportCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Football',
                'Basketball',
                'Tennis',
                'Volleyball',
                'Badminton',
                'Swimming',
                'Running',
                'Cycling',
            ]),
        ];
    }
}
