<?php

namespace App\Notifications\Team;

use App\Notifications\BaseNotification;

class JoinRequestReceivedNotification extends BaseNotification
{
    public function __construct(
        protected string $adminName,
        protected string $playerName,
        protected string $teamName,
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
            'type'    => 'join_request_received',
            'title'   => 'New join request',
            'message' => "{$this->playerName} wants to join {$this->teamName}.",
            'team'    => $this->teamName,
        ];
    }

    public function toWhatsapp(): string
    {
        $appName = config('app.name', 'SportApp');
        $body = "⚽ *{$appName} — Join Request*\n\n"
            . "Hi *{$this->adminName}*,\n\n"
            . "*{$this->playerName}* wants to join your team *{$this->teamName}*.\n";

        if ($this->message) {
            $body .= "\n📝 Message: _{$this->message}_\n";
        }

        $body .= "\nOpen the app to accept or reject this request.";

        return $body;
    }
}
