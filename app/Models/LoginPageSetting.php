<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LoginPageSetting extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    protected $fillable = ['offer_title', 'offer_body', 'is_offer_active'];

    protected function casts(): array
    {
        return ['is_offer_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        // singleFile() means adding a new background automatically removes
        // the previous one - Admin never has to manually clean up an old
        // upload before replacing it.
        $this->addMediaCollection('background')->singleFile();
    }

    /**
     * There's only ever one row - the login page has exactly one
     * background and one promo, not a list of them.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function backgroundImage(): ?Media
    {
        return $this->getFirstMedia('background');
    }

    public function hasActiveOffer(): bool
    {
        return $this->is_offer_active && (filled($this->offer_title) || filled($this->offer_body));
    }
}
