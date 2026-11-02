<?php

namespace App\Listeners;

namespace App\Listeners;

use Spatie\Backup\Events\BackupHasFailed;
use App\Services\WhatsappService;

class SendBackupWhatsappFailed
{
    public function handle(BackupHasFailed $event)
    {
        $message = "❌ Backup Failed\nTime: ".now()."\nCheck server immediately!";
        app(WhatsappService::class)->send('212605337823' ,$message);
    }
}
