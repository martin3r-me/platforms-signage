<?php

namespace Platform\Signage\Support;

use Illuminate\Support\Collection;
use Platform\Signage\Models\SignageSchedule;

/**
 * Berechnet, wie gut die einem Bildschirm zugewiesenen Zeitpläne die Woche abdecken
 * (für eine Art: visual/music). Nutzt dieselbe kanonische Intervall-Logik wie Laufzeit
 * und Überlappungsprüfung: SignageScheduleRule::dayIntervals() (halb-offen, Minuten).
 *
 * Eine "Lücke" ist ein Zeitbereich, in dem keine Regel greift – dort springt die
 * Standard-Wiedergabeliste des Bildschirms ein (oder es bleibt leer).
 */
class ScheduleCoverage
{
    private const DAYS = [
        1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So',
    ];

    /**
     * @param  Collection<int, SignageSchedule>  $schedules  jeweils mit geladener rules-Relation
     * @return array{full:bool, labels: array<int,string>}
     */
    public static function summary(Collection $schedules, string $kind = 'visual'): array
    {
        $byDay = array_fill_keys(range(1, 7), []);

        foreach ($schedules as $schedule) {
            foreach ($schedule->rules as $rule) {
                if (!$rule->active) {
                    continue;
                }
                $playlistId = $kind === 'music' ? $rule->music_playlist_id : $rule->playlist_id;
                if (!$playlistId) {
                    continue;
                }
                foreach ($rule->dayIntervals() as $iv) {
                    $byDay[$iv['day']][] = [$iv['start'], $iv['end']];
                }
            }
        }

        $full = true;
        $labels = [];
        foreach (range(1, 7) as $day) {
            $gaps = self::gaps(self::merge($byDay[$day]));
            if ($gaps) {
                $full = false;
                foreach ($gaps as [$s, $e]) {
                    $labels[] = self::DAYS[$day].' '.self::hm($s).'–'.self::hm($e);
                }
            }
        }

        return ['full' => $full, 'labels' => $labels];
    }

    /** Sortierte, zusammengefasste Intervalle. */
    private static function merge(array $intervals): array
    {
        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);

        $out = [];
        foreach ($intervals as [$s, $e]) {
            $n = count($out);
            if ($n > 0 && $s <= $out[$n - 1][1]) {
                $out[$n - 1][1] = max($out[$n - 1][1], $e);
            } else {
                $out[] = [$s, $e];
            }
        }

        return $out;
    }

    /** Komplement der gemergten Intervalle innerhalb [0,1440). */
    private static function gaps(array $merged): array
    {
        $gaps = [];
        $cursor = 0;
        foreach ($merged as [$s, $e]) {
            if ($s > $cursor) {
                $gaps[] = [$cursor, $s];
            }
            $cursor = max($cursor, $e);
        }
        if ($cursor < 1440) {
            $gaps[] = [$cursor, 1440];
        }

        return $gaps;
    }

    private static function hm(int $min): string
    {
        if ($min >= 1440) {
            return '24:00';
        }

        return sprintf('%02d:%02d', intdiv($min, 60), $min % 60);
    }
}
