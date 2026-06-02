<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignagePlaylistItem extends Model
{
    protected $table = 'signage_playlist_items';

    protected $fillable = [
        'playlist_id', 'media_id', 'position', 'duration_seconds', 'transition',
    ];

    protected $casts = [
        'position' => 'integer',
        'duration_seconds' => 'integer',
    ];

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(SignagePlaylist::class, 'playlist_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(SignageMedia::class, 'media_id');
    }
}
