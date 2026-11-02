<?php

namespace App\Contracts;

use App\Models\TeamInvitation;

interface TeamInvitationNotifierContract
{
    /**
     * Notify the player when the team admin sends them an invitation.
     */
    public function notifyPlayerInvited(TeamInvitation $invitation): void;

    /**
     * Notify the team admin when a player sends a join request.
     */
    public function notifyAdminJoinRequest(TeamInvitation $invitation): void;

    /**
     * Notify the initiator (admin or player) when their invitation/request is accepted.
     */
    public function notifyAccepted(TeamInvitation $invitation): void;

    /**
     * Notify the initiator (admin or player) when their invitation/request is rejected.
     */
    public function notifyRejected(TeamInvitation $invitation): void;

    /**
     * Notify the recipient when the sender cancels the invitation/request.
     */
    public function notifyCancelled(TeamInvitation $invitation): void;
}
