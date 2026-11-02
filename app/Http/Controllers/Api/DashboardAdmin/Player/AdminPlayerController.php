<?php

namespace App\Http\Controllers\Api\DashboardAdmin\Player;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\DashboardAdmin\CreatePlayerRequest;
use App\Http\Requests\DashboardAdmin\UpdatePlayerRequest;
use App\Models\User;
use Illuminate\Http\Request;

class AdminPlayerController extends ApiController
{
    public function createPlayer(CreatePlayerRequest $request)
    {
        $user = User::create([
            'first_name'   => $request->first_name,
            'last_name'    => $request->last_name,
            'username'     => $request->username,
            'email'        => $request->email,
            'phone_number' => $request->phone_number,
            'city'         => $request->city,
            'country'      => $request->country,
            'gender'       => $request->gender,
            'birthday'     => $request->birthday,
            'cin'          => $request->cin,
        ]);

        $user->playerStats()->create([
            'sport_category_id'  => 1,
            'primary_position'   => $request->primary_position,
            'secondary_position' => $request->secondary_position,
            'prefered_foot'      => $request->prefered_foot,
            'match_played'       => $request->match_played ?? 0,
            'nbr_goals'          => $request->nbr_goals ?? 0,
            'pac'                => $request->pac ?? 0,
            'dribble'            => $request->dribble ?? 0,
            'physique'           => $request->physique ?? 0,
            'shoot'              => $request->shoot ?? 0,
            'pass'               => $request->pass ?? 0,
            'assists'            => $request->assists ?? 0,
            'defence'            => $request->defence ?? 0,
        ]);

        if ($request->hasFile('picture')) {
            $user->addMediaFromRequest('picture')
                ->usingFileName('player_picture_' . $user->id . '_' . now()->timestamp . '.' . $request->file('picture')->getClientOriginalExtension())
                ->toMediaCollection('picture');
        }

        return $this->jsonResponse([
            'user'         => $user,
            'player_stats' => $user->playerStats,
        ], 'Player created successfully', 201);
    }

    public function getPlayers(Request $request)
    {
        $players = User::with('playerStats', 'media')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->when($request->team_id, function ($q, $teamId) {
                $q->whereHas('playerStats.teamMemberships', function ($q) use ($teamId) {
                    $q->where('team_id', $teamId)->where('status', 'active');
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15));

        return $this->jsonResponse($players, 'Players retrieved successfully');
    }

    public function detailPlayer($id)
    {
        $player = User::with('playerStats', 'media')->find($id);

        if (! $player) {
            return $this->jsonResponse(
                null,
                'Player not found',
                404
            );
        }

        return $this->jsonResponse($player, 'Player details retrieved successfully');
    }

    public function updatePlayer(UpdatePlayerRequest $request, $id)
    {
        $player = User::find($id);

        if (! $player) {
            return $this->jsonResponse(null, 'Player not found', 404);
        }

        $data = $request->validated();

        $userFields = array_intersect_key($data, array_flip([
            'first_name', 'last_name', 'username', 'email', 'phone_number',
            'cin', 'city', 'country', 'gender', 'birthday',
        ]));

        if (! empty($userFields)) {
            $player->update($userFields);
        }

        $statFields = array_intersect_key($data, array_flip([
            'primary_position', 'secondary_position', 'prefered_foot',
            'match_played', 'nbr_goals', 'pac', 'dribble', 'physique',
            'shoot', 'pass', 'assists', 'defence',
        ]));

        if (! empty($statFields)) {
            $playerStat = $player->playerStats()->orderBy('id')->first();

            if ($playerStat) {
                $playerStat->update($statFields);
            } else {
                $player->playerStats()->create(array_merge(['sport_category_id' => 1], $statFields));
            }
        }

        return $this->jsonResponse([
            'user'         => $player->fresh(),
            'player_stats' => $player->fresh()->playerStats,
        ], 'Player updated successfully');
    }

    public function updatePlayerPicture(Request $request, $id)
    {
        $player = User::find($id);

        if (! $player) {
            return $this->jsonResponse(null, 'Player not found', 404);
        }

        $request->validate([
            'picture' => ['required', 'image', 'max:2048'],
        ]);

        $player->clearMediaCollection('picture');

        $player->addMediaFromRequest('picture')
            ->usingFileName('player_picture_' . $player->id . '_' . now()->timestamp . '.' . $request->file('picture')->getClientOriginalExtension())
            ->toMediaCollection('picture');

        return $this->jsonResponse(
            ['picture_url' => $player->picture_url],
            'Player picture updated successfully'
        );
    }

    public function deletePlayer($id)
    {
        $player = User::find($id);

        if (! $player) {
            return $this->jsonResponse(null, 'Player not found', 404);
        }

        $player->delete();

        return $this->jsonResponse(null, 'Player deleted successfully');
    }
}
