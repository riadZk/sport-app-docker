<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The sport category all seeded teams belong to (Football).
     */
    private const SPORT_CATEGORY_ID = 1;

    /**
     * Role name assigned to every user created by this seeder.
     */
    private const USER_ROLE = 'player';

    /**
     * Create 5 teams for sport_category_id = 1, each with its own owner
     * user, 6 active players (1 captain + 5 players) and a jersey color set.
     */
    public function run(): void
    {
        $teams = [
            ['name' => 'Atlas Lions FC',  'short_name' => 'ATL', 'city' => 'Casablanca', 'address' => 'Stade Mohammed V, Casablanca',       'match_type' => '5v5',  'formation' => '2-2',   'primary_color' => '#C1272D', 'secondary_color' => '#006233'],
            ['name' => 'Casablanca United', 'short_name' => 'CBU', 'city' => 'Casablanca', 'address' => 'Complexe Sportif, Casablanca',      'match_type' => '5v5',  'formation' => '1-2-2', 'primary_color' => '#1D4ED8', 'secondary_color' => '#FFFFFF'],
            ['name' => 'Rabat Stars',     'short_name' => 'RBS', 'city' => 'Rabat',      'address' => 'Stade Prince Moulay, Rabat',        'match_type' => '7v7',  'formation' => '3-3',   'primary_color' => '#047857', 'secondary_color' => '#FBBF24'],
            ['name' => 'Marrakech Falcons', 'short_name' => 'MRF', 'city' => 'Marrakech',  'address' => 'Stade de Marrakech, Marrakech',     'match_type' => '6v6',  'formation' => '2-1-2', 'primary_color' => '#B45309', 'secondary_color' => '#1F2937'],
            ['name' => 'Tangier Titans',  'short_name' => 'TGT', 'city' => 'Tangier',    'address' => 'Stade Ibn Batouta, Tangier',        'match_type' => '5v5',  'formation' => '2-2',   'primary_color' => '#6D28D9', 'secondary_color' => '#F9FAFB'],
        ];

        // Positions cycled per team member (5v5 friendly).
        $positions = ['GK', 'DEF', 'DEF', 'MID', 'FWD'];

        foreach ($teams as $index => $data) {
            // One owner per team — unique emails/usernames come from the factory.
            $owner = User::factory()->create([
                'first_name' => 'Owner',
                'last_name'  => $data['short_name'],
            ]);
            $this->assignPlayerRole($owner);

            $team = Team::create($data + [
                'user_id'           => $owner->id,
                'sport_category_id' => self::SPORT_CATEGORY_ID,
                'logo_type'         => 'default',
                'default_logo_id'   => (string) ($index + 1),
            ]);

            // Squad: 5 active members, first one is the captain.
            foreach ($positions as $i => $position) {
                $player = User::factory()->create([
                    'first_name' => 'Player',
                    'last_name'  => $data['short_name'] . '-' . ($i + 1),
                ]);
                $this->assignPlayerRole($player);

                $player->playerStats()->create([
                    'sport_category_id' => self::SPORT_CATEGORY_ID,
                    'primary_position'  => $position,
                    'prefered_foot'     => ['left', 'right', 'both'][$i % 3],
                ]);

                TeamMember::create([
                    'player_id'    => $player->playerStats->first()->id,
                    'team_id'      => $team->id,
                    'role'         => $i === 0 ? 'captain' : 'player',
                    'position'     => $position,
                    'shirt_number' => $i + 1,
                    'status'       => 'active',
                    'start_date'   => now()->toDateString(),
                ]);
            }

            $this->command?->info("  ✔ Team #{$team->id}: {$team->name} (owner: {$owner->email})");
        }
    }

    /**
     * Attach the configured role to a user without duplicating the pivot row.
     */
    private function assignPlayerRole(User $user): void
    {
        $role = Role::where('name', self::USER_ROLE)->first();

        if ($role && ! $user->roles()->whereKey($role->id)->exists()) {
            $user->roles()->attach($role->id);
        }
    }
}
