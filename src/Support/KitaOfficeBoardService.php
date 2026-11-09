<?php

namespace Platform\Signage\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Signage\Support\KitaOffice\BoxRules;
use Platform\Signage\Support\KitaOffice\DriverDirectory;

/**
 * Daten fürs Kita-Office-Board (app_type=kitaoffice). Führt die Quellen zusammen
 * und wendet die Kita-Regeln an:
 *  - Boxen: MeinConnect-Connection aus platforms-integrations (Gastfreigabe).
 *  - Touren: bestehende DedeFleet-Anbindung (FleetBoardService), ergänzt um
 *    Fahrer-Telefonnummern aus der Telefonliste.
 *  - Wetter lädt der Player selbst (Open-Meteo), Uhr/Datum/KW ebenfalls.
 *
 * Modul-Schnitt wie beim FleetBoardService: alle Abrufe laufen über die Services der
 * Integration mit dem User (Editor bzw. Ersteller der App); per class_exists gegatet,
 * ohne installierte Integration bleibt der jeweilige Bereich leer.
 */
class KitaOfficeBoardService
{
    public const MEINCONNECT_KEY = 'meinconnect';

    private const MEINCONNECT_API = \Platform\Integrations\Services\MeinConnectApiService::class;
    private const RESOLVER        = \Platform\Integrations\Services\IntegrationConnectionResolver::class;

    public static function meinconnectAvailable(): bool
    {
        return class_exists(self::MEINCONNECT_API) && class_exists(self::RESOLVER);
    }

    /** @return array<int, array{id:int, label:string}> */
    public static function meinconnectConnectionsForUser(?object $user): array
    {
        if (!self::meinconnectAvailable() || !$user) {
            return [];
        }

        try {
            return app(self::RESOLVER)->resolveAllForUser(self::MEINCONNECT_KEY, $user)
                ->map(fn ($c) => ['id' => (int) $c->id, 'label' => (string) ($c->name ?: ('Connection #' . $c->id))])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param array<string,mixed> $config bereinigte App-Konfiguration (KitaOfficeConfig::sanitize)
     */
    public static function board(?User $user, array $config): array
    {
        $now = time();

        return [
            'generated_at'    => gmdate(DATE_ATOM, $now),
            'boxes'           => self::boxes($user, $config, $now),
            'tours'           => self::tours($user, $config),
            'facility_phones' => $config['facility_phones'] ?? [],
        ];
    }

    // ---------------------------------------------------------------------
    // Boxen (MeinConnect)
    // ---------------------------------------------------------------------

    private static function boxes(?User $user, array $config, int $now): array
    {
        $connectionId = $config['meinconnect_connection_id'] ?? null;

        if (!self::meinconnectAvailable() || !$user || !$connectionId) {
            return ['available' => false, 'devices' => []];
        }

        try {
            $data = app(self::MEINCONNECT_API)->forConnection((int) $connectionId)->devices($user);
        } catch (\Throwable $e) {
            Log::warning('Signage KitaOffice: MeinConnect-Abruf fehlgeschlagen', [
                'connection_id' => $connectionId,
                'user_id'       => $user->id ?? null,
                'exception'     => $e::class,
                'message'       => $e->getMessage(),
            ]);

            return ['available' => true, 'error' => self::publicError($e), 'devices' => []];
        }

        // Gerätealter bezieht sich auf den Abrufzeitpunkt; bis jetzt vergangene Zeit addieren
        // (Ergebnis kann aus dem kurzen Integrations-Cache stammen).
        $fetchedAt = strtotime((string) ($data['fetched_at'] ?? '')) ?: $now;
        $devices = array_map(function (array $d) use ($now, $fetchedAt) {
            if (isset($d['age_seconds']) && is_numeric($d['age_seconds'])) {
                $d['age_seconds'] = (int) $d['age_seconds'] + max(0, $now - $fetchedAt);
            }

            return $d;
        }, (array) ($data['devices'] ?? []));

        $rules = BoxRules::fromConfig($config);
        $doorKey = 'signage.kitaoffice.doors.' . (int) $connectionId;
        $doors = $rules->trackDoors($devices, (array) Cache::get($doorKey, []), $now);
        Cache::put($doorKey, $doors, 86400);

        $devices = array_map(
            fn (array $d) => $d + [
                'phone' => self::facilityPhone((string) ($d['name'] ?? ''), $config['facility_phones'] ?? []),
                'label' => self::shortName((string) ($d['name'] ?? ''), $config['box_strip_prefixes'] ?? []),
            ],
            $rules->evaluate($devices, $doors, $now)
        );

        return [
            'available'  => true,
            'fetched_at' => gmdate(DATE_ATOM, $fetchedAt),
            'devices'    => $devices,
        ];
    }

    /** Anzeigename ohne Träger-Präfix ("DRK Kita Lebensbaum …" → "Lebensbaum …"). */
    private static function shortName(string $name, array $prefixes): string
    {
        foreach ($prefixes as $prefix) {
            if ($prefix !== '' && mb_stripos($name, $prefix . ' ') === 0) {
                return trim(mb_substr($name, mb_strlen($prefix)));
            }
        }

        return $name;
    }

    private static function facilityPhone(string $deviceName, array $phones): ?string
    {
        foreach ($phones as $p) {
            if (($p['match'] ?? '') !== '' && mb_stripos($deviceName, $p['match']) !== false) {
                return $p['phone'];
            }
        }

        return null;
    }

    // ---------------------------------------------------------------------
    // Touren (DedeFleet)
    // ---------------------------------------------------------------------

    private static function tours(?User $user, array $config): array
    {
        $connectionId = $config['dedefleet_connection_id'] ?? null;
        if (!$connectionId) {
            return ['available' => false, 'tours' => []];
        }

        $board = FleetBoardService::board($user, (int) $connectionId, ['show_progress' => false]);
        if (empty($board['available']) || !empty($board['error'])) {
            return [
                'available' => (bool) ($board['available'] ?? false),
                'error'     => !empty($board['error']) ? 'Tourenplan aktuell nicht erreichbar.' : null,
                'tours'     => [],
            ];
        }

        $directory = DriverDirectory::fromConfig($config);
        $hidePickups = (bool) ($config['hide_pickups'] ?? true);
        $out = [];

        foreach ($board['tours'] as $tour) {
            $stops = $hidePickups
                ? array_values(array_filter($tour['stops'] ?? [], fn ($s) => empty($s['abh'])))
                : ($tour['stops'] ?? []);

            // Reine Abholfahrten ausblenden (Patricks Regel).
            if ($hidePickups && $stops === [] && !empty($tour['stops'])) {
                continue;
            }

            $driver = $directory->lookup((string) ($tour['driver'] ?? ''));

            $out[] = [
                'id'            => $tour['id'],
                'name'          => $tour['name'],
                'departure'     => $tour['departure'],
                'vehicle'       => $tour['vehicle'] ?? '',
                'driver'        => $driver['driver'],
                'phone'         => $driver['phone'],
                'phone_status'  => $driver['driver'] === '' ? 'none' : $driver['status'],
                'phone_note'    => $driver['driver'] === '' ? 'Kein Fahrer eingetragen' : $driver['note'],
                'whatsapp_only' => $driver['whatsapp_only'],
                'customers'     => array_values(array_filter(array_map(fn ($s) => (string) ($s['customer'] ?? ''), $stops))),
            ];
        }

        return ['available' => true, 'date' => $board['date'], 'tours' => $out];
    }

    /** Fehlermeldung ohne Interna (kein Link, keine Klassen). */
    private static function publicError(\Throwable $e): string
    {
        $class = '\Platform\Integrations\Exceptions\MeinConnectApiException';

        return class_exists($class) && $e instanceof $class
            ? 'Keine Verbindung zu MeinConnect. ' . $e->getMessage()
            : 'Keine Verbindung zu MeinConnect.';
    }
}
