<?php

namespace App\Http\Controllers\Api\Team;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Team\BulkInvitePlayersRequest;
use App\Http\Requests\Team\InvitePlayerRequest;
use App\Http\Requests\Team\RequestJoinTeamRequest;
use App\Http\Requests\Team\RespondToInvitationRequest;
use App\Services\BaseNotificationService;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeamInvitationController extends ApiController
{
    public function __construct(
        protected BaseNotificationService $notifier,
    ) {}
    /**
     * Team admin sends an invitation to a player (by player_stat_id).
     */
    public function invite(InvitePlayerRequest $request, Team $team)
    {
        $this->authorizeTeamOwner($request, $team);

        $playerStat = PlayerStat::findOrFail($request->player_stat_id);

        $this->abortIfActiveMember($team->id, $playerStat->id);
        $this->abortIfPendingExists($team->id, $playerStat->id);

        $invitation = TeamInvitation::create([
            'team_id'        => $team->id,
            'player_stat_id' => $playerStat->id,
            'initiated_by'   => $request->user()->id,
            'type'           => 'invitation',
            'message'        => $request->message,
        ]);

        $invitation->load(['team', 'playerStat.user']);

        $this->notifier->notifyPlayerInvited($invitation);

        return $this->jsonResponse(
            $invitation,
            'Invitation sent successfully.',
            201
        );
    }

    /**
     * Send invitations to multiple players at once (e.g. during team creation).
     * Players who are already active members or have a pending invitation are skipped.
     */
    public function bulkInvite(BulkInvitePlayersRequest $request, Team $team)
    {
        $this->authorizeTeamOwner($request, $team);

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
     * Player sends a join request to a team using one of their player_stats.
     */
    public function requestJoin(RequestJoinTeamRequest $request, Team $team)
    {
        // Ensure the player_stat belongs to the authenticated user
        $playerStat = PlayerStat::where('id', $request->player_stat_id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Team owner cannot send a join request to their own team
        if ($team->user_id === $request->user()->id) {
            return $this->jsonResponse(null, 'You cannot send a join request to your own team.', 422);
        }

        $this->abortIfActiveMember($team->id, $playerStat->id);
        $this->abortIfPendingExists($team->id, $playerStat->id);

        $invitation = TeamInvitation::create([
            'team_id'        => $team->id,
            'player_stat_id' => $playerStat->id,
            'initiated_by'   => $request->user()->id,
            'type'           => 'request',
            'message'        => $request->message,
        ]);

        $invitation->load(['team', 'playerStat']);

        $this->notifier->notifyAdminJoinRequest($invitation);

        return $this->jsonResponse(
            $invitation,
            'Join request sent successfully.',
            201
        );
    }

    /**
     * List all invitations and join requests for a team (admin only).
     */
    public function teamInvitations(Request $request, Team $team)
    {
        $this->authorizeTeamOwner($request, $team);

        $invitations = TeamInvitation::where('team_id', $team->id)
            ->with(['playerStat.user', 'initiatedBy'])
            ->latest()
            ->get();

        return $this->jsonResponse($invitations, 'Team invitations retrieved successfully.');
    }

    /**
     * List all invitations and join requests relevant to the authenticated user as a player.
     */
    public function myInvitations(Request $request)
    {
        $playerStatIds = $request->user()->playerStats()->pluck('id');

        $invitations = TeamInvitation::whereIn('player_stat_id', $playerStatIds)
            ->with(['team', 'playerStat', 'initiatedBy'])
            ->latest()
            ->get();

        return $this->jsonResponse($invitations, 'My invitations retrieved successfully.');
    }

    /**
     * Accept or reject a pending invitation/request.
     *
     * - If type = 'invitation': only the player (owner of player_stat) can respond.
     * - If type = 'request':   only the team owner can respond.
     */
    public function respond(RespondToInvitationRequest $request, TeamInvitation $invitation)
    {
        if ($invitation->status !== 'pending') {
            return $this->jsonResponse(null, 'This invitation is no longer pending.', 422);
        }

        $user = $request->user();

        if ($invitation->type === 'invitation') {
            if ($invitation->playerStat->user_id !== $user->id) {
                $this->abort403();
            }
        } else {
            if ($invitation->team->user_id !== $user->id) {
                $this->abort403();
            }
        }

        if ($request->action === 'accept') {
            DB::transaction(function () use ($invitation) {
                $invitation->update(['status' => 'accepted']);

                TeamMember::create([
                    'player_id'  => $invitation->player_stat_id,
                    'team_id'    => $invitation->team_id,
                    'role'       => 'player',
                    'status'     => 'active',
                    'start_date' => now()->toDateString(),
                ]);
            });

            $this->notifier->notifyAccepted($invitation);

            return $this->jsonResponse(null, 'Invitation accepted. Player has joined the team.');
        }

        $invitation->update(['status' => 'rejected']);

        $this->notifier->notifyRejected($invitation);

        return $this->jsonResponse(null, 'Invitation rejected.');
    }

    /**
     * Cancel a pending invitation or join request.
     *
     * - If type = 'invitation': only the team owner (sender) can cancel.
     * - If type = 'request':   only the player (sender) can cancel.
     */
    public function cancel(Request $request, TeamInvitation $invitation)
    {
        if ($invitation->status !== 'pending') {
            return $this->jsonResponse(null, 'Only pending invitations can be cancelled.', 422);
        }

        $user = $request->user();

        if ($invitation->type === 'invitation') {
            if ($invitation->team->user_id !== $user->id) {
                $this->abort403();
            }
        } else {
            if ($invitation->initiated_by !== $user->id) {
                $this->abort403();
            }
        }

        $invitation->update(['status' => 'cancelled']);

        $this->notifier->notifyCancelled($invitation);

        return $this->jsonResponse(null, 'Invitation cancelled successfully.');
    }

    // -------------------------------------------------------------------------

    private function authorizeTeamOwner(Request $request, Team $team): void
    {
        if ($team->user_id !== $request->user()->id) {
            abort(response()->json([
                'status'  => 403,
                'message' => 'You do not own this team.',
                'results' => null,
            ], 403));
        }
    }

    private function abortIfActiveMember(int $teamId, int $playerStatId): void
    {
        $exists = TeamMember::where('team_id', $teamId)
            ->where('player_id', $playerStatId)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            abort(response()->json([
                'status'  => 422,
                'message' => 'This player is already an active member of the team.',
                'results' => null,
            ], 422));
        }
    }

    private function abortIfPendingExists(int $teamId, int $playerStatId): void
    {
        $exists = TeamInvitation::where('team_id', $teamId)
            ->where('player_stat_id', $playerStatId)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            abort(response()->json([
                'status'  => 422,
                'message' => 'A pending invitation or request already exists for this player and team.',
                'results' => null,
            ], 422));
        }
    }

    private function abort403(): never
    {
        abort(response()->json([
            'status'  => 403,
            'message' => 'Unauthorized.',
            'results' => null,
        ], 403));
    }
}
