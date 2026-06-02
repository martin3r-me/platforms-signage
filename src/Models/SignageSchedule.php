<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Signage\Models\Concerns\HasUuid;

class SignageSchedule extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'signage_schedules';

    protected $fillable = [
        'uuid', 'team_id', 'screen_id', 'playlist_id', 'music_playlist_id',
        'days_of_week', 'start_time', 'end_time', 'priority', 'active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class, 'team_id');
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(SignageScreen::class, 'screen_id');
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(SignagePlaylist::class, 'playlist_id');
    }

    public function musicPlaylist(): BelongsTo
    {
        return $this->belongsTo(SignagePlaylist::class, 'music_playlist_id');
    }

    /**
     * Prüft, ob dieser Zeitplan für den gegebenen Zeitpunkt aktiv ist.
     * Unterstützt auch über Mitternacht laufende Fenster (z.B. 22:00–02:00).
     */
    public function matchesNow(\DateTimeInterface $now): bool
    {
        if (!$this->active) {
            return false;
        }

        $dow = (int) $now->format('N'); // 1 (Mo) .. 7 (So)
        $days = $this->days_of_week ?? [];
        if (!in_array($dow, array_map('intval', $days), true)) {
            return false;
        }

        $current = $now->format('H:i:s');
        $start = $this->normalizeTime($this->start_time);
        $end = $this->normalizeTime($this->end_time);

        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }

        // Über Mitternacht: aktiv wenn nach Start ODER vor Ende.
        return $current >= $start || $current <= $end;
    }

    private function normalizeTime($value): string
    {
        $str = (string) $value;
        // Akzeptiert "H:i" und "H:i:s".
        if (strlen($str) === 5) {
            return $str.':00';
        }

        return $str;
    }
}
