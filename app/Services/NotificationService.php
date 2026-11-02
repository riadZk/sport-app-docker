<?php

namespace App\Services;

use App\Events\UserNotificationEvent;
use App\Models\User;
use App\Notifications\BaseNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        protected WhatsappService $whatsappService,
    ) {}

    /**
     * Send a notification to a user through all enabled channels.
     */
    public function send(User $user, BaseNotification $notification): void
    {
        foreach ($notification->channels() as $channel) {
            match ($channel) {
                'realtime' => $this->sendRealtime($user, $notification),
                'whatsapp' => $this->sendWhatsapp($user, $notification),
                'database' => $this->sendDatabase($user, $notification),
                default => Log::warning("Unknown notification channel: {$channel}"),
            };
        }
    }

    /**
     * Persist a notification so it can be retrieved via the notifications API.
     */
    protected function sendDatabase(User $user, BaseNotification $notification): void
    {
        try {
            $user->notifications()->create([
                'id'      => (string) Str::uuid(),
                'type'    => get_class($notification),
                'data'    => $notification->toDatabase(),
                'read_at' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to persist database notification', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast a realtime notification via Reverb.
     */
    protected function sendRealtime(User $user, BaseNotification $notification): void
    {
        try {
            event(new UserNotificationEvent(
                userId: $user->id,
                data: $notification->toRealtime(),
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to send realtime notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a WhatsApp notification using WatsenderService.
     */
    protected function sendWhatsapp(User $user, BaseNotification $notification): void
    {
        if (empty($user->phone_number)) {
            Log::warning('Cannot send WhatsApp notification: user has no phone number', [
                'user_id' => $user->id,
            ]);

            return;
        }

        try {
            $this->whatsappService->send(phoneNumber: $user->phone_number,message: $notification->toWhatsapp());
        } catch (\Throwable $e) {
            Log::error('Failed to send WhatsApp notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
