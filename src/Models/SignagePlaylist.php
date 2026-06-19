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

    protected static function booted(): void
    {
        // Beim (Soft-)Löschen einer Wiedergabeliste alle Verweise sauber lösen,
        // damit Zeitpläne/Bildschirme nicht still auf eine verschwundene Liste zeigen.
        static::deleting(function (self $playlist) {
            $id = $playlist->id;

            // 1) Betroffene Bildschirme neu laden lassen – VOR dem Entfernen der
            //    Referenzen, solange bumpForPlaylists sie noch findet.
            SignageScreen::bumpForPlaylists([$id]);

            // 2) Direkte Screen-Verweise lösen.
            SignageScreen::where('default_playlist_id', $id)->update(['default_playlist_id' => null]);
            SignageScreen::where('music_playlist_id', $id)->update(['music_playlist_id' => null]);

            // 3) Zeitplan-Regeln: betroffene Verweise lösen, danach leer gewordene Regeln entfernen.
            $ruleIds = SignageScheduleRule::where('playlist_id', $id)
                ->orWhere('music_playlist_id', $id)
                ->pluck('id');
            SignageScheduleRule::where('playlist_id', $id)->update(['playlist_id' => null]);
            SignageScheduleRule::where('music_playlist_id', $id)->update(['music_playlist_id' => null]);
            SignageScheduleRule::whereIn('id', $ruleIds)
                ->whereNull('playlist_id')
                ->whereNull('music_playlist_id')
                ->delete();
        });
    }

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
