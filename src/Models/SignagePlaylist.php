<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

class SignagePlaylist extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_playlists';

    protected $fillable = [
        'uuid', 'team_id', 'user_id', 'name', 'description', 'kind', 'loop', 'fit',
    ];

    protected $casts = [
        'loop' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SignagePlaylistItem::class, 'playlist_id')->orderBy('position');
    }

    public function team()
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function user()
    {
        return $this->belongsTo(\Platform\Core\Models\User::class, 'user_id');
    }

    public function isVisual(): bool
    {
        return $this->kind === 'visual';
    }

    public function isMusic(): bool
    {
        return $this->kind === 'music';
    }
}
