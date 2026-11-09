<?php

namespace Platform\Signage\Support\KitaOffice;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Warnregeln für die CoolCooking-Boxen (aus Patricks Prototyp, jetzt serverseitig):
 *  - offline: länger als N Minuten keine neuen Gerätedaten.
 *  - Türwarnung (rot): Tür länger als N Minuten beobachtet offen, nur vor HH:MM (Europe/Berlin).
 *    MeinConnect liefert keine Öffnungszeit; die Dauer zählt ab der ersten Beobachtung.
 *    Eine Beobachtung gilt als durchgehend, wenn die letzte höchstens 2 Minuten zurückliegt.
 *  - Veraltete Daten lösen keine neue Türwarnung aus.
 *  - Andere Gerätefehler färben die Box ebenfalls rot.
 *
 * Der Beobachtungszustand ($doors) wird vom Aufrufer gespeichert (Cache), damit alle
 * Bildschirme denselben Stand zeigen und ein Neuladen ihn nicht zurücksetzt.
 * Reine Logik, ohne Laravel.
 */
final class BoxRules
{
    public const CONTINUITY_SECONDS = 120;

    public function __construct(
        private int $offlineMinutes = 5,
        private int $doorWarningMinutes = 5,
        private string $doorWarningUntil = '11:00',
    ) {
    }

    public static function fromConfig(array $cfg): self
    {
        return new self(
            (int) ($cfg['offline_minutes'] ?? 5),
            (int) ($cfg['door_warning_minutes'] ?? 5),
            (string) ($cfg['door_warning_until'] ?? '11:00'),
        );
    }

    /**
     * Aktualisiert die Tür-Beobachtungen.
     *
     * @param list<array<string,mixed>>                    $devices
     * @param array<string, array{since:int, last_seen:int}> $previous
     * @return array<string, array{since:int, last_seen:int}>
     */
    public function trackDoors(array $devices, array $previous, int $now): array
    {
        $next = [];

        foreach ($devices as $d) {
            $id = (string) ($d['id'] ?? '');
            if ($id === '' || !empty($d['error']) || ($d['door'] ?? null) !== 'open' || !$this->isFresh($d)) {
                continue;
            }

            $prior = $previous[$id] ?? null;
            $continuous = $prior && $now >= $prior['last_seen'] && $now - $prior['last_seen'] <= self::CONTINUITY_SECONDS;

            $next[$id] = ['since' => $continuous ? $prior['since'] : $now, 'last_seen' => $now];
        }

        return $next;
    }

    /**
     * Ergänzt je Gerät: offline, door_open_since, door_open_minutes, warning, level (ok|info|warn|alert).
     *
     * @param list<array<string,mixed>>                    $devices
     * @param array<string, array{since:int, last_seen:int}> $doors
     * @return list<array<string,mixed>>
     */
    public function evaluate(array $devices, array $doors, int $now): array
    {
        $local = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Berlin'))->format('H:i');
        $beforeCutoff = $local < $this->doorWarningUntil;

        return array_map(function (array $d) use ($doors, $now, $beforeCutoff) {
            $id = (string) ($d['id'] ?? '');
            $offline = empty($d['error']) && !$this->isFresh($d);
            $door = $doors[$id] ?? null;
            $openMinutes = $door ? intdiv(max(0, $now - $door['since']), 60) : null;

            $doorWarning = $door !== null
                && !$offline
                && $now - $door['since'] > $this->doorWarningMinutes * 60
                && $beforeCutoff;

            $otherAlerts = array_values(array_filter(
                (array) ($d['alerts'] ?? []),
                fn ($a) => !preg_match('~^Tür\b~u', (string) $a)
            ));

            $warning = null;
            if (!empty($d['error'])) {
                $level = 'info';
            } elseif ($offline) {
                $level = 'info';
                $warning = 'Offline';
            } elseif ($doorWarning) {
                $level = 'alert';
                $warning = 'Tür seit ' . $openMinutes . ' Min. offen';
            } elseif ($otherAlerts !== [] && ($d['connected'] ?? null) !== false) {
                $level = 'alert';
                $warning = $otherAlerts[0];
            } elseif (($d['door'] ?? null) === 'open') {
                $level = 'warn';
                $warning = 'Tür offen';
            } else {
                $level = 'ok';
            }

            return $d + [
                'offline'           => $offline,
                'door_open_since'   => $door ? gmdate(DATE_ATOM, $door['since']) : null,
                'door_open_minutes' => $openMinutes,
                'warning'           => $warning,
                'level'             => $level,
            ];
        }, $devices);
    }

    private function isFresh(array $d): bool
    {
        $age = $d['age_seconds'] ?? null;

        return is_numeric($age) && (int) $age <= $this->offlineMinutes * 60;
    }
}
