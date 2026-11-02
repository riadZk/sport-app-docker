<?php

namespace App\Http\Controllers\Api\PlayerStat;

use App\Http\Controllers\Api\ApiController;
use App\Models\PlayerStat;
use App\Models\SportCategory;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlayerStatController extends ApiController
{
    /**
     * Get ALL PlayerStats (public, no auth required).
     * Optional: ?position=RB — matching players appear first.
     */
    public function all(Request $request)
    {
        $request->validate([
            'position' => 'nullable|string|max:10',
        ]);

        $query = PlayerStat::query()->with('user:id,first_name,last_name');

        // If authenticated, exclude the user's own stats
        if (Auth::check()) {
            $query->where('user_id', '!=', Auth::id());
        }

        if ($request->filled('position')) {
            $position = $request->position;
            $query->orderByRaw('CASE WHEN primary_position = ? OR secondary_position = ? THEN 0 ELSE 1 END', [$position, $position]);
        }

        $stats = $query->paginate($request->integer('per_page', 15));

        return $this->jsonResponse($stats, 'Player stats retrieved successfully.');
    }

    public function myStats(Request $request)
    {
        $request->validate([
            'sport_category' => 'nullable|string|max:100',
        ]);

        $sportCategoryName = $request->input('sport_category', 'football');

        $sportCategory = SportCategory::where('name', 'like', '%' . $sportCategoryName . '%')->first();

        if (!$sportCategory) {
            return $this->jsonResponse(null, 'Sport category not found.', 404);
        }

        $stat = Auth::user()->playerStats()->with('user')->where('sport_category_id', $sportCategory->id)->first();

        if (!$stat) {
            return $this->jsonResponse(null, 'No player stats found for this sport category.', 404);
        }

        // Active team membership in THIS sport category — queried separately so
        // `teamMemberships` never leaks into the response payload.
        $membership = TeamMember::where('player_id', $stat->id)
            ->where('status', 'active')
            ->whereHas('team', fn($t) => $t->where('sport_category_id', $sportCategory->id))
            ->with('team:id,name,short_name,logo_type,default_logo_id')
            ->first();

        $team = $membership?->team;

        return $this->jsonResponse(array_merge($stat->toArray(), [
            'team' => $team ? [
                'id'         => $team->id,
                'name'       => $team->name,
                'short_name' => $team->short_name,
                'logo_url'   => $team->logo_url,
            ] : null,
        ]), 'Player stats retrieved successfully.');
    }

    /**
     * Get all PlayerStats for a given team (active members only).
     * Optional: ?position=RB — matching players appear first.
     */
    public function teamStats(Team $team)
    {

        $stats = PlayerStat::whereHas('teamMemberships', function ($q) use ($team) {
            $q->where('team_id', $team->id)->where('status', 'active');
        })->get();


        return $this->jsonResponse($stats, 'Team player stats retrieved successfully.');
    }

    /**
     * PATCH /player-stats/{playerStat}/position
     * Update only position fields of the authenticated user's player stat.
     */
    public function updatePosition(Request $request, PlayerStat $playerStat)
    {
        if ($playerStat->user_id !== Auth::id()) {
            return $this->jsonResponse(null, 'Unauthorized.', 403);
        }

        $validated = $request->validate([
            'primary_position'   => ['sometimes', 'string', 'max:10'],
            'secondary_position' => ['sometimes', 'nullable', 'string', 'max:10'],
            'prefered_foot'      => ['sometimes', 'in:left,right,both'],
        ]);

        $playerStat->update($validated);

        return $this->jsonResponse($playerStat->fresh(), 'Position updated successfully.');
    }
}
