<?php

namespace App\Notifications\Team;

use App\Notifications\BaseNotification;

class TeamInvitationReceivedNotification extends BaseNotification
{
    public function __construct(
        protected string $playerName,
        protected string $teamName,
        protected string $adminName,
        protected ?string $message,
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
            'type'    => 'team_invitation_received',
            'title'   => 'New team invitation',
            'message' => "You have been invited to join {$this->teamName}.",
            'team'    => $this->teamName,
        ];
    }

    public function toWhatsapp(): string
    {
        $appName = config('app.name', 'SportApp');
        $body = "⚽ *{$appName} — Team Invitation*\n\n"
            . "Hi *{$this->playerName}*,\n\n"
            . "*{$this->adminName}* has invited you to join *{$this->teamName}*.\n";

        if ($this->message) {
            $body .= "\n📝 Message: _{$this->message}_\n";
        }

        $body .= "\nOpen the app to accept or reject this invitation.";

        return $body;
    }
}
