<?php

namespace Database\Factories;

use App\Models\PlayerStat;
use App\Models\SportCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerStat>
 */
class PlayerStatFactory extends Factory
{
    protected $model = PlayerStat::class;

    public function definition(): array
    {
        return [
            'user_id'            => User::factory(),
            'sport_category_id'  => 1,
            'primary_position'   => fake()->randomElement(['GK', 'CB', 'LB', 'RB', 'CM', 'CAM', 'LW', 'RW', 'ST']),
            'secondary_position' => fake()->optional()->randomElement(['GK', 'CB_L', 'CB_R', 'LB', 'RB', 'CM', 'CAM', 'LW', 'RW', 'ST']),
            'prefered_foot'      => fake()->randomElement(['left', 'right', 'both']),
            'match_played'       => fake()->numberBetween(1, 99),
            'nbr_goals'          => fake()->numberBetween(1, 99),
            'pac'                => fake()->numberBetween(1, 99),
            'dribble'            => fake()->numberBetween(1, 99),
            'physique'           => fake()->numberBetween(1, 99),
            'shoot'              => fake()->numberBetween(1, 99),
            'pass'               => fake()->numberBetween(1, 99),
            'defence'            => fake()->numberBetween(1, 99),
            'assists'            => fake()->numberBetween(1, 99),
        ];
    }
}
