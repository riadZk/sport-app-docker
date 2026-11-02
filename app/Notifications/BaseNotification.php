<?php

namespace App\Notifications;

abstract class BaseNotification
{
    /**
     * Get the channels this notification should be sent through.
     *
     * @return array<string>
     */
    abstract public function channels(): array;

    /**
     * Get the realtime broadcast payload.
     * Override in subclass if 'realtime' channel is used.
     *
     * @return array<string, mixed>
     */
    public function toRealtime(): array
    {
        return [];
    }

    /**
     * Get the WhatsApp message content.
     * Override in subclass if 'whatsapp' channel is used.
     */
    public function toWhatsapp(): string
    {
        return '';
    }

    /**
     * The logical category used to group/filter persisted notifications.
     * Override in subclass (e.g. 'invitations', 'bookings').
     */
    public function category(): string
    {
        return 'general';
    }

    /**
     * The payload stored in the database when the 'database' channel is used.
     * Defaults to the realtime payload merged with the category.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(): array
    {
        $payload = $this->toRealtime();

        if (empty($payload)) {
            $payload = ['message' => $this->toWhatsapp()];
        }

        return array_merge(['category' => $this->category()], $payload);
    }

    /**
     * Check if a specific channel is enabled for this notification.
     */
    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels());
    }
}
