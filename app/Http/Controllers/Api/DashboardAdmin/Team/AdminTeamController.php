<?php

namespace App\Http\Controllers\Api\DashboardAdmin\Team;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Team\AdminCreateTeamRequest;
use App\Http\Requests\Team\AdminBulkInvitePlayerRequest;
use App\Http\Requests\Team\AdminInvitePlayerRequest;
use App\Http\Requests\Team\AdminUpdateTeamLogoRequest;
use App\Http\Requests\Team\AdminUpdateTeamRequest;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\BaseNotificationService;
use Illuminate\Http\Request;

class AdminTeamController extends ApiController
{
    public function __construct(
        protected BaseNotificationService $notifier,
    ) {}

    /**
     * Create a new team on behalf of a specific user.
     */
    public function store(AdminCreateTeamRequest $request)
    {
        $data = $request->safe()->except(['logo', 'invitation_players', 'invitation_message']);

        if ($data['logo_type'] === 'upload') {
            $data['default_logo_id'] = null;
        }

        $team = Team::create($data);

        if ($request->logo_type === 'upload' && $request->hasFile('logo')) {
            $team->addMediaFromRequest('logo')
                ->usingFileName('team_logo_' . $team->id . '_' . now()->timestamp . '.' . $request->file('logo')->getClientOriginalExtension())
                ->toMediaCollection('logo');
        }

        // Add the team owner as captain if they have a matching player stat
        $owner = User::find($data['user_id']);

        if ($owner) {
            $playerStat = $owner->playerStats()
                ->where('sport_category_id', $team->sport_category_id)
                ->first();

            if ($playerStat) {
                TeamMember::create([
                    'player_id'  => $playerStat->id,
                    'team_id'    => $team->id,
                    'role'       => 'captain',
                    'status'     => 'active',
                    'start_date' => now()->toDateString(),
                ]);
            }
        }

        // Send invitations to players provided during team creation
        if ($request->filled('invitation_players')) {
            $this->notifier->sendBulkInvitations(
                $team,
                $data['user_id'],
                $request->invitation_players,
                $request->invitation_message,
            );
        }

        return $this->jsonResponse($team, 'Team created successfully', 201);
    }

    public function getTeams(Request $request)
    {
        $teams = Team::with('owner')
            ->withCount('activeMembers')
            ->when($request->search, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($request->input('per_page', 15));

        return $this->jsonResponse($teams, 'Teams retrieved successfully');
    }

    public function detailTeam($id)
    {
        $team = Team::with([
            'owner',
            'activeMembers.playerStat.user',
            'activeMembers.playerStat.sportCategory',
        ])->find($id);

        if (! $team) {
            return $this->jsonResponse(null, 'Team not found', 404);
        }

        $members = $team->activeMembers->map(function ($member) {
            $user = $member->playerStat?->user;

            return [
                'member_id'        => $member->id,
                'full_name'        => $user ? $user->first_name . ' ' . $user->last_name : null,
                'username'         => $user?->username,
                'picture_url'      => $user?->picture_url,
                'city'             => $user?->city,
                'gender'           => $user?->gender,
                'role'             => $member->role,
                'position'         => $member->position,
                'shirt_number'     => $member->shirt_number,
                'status'           => $member->status,
                'start_date'       => $member->start_date,
                'primary_position' => $member->playerStat?->primary_position,
                'match_played'     => $member->playerStat?->match_played,
                'nbr_goals'        => $member->playerStat?->nbr_goals,
                'assists'          => $member->playerStat?->assists,
                'sport_category'   => $member->playerStat?->sportCategory?->name,
            ];
        });

        $team->unsetRelation('activeMembers');
        $team['members'] = $members;
        return $this->jsonResponse([
            'team'    => $team
        ], 'Team retrieved successfully');
    }

    public function updateTeam(AdminUpdateTeamRequest $request, $id)
    {
        $team = Team::find($id);

        if (! $team) {
            return $this->jsonResponse(null, 'Team not found', 404);
        }

        $data = $request->safe()->except(['logo']);

        if (isset($data['logo_type']) && $data['logo_type'] === 'upload') {
            $data['default_logo_id'] = null;
        }

        $team->update($data);

        return $this->jsonResponse($team->fresh(), 'Team updated successfully');
    }

    public function updateTeamLogo(AdminUpdateTeamLogoRequest $request, $id)
    {
        $team = Team::find($id);

        if (! $team) {
            return $this->jsonResponse(null, 'Team not found', 404);
        }

        $team->clearMediaCollection('logo');
        $team->addMediaFromRequest('logo')
            ->usingFileName('team_logo_' . $team->id . '_' . now()->timestamp . '.' . $request->file('logo')->getClientOriginalExtension())
            ->toMediaCollection('logo');

        $team->update(['logo_type' => 'upload', 'default_logo_id' => null]);

        return $this->jsonResponse($team->fresh(), 'Team logo updated successfully');
    }

    public function deleteTeam($id)
    {
        $team = Team::find($id);

        if (! $team) {
            return $this->jsonResponse(null, 'Team not found', 404);
        }

        $team->delete();

        return $this->jsonResponse(null, 'Team deleted successfully');
    }

    /**
     * Send an invitation to a player on behalf of a team (admin only).
     */
    public function sendInvitation(AdminInvitePlayerRequest $request, $id)
    {
        $team = Team::find($id);

        if (! $team) {
            return $this->jsonResponse(null, 'Team not found', 404);
        }

        $playerStat = PlayerStat::findOrFail($request->player_stat_id);

        $isActiveMember = TeamMember::where('team_id', $team->id)
            ->where('player_id', $playerStat->id)
            ->where('status', 'active')
            ->exists();

        if ($isActiveMember) {
            return $this->jsonResponse(null, 'This player is already an active member of the team.', 422);
        }

        $hasPending = TeamInvitation::where('team_id', $team->id)
            ->where('player_stat_id', $playerStat->id)
            ->where('status', 'pending')
            ->exists();

        if ($hasPending) {
            return $this->jsonResponse(null, 'A pending invitation already exists for this player.', 422);
        }

        $invitation = TeamInvitation::create([
            'team_id'        => $team->id,
            'player_stat_id' => $playerStat->id,
            'initiated_by'   => $request->user()->id,
            'type'           => 'invitation',
            'message'        => $request->message,
        ]);

        $invitation->load(['team', 'playerStat.user']);

        $this->notifier->notifyPlayerInvited($invitation);

        return $this->jsonResponse($invitation, 'Invitation sent successfully.', 201);
    }

    /**
     * Send invitations to multiple players at once (admin only).
     * Players who are already active members or have a pending invitation are skipped.
     */
    public function bulkInvite(AdminBulkInvitePlayerRequest $request, $id)
    {
        $team = Team::find($id);

        if (! $team) {
            return $this->jsonResponse(null, 'Team not found', 404);
        }

        $result = $this->notifier->sendBulkInvitations(
            $team,
            $request->user()->id,
            $request->players,
            $request->message,
        );

        return $this->jsonResponse(
            $result,
            count($result['sent']) . ' invitation(s) sent, ' . count($result['skipped']) . ' skipped.',
            201
        );
    }

    /**
     * Remove a member (player) from a team.
     */
    public function deleteMember($id, $memberId)
    {
        $team = Team::find($id);

        if (! $team) {
            return $this->jsonResponse(null, 'Team not found', 404);
        }

        $member = $team->members()->find($memberId);

        if (! $member) {
            return $this->jsonResponse(null, 'Member not found in this team', 404);
        }

        $member->delete();

        return $this->jsonResponse(null, 'Member removed from team successfully');
    }
}
