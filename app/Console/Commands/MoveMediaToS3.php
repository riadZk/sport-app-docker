<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Facades\Storage;

class MoveMediaToS3 extends Command
{
    protected $signature = 'media:move-to-s3';
    protected $description = 'Move existing media from local disk to S3';

    public function handle()
    {
        $mediaItems = Media::where('disk', 'public')->get();

        foreach ($mediaItems as $media) {

            $this->info("Moving media ID: {$media->id}");
            // Get file from local disk
            $file = Storage::disk('public')->get($media->getPathRelativeToRoot());

            // Put file to S3
            Storage::disk('s3')->put(
                $media->getPathRelativeToRoot(),
                $file
            );

            // Update disk in DB
            $media->update(['disk' => 's3']);
        }

        $this->info( '✅ All media moved to S3 successfully!');
    }
}
