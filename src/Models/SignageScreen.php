<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

class SignageScreen extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_screens';

    protected $fillable = [
        'uuid', 'team_id', 'name', 'device_token', 'pairing_code', 'status',
        'default_playlist_id', 'music_playlist_id', 'music_media_id', 'orientation',
        'content_version', 'last_seen_at', 'paired_at', 'settings',
    ];

    protected $casts = [
        'content_version' => 'integer',
        'last_seen_at' => 'datetime',
        'paired_at' => 'datetime',
        'settings' => 'array',
    ];

    // Routen binden über die UUID statt der numerischen ID.
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function defaultPlaylist(): BelongsTo
    {
        return $this->belongsTo(SignagePlaylist::class, 'default_playlist_id');
    }

    public function musicPlaylist(): BelongsTo
    {
        return $this->belongsTo(SignagePlaylist::class, 'music_playlist_id');
    }

    public function musicMedia(): BelongsTo
    {
        return $this->belongsTo(SignageMedia::class, 'music_media_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(SignageSchedule::class, 'screen_id');
    }

    public function isOnline(): bool
    {
        if (!$this->last_seen_at) {
            return false;
        }

        $threshold = (int) config('signage.offline_after_seconds', 60);

        return $this->last_seen_at->gt(now()->subSeconds($threshold));
    }

    public function isPaired(): bool
    {
        return $this->status === 'active';
    }
}
