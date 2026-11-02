<?php

namespace App\Notifications\Team;

use App\Notifications\BaseNotification;

class InvitationCancelledNotification extends BaseNotification
{
    public function __construct(
        protected string $recipientName,
        protected string $teamName,
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
            'type'    => 'invitation_cancelled',
            'title'   => 'Invitation cancelled',
            'message' => "An invitation for {$this->teamName} was cancelled.",
            'team'    => $this->teamName,
        ];
    }

    public function toWhatsapp(): string
    {
        $appName = config('app.name', 'SportApp');

        if ($this->isAdminRecipient) {
            return "🚫 *{$appName} — Join Request Cancelled*\n\n"
                . "Hi *{$this->recipientName}*,\n\n"
                . "A player has cancelled their join request to *{$this->teamName}*.";
        }

        return "🚫 *{$appName} — Invitation Cancelled*\n\n"
            . "Hi *{$this->recipientName}*,\n\n"
            . "Your invitation to join *{$this->teamName}* has been cancelled.";
    }
}
