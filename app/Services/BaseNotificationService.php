<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\Team\InvitationAcceptedNotification;
use App\Notifications\Team\InvitationCancelledNotification;
use App\Notifications\Team\InvitationRejectedNotification;
use App\Notifications\Team\JoinRequestReceivedNotification;
use App\Notifications\Team\TeamInvitationReceivedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class BaseNotificationService
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    // -------------------------------------------------------------------------
    // Core helpers — available to all callers
    // -------------------------------------------------------------------------

    /**
     * Send a notification to a user, swallowing exceptions so a failed
     * notification never breaks the calling business logic.
     */
    public function dispatch(User $user, BaseNotification $notification): void
    {
        try {
            $this->notificationService->send($user, $notification);
        } catch (\Throwable $e) {
            Log::error(static::class . ' failed to send notification', [
                'user_id'      => $user->id,
                'notification' => get_class($notification),
                'error'        => $e->getMessage(),
            ]);
        }
    }

    /**
     * Eager-load relations on a model only if they are not already loaded.
     * Accepts dot-notation relations (e.g. 'team.owner').
     */
    public function loadRelations(Model $model, array $relations): void
    {
        $roots = array_map(fn($r) => explode('.', $r)[0], $relations);

        $missing = array_values(
            array_filter($relations, fn($r, $i) => ! $model->relationLoaded($roots[$i]), ARRAY_FILTER_USE_BOTH)
        );

        if (! empty($missing)) {
            $model->loadMissing($missing);
        }
    }

    // -------------------------------------------------------------------------
    // Team Invitation notifications
    // -------------------------------------------------------------------------

    public function notifyPlayerInvited(TeamInvitation $invitation): void
    {
        $this->loadRelations($invitation, ['team.owner', 'playerStat.user']);

        $player = $invitation->playerStat->user;
        $admin  = $invitation->team->owner;

        $this->dispatch($player, new TeamInvitationReceivedNotification(
            playerName: $player->first_name,
            teamName: $invitation->team->name,
            adminName: $admin->first_name . ' ' . $admin->last_name,
            message: $invitation->message,
        ));
    }

    public function notifyAdminJoinRequest(TeamInvitation $invitation): void
    {
        $this->loadRelations($invitation, ['team.owner', 'playerStat.user']);

        $admin  = $invitation->team->owner;
        $player = $invitation->playerStat->user;

        $this->dispatch($admin, new JoinRequestReceivedNotification(
            adminName: $admin->first_name,
            playerName: $player->first_name . ' ' . $player->last_name,
            teamName: $invitation->team->name,
            message: $invitation->message,
        ));
    }

    public function notifyAccepted(TeamInvitation $invitation): void
    {
        $this->loadRelations($invitation, ['team.owner', 'playerStat.user']);

        $admin  = $invitation->team->owner;
        $player = $invitation->playerStat->user;

        if ($invitation->type === 'invitation') {
            $this->dispatch($admin, new InvitationAcceptedNotification(
                recipientName: $admin->first_name,
                teamName: $invitation->team->name,
                responderName: $player->first_name . ' ' . $player->last_name,
                isAdminRecipient: true,
            ));
        } else {
            $this->dispatch($player, new InvitationAcceptedNotification(
                recipientName: $player->first_name,
                teamName: $invitation->team->name,
                responderName: $admin->first_name,
                isAdminRecipient: false,
            ));
        }
    }

    public function notifyRejected(TeamInvitation $invitation): void
    {
        $this->loadRelations($invitation, ['team.owner', 'playerStat.user']);

        $admin  = $invitation->team->owner;
        $player = $invitation->playerStat->user;

        if ($invitation->type === 'invitation') {
            $this->dispatch($admin, new InvitationRejectedNotification(
                recipientName: $admin->first_name,
                teamName: $invitation->team->name,
                responderName: $player->first_name . ' ' . $player->last_name,
                isAdminRecipient: true,
            ));
        } else {
            $this->dispatch($player, new InvitationRejectedNotification(
                recipientName: $player->first_name,
                teamName: $invitation->team->name,
                responderName: $admin->first_name,
                isAdminRecipient: false,
            ));
        }
    }

    public function notifyCancelled(TeamInvitation $invitation): void
    {
        $this->loadRelations($invitation, ['team.owner', 'playerStat.user']);

        $admin  = $invitation->team->owner;
        $player = $invitation->playerStat->user;

        if ($invitation->type === 'invitation') {
            $this->dispatch($player, new InvitationCancelledNotification(
                recipientName: $player->first_name,
                teamName: $invitation->team->name,
                isAdminRecipient: false,
            ));
        } else {
            $this->dispatch($admin, new InvitationCancelledNotification(
                recipientName: $admin->first_name,
                teamName: $invitation->team->name,
                isAdminRecipient: true,
            ));
        }
    }

    // -------------------------------------------------------------------------
    // Bulk invitation logic (shared between team creation and bulk-invite)
    // -------------------------------------------------------------------------

    /**
     * Send invitations to multiple players for a team.
     *
     * Each entry: ['player_stat_id' => int, 'message' => ?string]
     *
     * Returns ['sent' => TeamInvitation[], 'skipped' => array[]]
     */
    public function sendBulkInvitations(Team $team, int $initiatedBy, array $players, ?string $message = null): array
    {
        $sent    = [];
        $skipped = [];

        foreach ($players as $entry) {
            $playerStatId = $entry['player_stat_id'];

            $isActiveMember = TeamMember::where('team_id', $team->id)
                ->where('player_id', $playerStatId)
                ->where('status', 'active')
                ->exists();

            $hasPending = TeamInvitation::where('team_id', $team->id)
                ->where('player_stat_id', $playerStatId)
                ->where('status', 'pending')
                ->exists();

            if ($isActiveMember || $hasPending) {
                $skipped[] = [
                    'player_stat_id' => $playerStatId,
                    'reason'         => $isActiveMember
                        ? 'Already an active member.'
                        : 'Pending invitation already exists.',
                ];
                continue;
            }

            $invitation = TeamInvitation::create([
                'team_id'        => $team->id,
                'player_stat_id' => $playerStatId,
                'initiated_by'   => $initiatedBy,
                'type'           => 'invitation',
                'message'        => $message,
            ]);

            $invitation->load(['team', 'playerStat.user']);
            $this->notifyPlayerInvited($invitation);

            $sent[] = $invitation;
        }

        return compact('sent', 'skipped');
    }
}
