<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GamePrediction;
use App\Models\PlayerStat;
use App\Models\SportCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePredictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // sport_categories rows are required by FK defaults (id 1).
        SportCategory::factory()->create();
    }

    private function makeGame(User $owner, string $status = 'scheduled'): Game
    {
        $payload = [
            'game_date'  => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'duration'   => 90,
            'game_type'  => '5v5',
            'gender'     => 'mixed',
            'privacy'    => 'public',
            'home_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
        ];

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $payload)
            ->assertStatus(201);

        $game = Game::latest('id')->first();

        if ($status !== 'draft') {
            $this->actingAs($owner, 'sanctum')
                ->postJson("/api/games/{$game->id}/complete")
                ->assertStatus(200);
        }

        return $game->fresh();
    }

    public function test_user_can_predict_home_win(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create();
        $game = $this->makeGame($owner);

        $this->actingAs($voter, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'home_win'])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Prediction submitted successfully.')
            ->assertJsonPath('results.home_win', 1)
            ->assertJsonPath('results.draw', 0)
            ->assertJsonPath('results.away_win', 0)
            ->assertJsonPath('results.total_votes', 1)
            ->assertJsonPath('results.my_prediction.prediction', 'home_win');

        $this->assertDatabaseHas('game_predictions', [
            'game_id' => $game->id,
            'user_id' => $voter->id,
            'prediction' => 'home_win',
        ]);
    }

    public function test_user_can_predict_draw_or_away_win(): void
    {
        $owner = User::factory()->create();
        $game = $this->makeGame($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'draw'])
            ->assertStatus(200)
            ->assertJsonPath('results.draw', 1);

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'away_win'])
            ->assertStatus(200)
            ->assertJsonPath('results.away_win', 1)
            ->assertJsonPath('results.total_votes', 2)
            ->assertJsonPath('results.home_win', 0)
            ->assertJsonPath('results.draw', 1);
    }

    public function test_voting_again_updates_the_prediction(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create();
        $game = $this->makeGame($owner);

        $this->actingAs($voter, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'home_win'])
            ->assertStatus(200);

        $this->actingAs($voter, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'draw'])
            ->assertStatus(200)
            ->assertJsonPath('message', 'Prediction updated successfully.')
            ->assertJsonPath('results.draw', 1)
            ->assertJsonPath('results.home_win', 0)
            ->assertJsonPath('results.total_votes', 1);

        $this->assertDatabaseCount('game_predictions', 1);
        $this->assertDatabaseHas('game_predictions', [
            'game_id' => $game->id,
            'user_id' => $voter->id,
            'prediction' => 'draw',
        ]);
    }

    public function test_prediction_is_required_and_validated(): void
    {
        $owner = User::factory()->create();
        $game = $this->makeGame($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", [])
            ->assertStatus(422);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'maybe'])
            ->assertStatus(422);

        $this->assertDatabaseCount('game_predictions', 0);
    }

    public function test_voting_is_closed_for_completed_games(): void
    {
        $owner = User::factory()->create();
        $game = $this->makeGame($owner);
        $game->update(['status' => Game::STATUS_COMPLETED]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'home_win'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Voting is closed for this game.');
    }

    public function test_voting_is_closed_for_cancelled_games(): void
    {
        $owner = User::factory()->create();
        $game = $this->makeGame($owner);
        $game->update(['status' => Game::STATUS_CANCELLED]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'draw'])
            ->assertStatus(422);
    }

    public function test_user_can_retrieve_tallies_and_own_prediction(): void
    {
        $owner = User::factory()->create();
        $game = $this->makeGame($owner);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'away_win']);

        User::factory()->count(3)->create()->each(function (User $voter) use ($game) {
            GamePrediction::create([
                'game_id' => $game->id,
                'user_id' => $voter->id,
                'prediction' => 'home_win',
            ]);
        });

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/games/{$game->id}/predictions")
            ->assertStatus(200)
            ->assertJsonPath('results.total_votes', 4)
            ->assertJsonPath('results.home_win', 3)
            ->assertJsonPath('results.away_win', 1)
            ->assertJsonPath('results.draw', 0)
            ->assertJsonPath('results.my_prediction.prediction', 'away_win');
    }

    public function test_user_can_withdraw_their_prediction(): void
    {
        $owner = User::factory()->create();
        $voter = User::factory()->create();
        $game = $this->makeGame($owner);

        $this->actingAs($voter, 'sanctum')
            ->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'draw']);

        $this->actingAs($voter, 'sanctum')
            ->deleteJson("/api/games/{$game->id}/predictions")
            ->assertStatus(200)
            ->assertJsonPath('results.total_votes', 0)
            ->assertJsonPath('results.my_prediction', null);

        $this->assertDatabaseMissing('game_predictions', [
            'game_id' => $game->id,
            'user_id' => $voter->id,
        ]);

        // Withdrawing again → 404.
        $this->actingAs($voter, 'sanctum')
            ->deleteJson("/api/games/{$game->id}/predictions")
            ->assertStatus(404);
    }

    public function test_predictions_require_authentication(): void
    {
        $owner = User::factory()->create();
        $game = $this->makeGame($owner);

        $this->postJson("/api/games/{$game->id}/predictions", ['prediction' => 'home_win'])
            ->assertStatus(401);
    }
}
