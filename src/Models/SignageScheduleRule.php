<?php

namespace Platform\Signage\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignageScheduleRule extends Model
{
    protected $table = 'signage_schedule_rules';

    protected $fillable = [
        'schedule_id', 'playlist_id', 'music_playlist_id',
        'days_of_week', 'start_time', 'end_time', 'priority', 'active',
    ];

    protected $casts = [
        'days_of_week' => 'array',
        'priority' => 'integer',
        'active' => 'boolean',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(SignageSchedule::class, 'schedule_id');
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
     * Gilt diese Regel jetzt? Unterstützt auch über Mitternacht laufende Fenster.
     */
    public function matchesNow(\DateTimeInterface $now): bool
    {
        if (!$this->active) {
            return false;
        }

        $dow = (int) $now->format('N'); // 1 (Mo) .. 7 (So)
        if (!in_array($dow, array_map('intval', $this->days_of_week ?? []), true)) {
            return false;
        }

        $current = $now->format('H:i:s');
        $start = $this->normalizeTime($this->start_time);
        $end = $this->normalizeTime($this->end_time);

        if ($start <= $end) {
            return $current >= $start && $current <= $end;
        }

        return $current >= $start || $current <= $end;
    }

    private function normalizeTime($value): string
    {
        $str = (string) $value;

        return strlen($str) === 5 ? $str.':00' : $str;
    }
}
