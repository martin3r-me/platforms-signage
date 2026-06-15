<?php

namespace Platform\Signage\Support;

use Illuminate\Support\Collection;
use Platform\Signage\Models\SignageSchedule;

/**
 * Erkennt zeitliche Überschneidungen zwischen den Regeln mehrerer Zeitpläne,
 * die demselben Bildschirm zugewiesen werden sollen.
 *
 * Grundsätze:
 *  - Getrennt nach Art (Anzeige/Musik): beide werden im Player unabhängig
 *    aufgelöst, ein reiner Musik-Plan darf also zeitgleich mit einem reinen
 *    Anzeige-Plan laufen.
 *  - Nur Regeln VERSCHIEDENER Pläne werden verglichen. Innerhalb eines Plans
 *    sind Überlappungen weiterhin erlaubt (dort entscheidet die Priorität).
 *  - Intervalle sind halb-offen [start, end): angrenzende Fenster (z.B. ein
 *    Fenster endet 10:30, das nächste beginnt 10:30) gelten NICHT als Konflikt.
 */
class ScheduleOverlap
{
    private const DAYS = [
        1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag',
        5 => 'Freitag', 6 => 'Samstag', 7 => 'Sonntag',
    ];

    /**
     * @param  Collection<int, SignageSchedule>  $schedules  jeweils mit geladener rules-Relation
     * @return string|null  Menschenlesbare Fehlermeldung beim ersten Konflikt, sonst null
     */
    public static function conflictMessage(Collection $schedules): ?string
    {
        foreach (['visual', 'music'] as $kind) {
            $segments = [];

            foreach ($schedules->values() as $si => $schedule) {
                foreach ($schedule->rules as $rule) {
                    if (!$rule->active) {
                        continue;
                    }
                    $playlistId = $kind === 'music' ? $rule->music_playlist_id : $rule->playlist_id;
                    if (!$playlistId) {
                        continue;
                    }
                    foreach (self::intervals($rule) as $seg) {
                        $segments[] = $seg + ['si' => $si, 'schedule' => $schedule, 'rule' => $rule];
                    }
                }
            }

            $count = count($segments);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $segments[$i];
                    $b = $segments[$j];

                    if ($a['si'] === $b['si']) {
                        continue; // gleicher Plan -> Priorität regelt das
                    }
                    if ($a['day'] !== $b['day']) {
                        continue;
                    }
                    // Halb-offene Überschneidung.
                    if ($a['start'] < $b['end'] && $b['start'] < $a['end']) {
                        return self::message($kind, $a, $b);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Halb-offene Minuten-Intervalle je Wochentag. Über Mitternacht laufende
     * Fenster werden auf den Folgetag aufgeteilt. 00:00 als Ende = Tagesende (24:00).
     *
     * @return array<int, array{day:int,start:int,end:int}>
     */
    private static function intervals($rule): array
    {
        $start = self::minutes($rule->start_time);
        $end = self::minutes($rule->end_time);
        if ($end === 0) {
            $end = 1440; // 00:00 als Endzeit meint das Tagesende
        }

        $days = array_map('intval', $rule->days_of_week ?? []);
        $out = [];

        foreach ($days as $d) {
            if ($start < $end) {
                $out[] = ['day' => $d, 'start' => $start, 'end' => $end];
            } elseif ($start > $end) {
                // Läuft über Mitternacht: heute bis 24:00, Folgetag ab 00:00.
                $out[] = ['day' => $d, 'start' => $start, 'end' => 1440];
                $next = $d % 7 + 1;
                $out[] = ['day' => $next, 'start' => 0, 'end' => $end];
            }
            // start == end: kein sinnvolles Fenster -> ignorieren
        }

        return $out;
    }

    private static function minutes($time): int
    {
        $p = explode(':', (string) $time);

        return ((int) ($p[0] ?? 0)) * 60 + (int) ($p[1] ?? 0);
    }

    private static function message(string $kind, array $a, array $b): string
    {
        $label = $kind === 'music' ? 'Musik' : 'Anzeige';
        $day = self::DAYS[$a['day']] ?? ('Tag '.$a['day']);

        return sprintf(
            'Überschneidung (%s) am %s: »%s« (%s–%s) und »%s« (%s–%s) überlappen sich. '
            .'Bitte die Zeitfenster anpassen, sodass sie sich nicht überschneiden.',
            $label,
            $day,
            $a['schedule']->name,
            self::hm($a['rule']->start_time),
            self::hm($a['rule']->end_time),
            $b['schedule']->name,
            self::hm($b['rule']->start_time),
            self::hm($b['rule']->end_time),
        );
    }

    private static function hm($time): string
    {
        $p = explode(':', (string) $time);

        return sprintf('%02d:%02d', (int) ($p[0] ?? 0), (int) ($p[1] ?? 0));
    }
}
