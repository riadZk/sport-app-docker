<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Field;
use App\Models\FieldFormat;
use App\Models\Game;
use App\Models\PlayerStat;
use App\Models\SportCategory;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameCreationTest extends TestCase
{
    use RefreshDatabase;

    /** Home-side players created by the latest makeChallengedGame() call. */
    protected $homePlayers;

    protected function setUp(): void
    {
        parent::setUp();

        // sport_categories rows are required by FK defaults (id 1).
        SportCategory::factory()->create();
    }

    private function authUser(): User
    {
        return User::factory()->create();
    }

    private function validPayload(array $overrides = []): array
    {
        $base = [
            'game_date'  => now()->addDay()->toDateString(),
            'start_time' => '18:00',
            'duration'   => 90,
            'game_type'  => '5v5',
            'gender'     => 'mixed',
            'privacy'    => 'public',
        ];

        // A game creation MUST carry an explicit home-side choice.
        if (! isset($overrides['home_team_id']) && ! isset($overrides['home_players'])) {
            $base['home_players'] = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all();
        }

        return array_merge($base, $overrides);
    }

    private function makeField(): Field
    {
        $venue = Venue::create([
            'name'      => 'Test Venue',
            'is_active' => true,
        ]);

        $format = FieldFormat::create([
            'name'             => '5v5',
            'players_per_team' => 5,
        ]);

        return Field::create([
            'venue_id'         => $venue->id,
            'field_format_id'  => $format->id,
            'name'             => 'Field A',
            'surface_type'     => 'artificial',
            'is_active'        => true,
        ]);
    }

    private function makeTeam(User $owner, string $name = 'Team A'): Team
    {
        return Team::create([
            'name'       => $name,
            'short_name' => substr($name, 0, 3),
            'city'       => 'Casablanca',
            'address'    => 'Somewhere',
            'match_type' => '5v5',
            'formation'  => '2-2',
            'user_id'    => $owner->id,
        ]);
    }

    /** A permanent team needs active members before it can field a roster. */
    private function attachMembers(Team $team, int $count = 2)
    {
        return PlayerStat::factory()->count($count)->create(['sport_category_id' => 1])
            ->each(fn($ps) => \App\Models\TeamMember::create([
                'team_id'   => $team->id,
                'player_id' => $ps->id,
                'role'      => 'player',
                'status'    => 'active',
            ]));
    }

    // 1. Authenticated user can create a draft game
    public function test_authenticated_user_can_create_draft_game(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonPath('results.status', 'draft');

        $this->assertDatabaseHas('games', [
            'user_id' => $user->id,
            'status'  => 'draft',
        ]);
    }

    // 2. Guest cannot create a game
    public function test_guest_cannot_create_game(): void
    {
        $this->postJson('/api/games', $this->validPayload())
            ->assertStatus(401);
    }

    // 2b. GET /games/teams — search, match_type filter, members count
    public function test_all_teams_search_match_type_and_members_count(): void
    {
        $user  = $this->authUser();
        $other = $this->authUser();

        $wanted = $this->makeTeam($other, 'Atlas Junior');
        $wanted->update(['match_type' => '6v6']);
        $this->attachMembers($wanted, 3);

        $this->makeTeam($other, 'Desert Wolves');          // different name, 5v5 default
        $this->makeTeam($user, 'Atlas Mine');              // own team — must be excluded

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/games/teams?search=Atlas&match_type=6v6')
            ->assertStatus(200)
            ->assertJsonCount(1, 'results.data')
            ->assertJsonPath('results.data.0.name', 'Atlas Junior')
            ->assertJsonPath('results.data.0.match_type', '6v6')
            ->assertJsonPath('results.data.0.members_count', 3);

        // match_type filter alone also works
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/games/teams?match_type=5v5')
            ->assertStatus(200)
            ->assertJsonCount(1, 'results.data')
            ->assertJsonPath('results.data.0.name', 'Desert Wolves');
    }

    // 3. User can create a manual game pitch
    public function test_user_can_create_manual_game_pitch(): void
    {
        $user = $this->authUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games/pitches', [
                'name'      => 'My Street Pitch',
                'address'   => '12 Rue X, Casablanca',
                'latitude'  => 33.5731,
                'longitude' => -7.5898,
            ])
            ->assertStatus(201)
            ->assertJsonPath('results.name', 'My Street Pitch');

        $this->assertDatabaseHas('game_pitches', ['user_id' => $user->id]);
    }

    // 3b. GET /games/pitches — paginated list of the authenticated user's pitches
    public function test_user_can_list_their_game_pitches_paginated(): void
    {
        $user  = $this->authUser();
        $other = $this->authUser();

        for ($i = 1; $i <= 12; $i++) {
            $user->gamePitches()->create([
                'name'      => "Pitch {$i}",
                'address'   => "Address {$i}",
                'latitude'  => 33.5,
                'longitude' => -7.5,
            ]);
        }

        $other->gamePitches()->create([
            'name'      => 'Other pitch',
            'address'   => 'Other address',
            'latitude'  => 33.5,
            'longitude' => -7.5,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/games/pitches')
            ->assertStatus(200)
            ->assertJsonPath('results.per_page', 10)
            ->assertJsonCount(10, 'results.data')
            ->assertJsonPath('results.total', 12)
            ->assertJsonMissing(['name' => 'Other pitch']);
    }

    // 4. user_id is taken from authenticated user (never from input)
    public function test_user_id_is_taken_from_authenticated_user(): void
    {
        $user = $this->authUser();
        $other = $this->authUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games/pitches', [
                'name'      => 'Pitch',
                'address'   => 'Address',
                'latitude'  => 33.5,
                'longitude' => -7.5,
            ]);

        $this->assertDatabaseMissing('game_pitches', ['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['user_id' => $other->id]));

        $this->assertDatabaseMissing('games', ['user_id' => $other->id]);
        $this->assertDatabaseHas('games', ['user_id' => $user->id]);
    }

    // 5. User can create a game with game_pitch_id
    public function test_user_can_create_game_with_game_pitch(): void
    {
        $user = $this->authUser();
        $pitch = $user->gamePitches()->create([
            'name'      => 'Pitch',
            'address'   => 'Address',
            'latitude'  => 33.5,
            'longitude' => -7.5,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['game_pitch_id' => $pitch->id]))
            ->assertStatus(201)
            ->assertJsonPath('results.pitch.id', $pitch->id);
    }

    // 6. User can create a game with booking_id
    public function test_user_can_create_game_with_booking(): void
    {
        $user = $this->authUser();
        $field = $this->makeField();

        $booking = Booking::create([
            'reference'      => 'BKTEST01',
            'field_id'       => $field->id,
            'user_id'        => $user->id,
            'booking_date'   => now()->addDay()->toDateString(),
            'start_time'     => '18:00',
            'end_time'       => '19:30',
            'total_price'    => 200,
            'status'         => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'booking_id'  => $booking->id,
                'start_time'  => '18:00',
                'duration'    => 90,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('results.booking.id', $booking->id);
    }

    // 6b. GET /games/bookings/available — active bookings not yet used by a game
    public function test_available_bookings_lists_only_unused_active_bookings(): void
    {
        $user  = $this->authUser();
        $other = $this->authUser();
        $field = $this->makeField();

        $makeBooking = fn(User $u, string $ref, string $status) => Booking::create([
            'reference'      => $ref,
            'field_id'       => $field->id,
            'user_id'        => $u->id,
            'booking_date'   => now()->addDay()->toDateString(),
            'start_time'     => '18:00',
            'end_time'       => '19:30',
            'total_price'    => 200,
            'status'         => $status,
            'payment_status' => 'paid',
        ]);

        $free      = $makeBooking($user, 'BKFREE01', 'confirmed');
        $cancelled = $makeBooking($user, 'BKCXL001', 'cancelled');
        $completed = $makeBooking($user, 'BKDONE01', 'completed');
        $used      = $makeBooking($user, 'BKUSED01', 'confirmed');
        $makeBooking($other, 'BKOTHER', 'confirmed'); // other user — must be excluded

        Game::factory()->create(['user_id' => $user->id, 'booking_id' => $used->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/games/bookings/available')
            ->assertStatus(200)
            ->assertJsonCount(1, 'results.data')
            ->assertJsonPath('results.data.0.id', $free->id)
            ->assertJsonMissing(['reference' => 'BKCXL001'])
            ->assertJsonMissing(['reference' => 'BKDONE01'])
            ->assertJsonMissing(['reference' => 'BKUSED01'])
            ->assertJsonMissing(['reference' => 'BKOTHER']);

        // ?date= filter
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/games/bookings/available?date=' . now()->subYear()->toDateString())
            ->assertStatus(200)
            ->assertJsonCount(0, 'results.data');
    }

    // 7. User can create a game without pitch/booking
    public function test_user_can_create_game_without_pitch_or_booking(): void
    {
        $user = $this->authUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload())
            ->assertStatus(201)
            ->assertJsonPath('results.pitch', null)
            ->assertJsonPath('results.booking', null);
    }

    // 8. Invalid latitude is rejected
    public function test_invalid_latitude_is_rejected(): void
    {
        $user = $this->authUser();

        // Note: the project's ApiRequest returns errors under `results`.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games/pitches', [
                'name'      => 'Pitch',
                'address'   => 'Address',
                'latitude'  => 95,
                'longitude' => -7.5,
            ])
            ->assertStatus(422)
            ->assertJsonPath('results.latitude.0', fn($e) => str_contains($e, 'between'));
    }

    // 9. Invalid longitude is rejected
    public function test_invalid_longitude_is_rejected(): void
    {
        $user = $this->authUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games/pitches', [
                'name'      => 'Pitch',
                'address'   => 'Address',
                'latitude'  => 33.5,
                'longitude' => -200,
            ])
            ->assertStatus(422)
            ->assertJsonPath('results.longitude.0', fn($e) => str_contains($e, 'between'));
    }

    // 10. Invalid date/time is rejected
    public function test_invalid_date_time_is_rejected(): void
    {
        $user = $this->authUser();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'game_date'  => now()->subDay()->toDateString(),
                'start_time' => '25:99',
            ]))
            ->assertStatus(422);

        $errors = $response->json('results');
        $this->assertArrayHasKey('game_date', $errors);
        $this->assertArrayHasKey('start_time', $errors);
    }

    // 11. User must choose exactly ONE way to play (team XOR players)
    public function test_cannot_choose_both_team_and_players(): void
    {
        $user = $this->authUser();
        $team = $this->makeTeam($user);
        $player = PlayerStat::factory()->create(['sport_category_id' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_team_id' => $team->id,
                'home_players' => [$player->id],
            ]))
            ->assertStatus(422);
    }

    public function test_must_choose_team_or_players(): void
    {
        $user = $this->authUser();

        // Explicitly send NO home-side choice at all.
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', [
                'game_date'  => now()->addDay()->toDateString(),
                'start_time' => '18:00',
                'duration'   => 90,
                'game_type'  => '5v5',
                'gender'     => 'mixed',
                'privacy'    => 'public',
            ])
            ->assertStatus(422)
            ->assertJsonPath('results.home_team_id.0', 'Choose a team to play with or select players.');
    }

    // 12. Option A: "Play with my team" → permanent game team, roster joined
    public function test_creating_game_with_permanent_team_copies_active_roster(): void
    {
        $user = $this->authUser();
        $team = $this->makeTeam($user);
        $memberIds = PlayerStat::factory()->count(3)->create(['sport_category_id' => 1])->pluck('id');
        foreach ($memberIds as $pid) {
            \App\Models\TeamMember::create([
                'team_id'    => $team->id,
                'player_id'  => $pid,
                'role'       => 'player',
                'status'     => 'active',
            ]);
        }

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['home_team_id' => $team->id]))
            ->assertStatus(201);

        $game = Game::where('user_id', $user->id)->first();

        // Permanent game team linked to teams.id, players joined immediately.
        $this->assertDatabaseHas('game_teams', [
            'game_id' => $game->id,
            'team_id' => $team->id,
            'type'    => 'permanent',
            'side'    => 'home',
        ]);
        $this->assertDatabaseCount('game_players', 3);
        $this->assertDatabaseMissing('game_player_requests', ['game_id' => $game->id]);
    }

    // 12b. Option B: "I want to choose players" → temporary game team + requests
    public function test_creating_game_with_picked_players_creates_temporary_team_and_requests(): void
    {
        $user = $this->authUser();
        $players = PlayerStat::factory()->count(3)->create(['sport_category_id' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players'   => $players->pluck('id')->all(),
                'home_team_name' => 'Street Squad',
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $user->id)->first();

        // Temporary game team — NEVER in the `teams` table.
        $this->assertDatabaseHas('game_teams', [
            'game_id'  => $game->id,
            'team_id'  => null,
            'type'     => 'temporary',
            'side'     => 'home',
            'name'     => 'Street Squad',
        ]);
        $this->assertDatabaseCount('teams', 0);

        // Players are PENDING until they accept; a request row exists per player.
        $this->assertDatabaseHas('game_players', [
            'game_id' => $game->id,
            'status'  => 'pending',
        ]);
        $this->assertDatabaseCount('game_player_requests', 3);
        $this->assertDatabaseHas('game_player_requests', [
            'player_stat_id' => $players->first()->id,
            'status'         => 'pending',
        ]);
    }

    // 12c. A player accepting the request becomes a confirmed member
    public function test_player_can_accept_request_and_become_confirmed(): void
    {
        $user = $this->authUser();
        $players = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players' => $players->pluck('id')->all(),
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $user->id)->first();
        $request = \App\Models\GamePlayerRequest::where('player_stat_id', $players->first()->id)->first();
        $invitedUser = User::find($players->first()->user_id);

        $this->actingAs($invitedUser, 'sanctum')
            ->postJson("/api/games/player-requests/{$request->id}/respond", ['status' => 'accepted'])
            ->assertStatus(200);

        $this->assertDatabaseHas('game_players', [
            'game_id'        => $game->id,
            'player_stat_id' => $players->first()->id,
            'status'         => 'joined',
        ]);

        // Rejecting the other one marks it declined.
        $request2 = \App\Models\GamePlayerRequest::where('player_stat_id', $players->last()->id)->first();
        $rejectUser = User::find($players->last()->user_id);

        $this->actingAs($rejectUser, 'sanctum')
            ->postJson("/api/games/player-requests/{$request2->id}/respond", ['status' => 'rejected'])
            ->assertStatus(200);

        $this->assertDatabaseHas('game_players', [
            'game_id'        => $game->id,
            'player_stat_id' => $players->last()->id,
            'status'         => 'declined',
        ]);
    }

    // 12d. Only the recipient can respond to a player request
    public function test_only_recipient_can_respond_to_player_request(): void
    {
        $user = $this->authUser();
        $players = PlayerStat::factory()->count(1)->create(['sport_category_id' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players' => $players->pluck('id')->all(),
            ]));

        $request = \App\Models\GamePlayerRequest::first();
        $stranger = $this->authUser();

        $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/games/player-requests/{$request->id}/respond", ['status' => 'accepted'])
            ->assertStatus(422)
            ->assertJsonPath('message', 'This request was not sent to you.');
    }

    // 13. Player capacity respects 5v5/6v6/7v7 (counted per game team)
    public function test_player_capacity_respects_game_type(): void
    {
        $user = $this->authUser();
        $game = Game::factory()->create(['user_id' => $user->id, 'game_type' => '5v5']);

        $players = PlayerStat::factory()->count(6)->create(['sport_category_id' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'home',
                'player_stat_ids' => $players->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Too many players for 5v5: max 5 per side.');
    }

    // 14. User cannot modify another user's game
    public function test_user_cannot_modify_another_users_game(): void
    {
        $owner = $this->authUser();
        $other = $this->authUser();
        $game = Game::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/games/{$game->id}", ['duration' => 60])
            ->assertStatus(404);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/games/{$game->id}/cancel")
            ->assertStatus(404);

        $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'draft']);
    }

    // 15. Booking conflict is rejected
    public function test_booking_conflict_is_rejected(): void
    {
        $user = $this->authUser();

        // First game occupies 18:00-19:30
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload())
            ->assertStatus(201);

        // Second game overlapping the same slot
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['start_time' => '19:00']))
            ->assertStatus(422)
            ->assertJsonPath('message', 'You already have a game in this time slot.');
    }

    // 16. Failed transaction does not leave partial records
    public function test_failed_transaction_does_not_leave_partial_records(): void
    {
        $user = $this->authUser();
        $field = $this->makeField();
        $game = Game::factory()->create([
            'user_id'    => $user->id,
            'game_date'  => now()->addDay()->toDateString(),
            'start_time' => '20:00',
            'duration'   => 30,
        ]);

        $bookingsBefore = Booking::count();

        // Attach a foreign booking → rejected, and no booking may be created
        // and the game's booking_id must stay null (transactional integrity).
        $foreignBooking = Booking::create([
            'reference'      => 'BKFOREIGN',
            'field_id'       => $field->id,
            'user_id'        => $this->authUser()->id,
            'booking_date'   => now()->addDay()->toDateString(),
            'start_time'     => '10:00',
            'end_time'       => '11:00',
            'total_price'    => 100,
            'status'         => 'confirmed',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/games/{$game->id}/booking", ['booking_id' => $foreignBooking->id])
            ->assertStatus(422);

        $this->assertDatabaseCount('bookings', $bookingsBefore + 1); // only the foreign one
        $this->assertDatabaseHas('games', ['id' => $game->id, 'booking_id' => null]);
    }

    // 17. Completing the wizard changes draft → scheduled
    public function test_finalizing_wizard_moves_draft_to_scheduled(): void
    {
        $user = $this->authUser();
        $game = Game::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/games/{$game->id}/complete")
            ->assertStatus(200)
            ->assertJsonPath('results.status', 'scheduled');

        $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'scheduled']);
    }

    // Bonus: recommended opponents + challenge flows (permanent & user target)
    public function test_recommended_opponents_and_team_challenge_flow(): void
    {
        $user = $this->authUser();
        $game = Game::factory()->create(['user_id' => $user->id]);
        $homeTeam = $this->makeTeam($user, 'Home');
        $this->attachMembers($homeTeam);

        // Home side = permanent game team via the setupSide service path.
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/games/{$game->id}", ['home_team_id' => $homeTeam->id])
            ->assertStatus(200);

        $otherOwner = $this->authUser();
        $this->makeTeam($otherOwner, 'Rival');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/games/{$game->id}/recommended-opponents")
            ->assertStatus(200)
            ->assertJsonCount(1, 'results');

        $rival = Team::where('name', 'Rival')->first();
        $this->attachMembers($rival); // the away roster is copied on acceptance

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/games/{$game->id}/opponent/challenge", [
                'team_id' => $rival->id,
                'message' => 'Up for a match?',
            ])
            ->assertStatus(201)
            ->assertJsonPath('results.status', 'pending');

        // Team challenge records the recipient user so the owner can respond.
        $this->assertDatabaseHas('game_challenges', [
            'challenged_team_id' => $rival->id,
            'challenged_user_id' => $otherOwner->id,
        ]);

        $challengeId = \App\Models\GameChallenge::first()->id;

        // Rival owner accepts → away side becomes the permanent rival team.
        $this->actingAs($otherOwner, 'sanctum')
            ->postJson("/api/games/challenges/{$challengeId}/respond", ['status' => 'accepted'])
            ->assertStatus(200);

        $awayGameTeam = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();
        $this->assertNotNull($awayGameTeam);
        $this->assertEquals('permanent', $awayGameTeam->type);
        $this->assertEquals($rival->id, $awayGameTeam->team_id);
    }

    // Bonus: user challenge → away side is a TEMPORARY game team (pick players)
    public function test_user_challenge_creates_temporary_away_side_on_accept(): void
    {
        $user = $this->authUser();
        $game = Game::factory()->create(['user_id' => $user->id]);
        $homeTeam = $this->makeTeam($user, 'Home');
        $this->attachMembers($homeTeam);
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/games/{$game->id}", ['home_team_id' => $homeTeam->id])
            ->assertStatus(200);

        $opponent = $this->authUser();
        $hisPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        // Challenge the USER (no team).
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/games/{$game->id}/opponent/challenge", ['user_id' => $opponent->id])
            ->assertStatus(201);

        $challengeId = \App\Models\GameChallenge::first()->id;

        $this->assertDatabaseHas('game_challenges', [
            'id'                 => $challengeId,
            'challenged_user_id' => $opponent->id,
            'challenged_team_id' => null,
        ]);

        // Opponent accepts AND picks his players → temporary away game team.
        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challengeId}/respond", [
                'status'          => 'accepted',
                'player_stat_ids' => $hisPlayers->pluck('id')->all(),
                'name'            => "Opponent's Five",
            ])
            ->assertStatus(200);

        $awayGameTeam = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();
        $this->assertNotNull($awayGameTeam);
        $this->assertEquals('temporary', $awayGameTeam->type);
        $this->assertNull($awayGameTeam->team_id);
        $this->assertDatabaseHas('game_players', [
            'game_team_id' => $awayGameTeam->id,
            'status'       => 'pending',
        ]);
    }

    // A user without any team picks players directly at creation (home side).
    public function test_user_without_team_builds_temporary_home_side(): void
    {
        $user = $this->authUser(); // owns NO team
        $players = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players' => $players->pluck('id')->all(),
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $user->id)->first();
        $homeGameTeam = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();

        $this->assertEquals('temporary', $homeGameTeam->type);
        $this->assertNull($homeGameTeam->team_id);
        $this->assertEquals($user->id, $homeGameTeam->owner_id);
    }

    // ── OWNERSHIP RULE: each user builds ONLY their own side ─────────────

    /** Create a game owned by $owner with a temporary home side; challenge $opponent; accept. */
    private function makeChallengedGame(User $owner, User $opponent, bool $toTeam = false): array
    {
        $this->homePlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players' => $this->homePlayers->pluck('id')->all(),
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        $payload = $toTeam ? ['team_id' => $this->makeTeam($opponent, 'Rival')->id] : ['user_id' => $opponent->id];

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/opponent/challenge", $payload)
            ->assertStatus(201);

        return [$game, \App\Models\GameChallenge::latest('id')->first()];
    }

    // 7. The home owner can NOT choose the opponent's players — his picks
    // always land on HIS OWN side (home), the away side stays untouched.
    public function test_home_owner_cannot_choose_opponent_players(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game] = $this->makeChallengedGame($owner, $opponent);

        $awayCandidates = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        // Even after challenging, the owner's pick-players call rebuilds HOME.
        $newHomePlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'home',
                'player_stat_ids' => $newHomePlayers->pluck('id')->all(),
            ])
            ->assertStatus(200);

        // No AWAY side exists — the opponent's side can only be built by the
        // opponent (challenge was sent, never accepted).
        $this->assertNull(\App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first());

        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $this->assertEquals(
            $newHomePlayers->pluck('id')->sort()->values()->all(),
            \App\Models\GamePlayer::where('game_team_id', $home->id)->pluck('player_stat_id')->sort()->values()->all()
        );
        $this->assertDatabaseMissing('game_players', ['player_stat_id' => $awayCandidates->first()->id]);
    }

    // 8. The opponent can NOT modify the home side
    public function test_opponent_cannot_modify_home_side(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game, $challenge] = $this->makeChallengedGame($owner, $opponent);

        // A user-challenge needs the opponent's own players on accept — a bare
        // accept is rejected (the opponent must decide how to build his side).
        $hisPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", ['status' => 'accepted'])
            ->assertStatus(422);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", [
                'status'          => 'accepted',
                'player_stat_ids' => $hisPlayers->pluck('id')->all(),
            ])
            ->assertStatus(200);

        // Opponent picks his own away players…
        $awayPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'away',
                'player_stat_ids' => $awayPlayers->pluck('id')->all(),
            ])
            ->assertStatus(200);

        // …but may NOT rebuild the HOME side nor edit the game.
        $this->actingAs($opponent, 'sanctum')
            ->putJson("/api/games/{$game->id}", [
                'home_players'   => $awayPlayers->pluck('id')->all(),
                'home_team_name' => 'Hijacked',
            ])
            ->assertStatus(404);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'home',
                'player_stat_ids' => PlayerStat::factory()->count(1)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ])
            ->assertStatus(404);

        // Opponent's RE-submission with side=away replaces ONLY the away side.
        $replacement = PlayerStat::factory()->count(1)->create(['sport_category_id' => 1]);
        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'away',
                'player_stat_ids' => $replacement->pluck('id')->all(),
            ])
            ->assertStatus(200);

        // Away now holds exactly the 1 replacement player.
        $this->assertEquals(
            1,
            \App\Models\GamePlayer::where('game_id', $game->id)
                ->whereHas('gameTeam', fn($q) => $q->where('side', 'away'))->count()
        );

        // HOME side was never touched by the opponent: same 2 original players.
        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $this->assertNotEquals('Hijacked', $home->name);
        $this->assertEquals(2, \App\Models\GamePlayer::where('game_team_id', $home->id)->count());
    }

    // 4. Opponent with a permanent team accepts → away PERMANENT (roster copied)
    public function test_opponent_uses_permanent_team_on_accept(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game, $challenge] = $this->makeChallengedGame($owner, $opponent);

        $rival = $this->makeTeam($opponent, 'Rival FC');
        $this->attachMembers($rival);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", [
                'status'  => 'accepted',
                'team_id' => $rival->id, // opponent explicitly chooses his permanent team
            ])
            ->assertStatus(200);

        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();
        $this->assertNotNull($away);
        $this->assertEquals('permanent', $away->type);
        $this->assertEquals($rival->id, $away->team_id);
        $this->assertEquals($opponent->id, $away->owner_id);
        $this->assertEquals(2, \App\Models\GamePlayer::where('game_team_id', $away->id)->where('status', 'joined')->count());
    }

    // 5. Opponent with a permanent team chooses players instead (override)
    public function test_opponent_can_override_team_challenge_with_players(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game, $challenge] = $this->makeChallengedGame($owner, $opponent, toTeam: true);

        $rival = $this->makeTeam($opponent, 'Rival FC'); // he HAS a team…
        $this->attachMembers($rival);
        $pickedPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        // …but accepts with picked players → away must be TEMPORARY.
        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", [
                'status'          => 'accepted',
                'player_stat_ids' => $pickedPlayers->pluck('id')->all(),
                'name'            => 'My Pickup Five',
            ])
            ->assertStatus(200);

        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();
        $this->assertEquals('temporary', $away->type);
        $this->assertNull($away->team_id);
        $this->assertEquals($opponent->id, $away->owner_id);
        $this->assertDatabaseHas('game_player_requests', ['game_id' => $game->id, 'status' => 'pending']);
    }

    // 6. Opponent without any team → away TEMPORARY via his own players
    public function test_opponent_without_team_builds_temporary_away_side(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser(); // owns NO team
        [$game, $challenge] = $this->makeChallengedGame($owner, $opponent);

        $pickedPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", [
                'status'          => 'accepted',
                'player_stat_ids' => $pickedPlayers->pluck('id')->all(),
            ])
            ->assertStatus(200);

        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();
        $this->assertEquals('temporary', $away->type);
        $this->assertNull($away->team_id);
        $this->assertDatabaseCount('teams', 0);
    }

    // 10. Temp vs temp through two INDEPENDENT player-request flows
    public function test_temp_vs_temp_two_independent_request_flows(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game, $challenge] = $this->makeChallengedGame($owner, $opponent);

        $awayPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", [
                'status'          => 'accepted',
                'player_stat_ids' => $awayPlayers->pluck('id')->all(),
            ])
            ->assertStatus(200);

        // Side A request belongs to an owner-side player; side B to an opponent-side one.
        $homeRequest = \App\Models\GamePlayerRequest::where('player_stat_id', $this->homePlayers->first()->id)->first();
        $awayRequest = \App\Models\GamePlayerRequest::where('player_stat_id', $awayPlayers->first()->id)->first();

        // Each player answers only HIS side's request.
        $this->actingAs(User::find($this->homePlayers->first()->user_id), 'sanctum')
            ->postJson("/api/games/player-requests/{$homeRequest->id}/respond", ['status' => 'accepted'])
            ->assertStatus(200);

        $this->actingAs(User::find($awayPlayers->first()->user_id), 'sanctum')
            ->postJson("/api/games/player-requests/{$awayRequest->id}/respond", ['status' => 'rejected'])
            ->assertStatus(200);

        $this->assertDatabaseHas('game_players', ['player_stat_id' => $this->homePlayers->first()->id, 'status' => 'joined']);
        $this->assertDatabaseHas('game_players', ['player_stat_id' => $awayPlayers->first()->id, 'status' => 'declined']);
        $this->assertDatabaseCount('teams', 0);

        // Both sides are temporary game teams owned by different users.
        $sides = \App\Models\GameTeam::where('game_id', $game->id)->orderBy('side')->get();
        $this->assertEquals(['away', 'home'], $sides->pluck('side')->all());
        $this->assertEquals('temporary', $sides->where('side', 'home')->first()->type);
        $this->assertEquals('temporary', $sides->where('side', 'away')->first()->type);
        $this->assertEquals($owner->id, $sides->where('side', 'home')->first()->owner_id);
        $this->assertEquals($opponent->id, $sides->where('side', 'away')->first()->owner_id);
    }

    // 1/2/3 are covered at HOME by: permanent roster copy, picked players with a
    // team owned, and no-team user — plus the explicit "cannot challenge yourself" guard.
    public function test_owner_cannot_challenge_himself_as_user(): void
    {
        $owner = $this->authUser();
        [$game] = $this->makeChallengedGame($owner, $this->authUser());

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/opponent/challenge", ['user_id' => $owner->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot challenge yourself.');
    }

    // ── SELF-ORGANIZED MATCH: the game owner builds BOTH sides ──────────

    /** Owner picks BOTH sides himself → two independent temporary game teams. */
    public function test_owner_builds_both_temporary_sides(): void
    {
        $owner = $this->authUser();
        $homePlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $awayPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players' => $homePlayers->pluck('id')->all(),
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        // AWAY side picked by the SAME user.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'away',
                'player_stat_ids' => $awayPlayers->pluck('id')->all(),
                'name'            => 'Away Five',
            ])
            ->assertStatus(200);

        // Exactly TWO game_teams, one per side, both temporary & team-less.
        $this->assertDatabaseCount('game_teams', 2);
        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();

        foreach ([$home, $away] as $sideTeam) {
            $this->assertEquals('temporary', $sideTeam->type);
            $this->assertNull($sideTeam->team_id);
            $this->assertEquals($owner->id, $sideTeam->owner_id);
        }

        // Players attached to the CORRECT side only.
        $this->assertEquals(
            $homePlayers->pluck('id')->sort()->values()->all(),
            \App\Models\GamePlayer::where('game_team_id', $home->id)->pluck('player_stat_id')->sort()->values()->all()
        );
        $this->assertEquals(
            $awayPlayers->pluck('id')->sort()->values()->all(),
            \App\Models\GamePlayer::where('game_team_id', $away->id)->pluck('player_stat_id')->sort()->values()->all()
        );

        // No permanent teams record was ever created.
        $this->assertDatabaseCount('teams', 0);
    }

    /** Home players are NEVER attached to away and vice versa (independence). */
    public function test_home_and_away_players_never_mix(): void
    {
        $owner = $this->authUser();
        $homePlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $awayPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['home_players' => $homePlayers->pluck('id')->all()]))
            ->assertStatus(201);
        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side' => 'away',
                'player_stat_ids' => $awayPlayers->pluck('id')->all(),
            ])
            ->assertStatus(200);

        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();

        foreach ($awayPlayers as $player) {
            $this->assertDatabaseMissing('game_players', ['game_team_id' => $home->id, 'player_stat_id' => $player->id]);
        }
        foreach ($homePlayers as $player) {
            $this->assertDatabaseMissing('game_players', ['game_team_id' => $away->id, 'player_stat_id' => $player->id]);
        }
    }

    /** Both sides filled by the owner still deliver two independent invites. */
    public function test_owner_built_sides_send_invites_for_both_sides(): void
    {
        $owner = $this->authUser();
        $homePlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $awayPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['home_players' => $homePlayers->pluck('id')->all()]))
            ->assertStatus(201);
        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side' => 'away',
                'player_stat_ids' => $awayPlayers->pluck('id')->all(),
                'message' => 'Let’s play!',
            ])
            ->assertStatus(200);

        // 2 home + 2 away pending invites, all initiated by the owner.
        $this->assertDatabaseCount('game_player_requests', 4);
        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();
        $this->assertEquals(2, \App\Models\GamePlayerRequest::where('game_team_id', $home->id)->where('status', 'pending')->count());
        $this->assertEquals(2, \App\Models\GamePlayerRequest::where('game_team_id', $away->id)->where('status', 'pending')->count());

        // A home player accepts; an away player declines — independent flows.
        $homeRequest = \App\Models\GamePlayerRequest::where('player_stat_id', $homePlayers->first()->id)->first();
        $this->actingAs(User::find($homePlayers->first()->user_id), 'sanctum')
            ->postJson("/api/games/player-requests/{$homeRequest->id}/respond", ['status' => 'accepted'])
            ->assertStatus(200);
        $this->assertDatabaseHas('game_players', ['player_stat_id' => $homePlayers->first()->id, 'status' => 'joined']);

        $awayRequest = \App\Models\GamePlayerRequest::where('player_stat_id', $awayPlayers->first()->id)->first();
        $this->actingAs(User::find($awayPlayers->first()->user_id), 'sanctum')
            ->postJson("/api/games/player-requests/{$awayRequest->id}/respond", ['status' => 'rejected'])
            ->assertStatus(200);
        $this->assertDatabaseHas('game_players', ['player_stat_id' => $awayPlayers->first()->id, 'status' => 'declined']);
    }

    /** side=away for a non-owner with no accepted challenge is rejected. */
    public function test_stranger_cannot_build_any_side(): void
    {
        $owner = $this->authUser();
        $stranger = $this->authUser();
        $players = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['home_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all()]))
            ->assertStatus(201);
        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        foreach (['home', 'away'] as $side) {
            $this->actingAs($stranger, 'sanctum')
                ->postJson("/api/games/{$game->id}/players", [
                    'side' => $side,
                    'player_stat_ids' => $players->pluck('id')->all(),
                ])
                ->assertStatus(404);
        }

        // The home side was not replaced by the stranger's attempts.
        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $this->assertNotNull($home);
        $this->assertEquals(2, \App\Models\GamePlayer::where('game_team_id', $home->id)->count());
        $this->assertNull(\App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first());
    }

    /** An invalid side value is a validation error (422). */
    public function test_invalid_side_is_rejected(): void
    {
        $owner = $this->authUser();
        $players = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['home_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all()]))
            ->assertStatus(201);
        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side' => 'bench',
                'player_stat_ids' => $players->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['results' => ['side']]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'player_stat_ids' => $players->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['results' => ['side']]);
    }

    /** Duplicate player_stat_ids within one submission are not duplicated. */
    public function test_duplicate_player_ids_are_not_duplicated(): void
    {
        $owner = $this->authUser();
        $players = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload(['home_players' => $players->pluck('id')->all()]))
            ->assertStatus(201);
        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        // distinct rule → validation error before anything is written.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'away',
                'player_stat_ids' => [$players->first()->id, $players->first()->id],
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['results' => ['player_stat_ids.0']]);

        // The same player on BOTH sides is allowed by the API (he can only be
        // confirmed through one invite, one side at a time); duplicates WITHIN
        // a side are impossible thanks to unique(game_team_id, player_stat_id)
        // + the distinct rule above.
        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $this->assertEquals(2, \App\Models\GamePlayer::where('game_team_id', $home->id)->count());
    }

    /** After the owner hands the away side to a challenged opponent, the
     *  owner may no longer overwrite the away side himself. */
    public function test_owner_cannot_overwrite_away_after_challenge_accepted(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game, $challenge] = $this->makeChallengedGame($owner, $opponent);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", [
                'status'          => 'accepted',
                'player_stat_ids' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ])
            ->assertStatus(200);

        // Owner tries to rebuild the opponent's away side → refused.
        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/games/{$game->id}/players", [
                'side'            => 'away',
                'player_stat_ids' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only the challenged opponent can build the away side.');
    }

    // ── AWAY SIDE IN THE SAME CREATE REQUEST ────────────────────────────

    /** One POST builds BOTH temporary sides + invites for all players. */
    public function test_create_with_away_players_builds_both_sides(): void
    {
        $owner = $this->authUser();
        $homePlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);
        $awayPlayers = PlayerStat::factory()->count(2)->create(['sport_category_id' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players'   => $homePlayers->pluck('id')->all(),
                'home_team_name' => 'Home Five',
                'away_players'   => $awayPlayers->pluck('id')->all(),
                'away_team_name' => 'Away Five',
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        // Exactly 2 game_teams: home + away, both temporary, team-less, owner-owned.
        $this->assertDatabaseCount('game_teams', 2);
        $home = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();

        foreach ([$home, $away] as $sideTeam) {
            $this->assertEquals('temporary', $sideTeam->type);
            $this->assertNull($sideTeam->team_id);
            $this->assertEquals($owner->id, $sideTeam->owner_id);
        }
        $this->assertEquals('Home Five', $home->name);
        $this->assertEquals('Away Five', $away->name);

        // Players attached to the CORRECT side only.
        $this->assertEquals(
            $homePlayers->pluck('id')->sort()->values()->all(),
            \App\Models\GamePlayer::where('game_team_id', $home->id)->pluck('player_stat_id')->sort()->values()->all()
        );
        $this->assertEquals(
            $awayPlayers->pluck('id')->sort()->values()->all(),
            \App\Models\GamePlayer::where('game_team_id', $away->id)->pluck('player_stat_id')->sort()->values()->all()
        );

        // Invites for every player on both sides.
        $this->assertDatabaseCount('game_player_requests', 4);
        $this->assertEquals(2, \App\Models\GamePlayerRequest::where('game_team_id', $home->id)->where('status', 'pending')->count());
        $this->assertEquals(2, \App\Models\GamePlayerRequest::where('game_team_id', $away->id)->where('status', 'pending')->count());

        // No permanent teams record.
        $this->assertDatabaseCount('teams', 0);
    }

    /** away_team_id in the create request → away PERMANENT (roster copied). */
    public function test_create_with_away_team_builds_permanent_away_side(): void
    {
        $owner = $this->authUser();
        $rival = $this->makeTeam($owner, 'Rival FC');
        $this->attachMembers($rival);

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
                'away_team_id' => $rival->id,
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $owner->id)->latest('id')->first();
        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();

        $this->assertNotNull($away);
        $this->assertEquals('permanent', $away->type);
        $this->assertEquals($rival->id, $away->team_id);
        $this->assertEquals(2, \App\Models\GamePlayer::where('game_team_id', $away->id)->where('status', 'joined')->count());
    }

    /** away_team_id + away_players together → 422 (mutually exclusive). */
    public function test_create_rejects_away_team_and_away_players_together(): void
    {
        $owner = $this->authUser();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
                'away_team_id' => $this->makeTeam($owner, 'Rival FC')->id,
                'away_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['results' => ['away_team_id']]);
    }

    /** A non-owner cannot smuggle an away side into a game update. */
    public function test_update_rejects_away_side_from_non_owner(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game] = $this->makeChallengedGame($owner, $opponent);

        $this->actingAs($opponent, 'sanctum')
            ->putJson("/api/games/{$game->id}", [
                'away_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ])
            ->assertStatus(404);
    }

    /** Rebuilding via PUT keeps ownership: away after an accepted challenge is locked. */
    public function test_update_cannot_overwrite_away_after_challenge_accepted(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();
        [$game, $challenge] = $this->makeChallengedGame($owner, $opponent);

        $this->actingAs($opponent, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", [
                'status'          => 'accepted',
                'player_stat_ids' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ])
            ->assertStatus(200);

        $this->actingAs($owner, 'sanctum')
            ->putJson("/api/games/{$game->id}", [
                'away_players' => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Only the challenged opponent can build the away side.');
    }

    // ── CHALLENGE IN THE SAME CREATE REQUEST ────────────────────────────

    /** ONE POST: game + permanent home team + team challenge (201). */
    public function test_create_with_challenge_team_sends_challenge(): void
    {
        $owner = $this->authUser();
        $rivalOwner = $this->authUser();
        $home = $this->makeTeam($owner, 'Atlas Lions FC');
        $this->attachMembers($home);
        $rival = $this->makeTeam($rivalOwner, 'Casablanca United');
        $this->attachMembers($rival); // bare accept copies this roster

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_team_id'      => $home->id,
                'challenge_team_id' => $rival->id,
                'message'           => 'Friday 18:00?',
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        // Home side AND challenge were created in the same transaction.
        $homeGameTeam = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'home')->first();
        $this->assertNotNull($homeGameTeam);

        $this->assertDatabaseHas('game_challenges', [
            'game_id'                 => $game->id,
            'challenging_game_team_id' => $homeGameTeam->id,
            'challenged_team_id'      => $rival->id,
            'challenged_user_id'      => $rivalOwner->id, // team owner auto-recorded
            'status'                  => 'pending',
            'message'                 => 'Friday 18:00?',
        ]);

        // The rival owner can see and answer the challenge.
        $challenge = \App\Models\GameChallenge::where('game_id', $game->id)->first();
        $this->actingAs($rivalOwner, 'sanctum')
            ->postJson("/api/games/challenges/{$challenge->id}/respond", ['status' => 'accepted'])
            ->assertStatus(200);

        // Bare accept of a team challenge → permanent away side.
        $away = \App\Models\GameTeam::where('game_id', $game->id)->where('side', 'away')->first();
        $this->assertEquals('permanent', $away->type);
        $this->assertEquals($rival->id, $away->team_id);
    }

    /** ONE POST: game + temporary home players + user challenge. */
    public function test_create_with_challenge_user_sends_challenge(): void
    {
        $owner = $this->authUser();
        $opponent = $this->authUser();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players'      => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
                'challenge_user_id' => $opponent->id,
            ]))
            ->assertStatus(201);

        $game = Game::where('user_id', $owner->id)->latest('id')->first();

        $this->assertDatabaseHas('game_challenges', [
            'game_id'            => $game->id,
            'challenged_user_id' => $opponent->id,
            'status'             => 'pending',
        ]);
    }

    /** team_id + user_id challenges together → 422. */
    public function test_create_rejects_challenge_team_and_user_together(): void
    {
        $owner = $this->authUser();

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'challenge_team_id' => $this->makeTeam($this->authUser(), 'Rivals')->id,
                'challenge_user_id' => $this->authUser()->id,
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['results' => ['challenge_team_id']]);
    }

    /** Challenge + owner-built away side in one payload → 422 (away belongs to the opponent). */
    public function test_create_rejects_challenge_with_away_side(): void
    {
        $owner = $this->authUser();
        $rival = $this->makeTeam($this->authUser(), 'Rivals');

        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'challenge_team_id' => $rival->id,
                'away_players'      => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
            ]))
            ->assertStatus(422)
            ->assertJsonStructure(['results' => ['challenge_team_id']]);
    }

    /** A failed challenge in the create call rolls back the WHOLE draft. */
    public function test_create_with_invalid_challenge_creates_nothing(): void
    {
        $owner = $this->authUser();
        $gamesBefore = Game::count();

        // Owner challenges himself → GameService::challenge() throws → rollback.
        $this->actingAs($owner, 'sanctum')
            ->postJson('/api/games', $this->validPayload([
                'home_players'      => PlayerStat::factory()->count(2)->create(['sport_category_id' => 1])->pluck('id')->all(),
                'challenge_user_id' => $owner->id,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot challenge yourself.');

        $this->assertEquals($gamesBefore, Game::count());
        $this->assertDatabaseCount('game_teams', 0);
        $this->assertDatabaseCount('game_player_requests', 0);
    }
}
