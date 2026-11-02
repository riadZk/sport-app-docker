<?php

namespace App\Notifications;

class WelcomeNotification extends BaseNotification
{
    public function __construct(
        protected string $firstName,
    ) {}

    public function channels(): array
    {
        return ['realtime', 'whatsapp'];
    }

    public function toRealtime(): array
    {
        return [
            'type' => 'welcome',
            'title' => 'Welcome!',
            'message' => "Welcome {$this->firstName}! Your phone number has been verified successfully.",
        ];
    }

    public function toWhatsapp(): string
    {
        $appName = config('app.name', 'SportApp');

        return "🎉 *Congratulations {$this->firstName}!*\n\n"
            . "Your phone number has been verified successfully.\n\n"
            . "Welcome to *{$appName}*! 🏆\n"
            . "You now have full access to all features.\n\n"
            . "Enjoy your experience! ⚽🎾🏀";
    }
}
