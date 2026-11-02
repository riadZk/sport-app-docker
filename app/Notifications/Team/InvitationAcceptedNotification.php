<?php

namespace App\Notifications\Team;

use App\Notifications\BaseNotification;

class InvitationAcceptedNotification extends BaseNotification
{
    public function __construct(
        protected string $recipientName,
        protected string $teamName,
        protected string $responderName,
        protected bool $isAdminRecipient,
    ) {}

    public function channels(): array
    {
        return ['whatsapp', 'realtime', 'database'];
    }

    public function category(): string
    {
        return 'invitations';
    }

    public function toRealtime(): array
    {
        return [
            'type'    => 'invitation_accepted',
            'title'   => 'Invitation accepted',
            'message' => "{$this->responderName} accepted — {$this->teamName}.",
            'team'    => $this->teamName,
        ];
    }

    public function toWhatsapp(): string
    {
        $appName = config('app.name', 'SportApp');

        if ($this->isAdminRecipient) {
            return "✅ *{$appName} — Invitation Accepted*\n\n"
                . "Hi *{$this->recipientName}*,\n\n"
                . "*{$this->responderName}* has accepted your invitation and joined *{$this->teamName}*. 🎉";
        }

        return "✅ *{$appName} — Request Accepted*\n\n"
            . "Hi *{$this->recipientName}*,\n\n"
            . "Your request to join *{$this->teamName}* has been accepted. Welcome aboard! 🎉";
    }
}
