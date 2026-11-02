<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Backup\Events\BackupHasFailed;
use App\Listeners\SendBackupWhatsappSuccess;
use App\Listeners\SendBackupWhatsappFailed;

class BackupEventServiceProvider extends ServiceProvider
{
    protected $listen = [
        BackupWasSuccessful::class => [
            SendBackupWhatsappSuccess::class,
        ],
        BackupHasFailed::class => [
            SendBackupWhatsappFailed::class,
        ],
    ];
}