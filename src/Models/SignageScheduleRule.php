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
     * Gilt diese Regel jetzt? Nutzt dieselbe Intervall-Definition wie dayIntervals()
     * (halb-offen) – inkl. ganztägig und sauberem Über-Mitternacht-Übergang.
     */
    public function matchesNow(\DateTimeInterface $now): bool
    {
        if (!$this->active) {
            return false;
        }

        $dow = (int) $now->format('N'); // 1 (Mo) .. 7 (So)
        $cur = (int) $now->format('G') * 60 + (int) $now->format('i');

        foreach ($this->dayIntervals() as $iv) {
            if ($iv['day'] === $dow && $cur >= $iv['start'] && $cur < $iv['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Halb-offene Minuten-Intervalle je Wochentag (ISO 1=Mo..7=So). Kanonische
     * Quelle für Laufzeit (matchesNow) und Überlappungsprüfung (ScheduleOverlap):
     *  - Ende 00:00 zählt als Tagesende (1440).
     *  - start < end  -> [start, end)
     *  - start > end  -> über Nacht: [start,1440) heute und [0,end) am Folgetag
     *  - start == end -> ganzer Tag [0,1440)
     *
     * @return array<int, array{day:int,start:int,end:int}>
     */
    public function dayIntervals(): array
    {
        $start = self::minutes($this->start_time);
        $end = self::minutes($this->end_time);
        if ($end === 0) {
            $end = 1440; // 00:00 als Endzeit meint das Tagesende
        }

        $days = array_map('intval', $this->days_of_week ?? []);
        $out = [];

        foreach ($days as $d) {
            if ($start < $end) {
                $out[] = ['day' => $d, 'start' => $start, 'end' => $end];
            } elseif ($start > $end) {
                $out[] = ['day' => $d, 'start' => $start, 'end' => 1440];
                $out[] = ['day' => $d % 7 + 1, 'start' => 0, 'end' => $end];
            } else {
                // start == end -> ganztägig
                $out[] = ['day' => $d, 'start' => 0, 'end' => 1440];
            }
        }

        return $out;
    }

    private static function minutes($time): int
    {
        $p = explode(':', (string) $time);

        return ((int) ($p[0] ?? 0)) * 60 + (int) ($p[1] ?? 0);
    }
}
