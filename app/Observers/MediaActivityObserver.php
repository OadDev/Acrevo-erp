<?php

namespace App\Observers;

use Illuminate\Support\Facades\Auth;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Every file/photo/video upload in the app goes through this one Media
 * model, regardless of which record it's attached to (Assets, Tickets,
 * Work Orders, Quotations, Employees, Sites, ...), so observing it here
 * once logs every upload and removal system-wide - no need to touch each
 * individual controller's addMedia() call.
 */
class MediaActivityObserver
{
    public function created(Media $media): void
    {
        $this->log($media, 'uploaded');
    }

    public function deleted(Media $media): void
    {
        $this->log($media, 'removed');
    }

    private function log(Media $media, string $verb): void
    {
        $subject = $media->model_type && class_exists($media->model_type) ? $media->model : null;
        $ownerLabel = class_basename($media->model_type ?? 'Unknown').($subject ? " #{$subject->getKey()}" : " #{$media->model_id}");

        activity('media')
            ->performedOn($subject ?: $media)
            ->causedBy(Auth::user())
            ->withProperties([
                'file_name' => $media->file_name,
                'collection' => $media->collection_name,
                'size' => $media->size,
                'model_type' => $media->model_type,
                'model_id' => $media->model_id,
            ])
            ->log("{$verb} \"{$media->file_name}\" ({$media->collection_name}) on {$ownerLabel}");
    }
}
