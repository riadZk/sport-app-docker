<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'game_date'  => now()->addDay()->toDateString(),
            'start_time' => '18:00:00',
            'duration'   => 90,
            'game_type'  => '5v5',
            'gender'     => 'mixed',
            'privacy'    => 'public',
            'status'     => Game::STATUS_DRAFT,
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Game::STATUS_SCHEDULED,
        ]);
    }

    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Game::STATUS_ONGOING,
        ]);
    }
}
