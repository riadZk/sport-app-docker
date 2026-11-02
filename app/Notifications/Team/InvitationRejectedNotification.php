<?php

namespace App\Notifications\Team;

use App\Notifications\BaseNotification;

class InvitationRejectedNotification extends BaseNotification
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
            'type'    => 'invitation_rejected',
            'title'   => 'Invitation declined',
            'message' => "{$this->responderName} declined — {$this->teamName}.",
            'team'    => $this->teamName,
        ];
    }

    public function toWhatsapp(): string
    {
        $appName = config('app.name', 'SportApp');

        if ($this->isAdminRecipient) {
            return "❌ *{$appName} — Invitation Declined*\n\n"
                . "Hi *{$this->recipientName}*,\n\n"
                . "*{$this->responderName}* has declined your invitation to join *{$this->teamName}*.";
        }

        return "❌ *{$appName} — Request Declined*\n\n"
            . "Hi *{$this->recipientName}*,\n\n"
            . "Your request to join *{$this->teamName}* has been declined.";
    }
}
