<?php

namespace App\Listeners;

use Spatie\Backup\Events\BackupWasSuccessful;
use App\Services\WhatsappService;

class SendBackupWhatsappSuccess
{


    /**
     * Handle the event.
     */
    public function handle(BackupWasSuccessful $event)
    {
        $message = "✅ Backup Successful\nTime: ".now();
        app(WhatsappService::class)->send('212605337823',$message);
    }
}
