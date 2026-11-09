<?php

namespace Platform\Signage\Support\KitaOffice;

/**
 * Konfiguration der Kita-Office-App (app_type=kitaoffice): Standardwerte und
 * Bereinigung. Wird vom Editor beim Speichern und vom Vorschau-Endpoint genutzt,
 * damit beide dieselben Regeln anwenden. Reine Logik, ohne Laravel.
 *
 * Fachliche Regeln (Fahrer-Aliase, "nur WhatsApp", Einrichtungstelefone) stammen aus
 * Patricks Prototyp und sind hier bewusst KONFIGURATION statt Code: sie ändern sich
 * mit dem Personal.
 */
final class KitaOfficeConfig
{
    public const MAX_LIST = 100;

    public static function defaults(): array
    {
        return [
            'title'                   => 'Kita Office Dashboard',
            'meinconnect_connection_id' => null,
            'dedefleet_connection_id' => null,
            'city'                    => 'Düsseldorf',
            'latitude'                => 51.23,
            'longitude'               => 6.78,
            'boxes_per_page'          => 3,
            'box_page_seconds'        => 20,
            'offline_minutes'         => 5,
            'door_warning_minutes'    => 5,
            'door_warning_until'      => '11:00',
            'hide_pickups'            => true,
            'strip_prefixes'          => ['Onway'],
            'driver_aliases'          => [
                ['from' => 'Mimi', 'to' => 'Hicham'],
                ['from' => 'Serdat', 'to' => 'Sedat'],
                ['from' => 'Thomas G', 'to' => 'Thomas Giesecke'],
                ['from' => 'T. Giesecke', 'to' => 'Thomas Giesecke'],
            ],
            'whatsapp_only'           => ['Hicham', 'Salim'],
            'drivers'                 => [],   // [{name, phone}] – manuelle Telefonliste (bis die M365-Liste angebunden ist)
            'facility_phones'         => [
                ['match' => 'Lebensbaum', 'phone' => '02161 9023605'],
                ['match' => 'Herrenshoff', 'phone' => '02161 644105'],
            ],
        ];
    }

    /** Bereinigt eine (ggf. vom Client stammende) Konfiguration. */
    public static function sanitize(array $cfg): array
    {
        $d = self::defaults();

        $id = static fn ($v) => is_numeric($v) && (int) $v > 0 ? (int) $v : null;
        $str = static fn ($v, int $max) => mb_substr(trim((string) $v), 0, $max);
        $int = static fn ($v, int $min, int $max, int $def) => is_numeric($v) ? max($min, min($max, (int) $v)) : $def;
        $float = static fn ($v, float $min, float $max, float $def) => is_numeric($v) ? max($min, min($max, (float) $v)) : $def;

        $until = (string) ($cfg['door_warning_until'] ?? $d['door_warning_until']);
        if (!preg_match('~^([01]\d|2[0-3]):[0-5]\d$~', $until)) {
            $until = $d['door_warning_until'];
        }

        return [
            'title'                     => $str($cfg['title'] ?? $d['title'], 120),
            'meinconnect_connection_id' => $id($cfg['meinconnect_connection_id'] ?? null),
            'dedefleet_connection_id'   => $id($cfg['dedefleet_connection_id'] ?? null),
            'city'                      => $str($cfg['city'] ?? $d['city'], 80),
            'latitude'                  => $float($cfg['latitude'] ?? null, -90, 90, $d['latitude']),
            'longitude'                 => $float($cfg['longitude'] ?? null, -180, 180, $d['longitude']),
            'boxes_per_page'            => $int($cfg['boxes_per_page'] ?? null, 1, 8, $d['boxes_per_page']),
            'box_page_seconds'          => $int($cfg['box_page_seconds'] ?? null, 5, 300, $d['box_page_seconds']),
            'offline_minutes'           => $int($cfg['offline_minutes'] ?? null, 1, 120, $d['offline_minutes']),
            'door_warning_minutes'      => $int($cfg['door_warning_minutes'] ?? null, 1, 120, $d['door_warning_minutes']),
            'door_warning_until'        => $until,
            'hide_pickups'              => (bool) ($cfg['hide_pickups'] ?? $d['hide_pickups']),
            'strip_prefixes'            => self::strings($cfg['strip_prefixes'] ?? $d['strip_prefixes'], 60),
            'driver_aliases'            => self::rows($cfg['driver_aliases'] ?? $d['driver_aliases'], ['from' => 80, 'to' => 80], ['from', 'to']),
            'whatsapp_only'             => self::strings($cfg['whatsapp_only'] ?? $d['whatsapp_only'], 80),
            'drivers'                   => self::rows($cfg['drivers'] ?? [], ['name' => 80, 'phone' => 40], ['name', 'phone']),
            'facility_phones'           => self::rows($cfg['facility_phones'] ?? $d['facility_phones'], ['match' => 80, 'phone' => 40], ['match', 'phone']),
        ];
    }

    /** Liste von Strings; akzeptiert auch kommagetrennten Text aus einem Eingabefeld. */
    private static function strings($value, int $max): array
    {
        if (is_string($value)) {
            $value = preg_split('~[,;\n]~', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $v) {
            $v = mb_substr(trim((string) (is_scalar($v) ? $v : '')), 0, $max);
            if ($v !== '') {
                $out[] = $v;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, self::MAX_LIST);
    }

    /**
     * @param array<string,int> $fields  Feld => Maximallänge
     * @param list<string>      $required Zeilen ohne diese Felder werden verworfen
     */
    private static function rows($value, array $fields, array $required): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }
            $clean = [];
            foreach ($fields as $f => $max) {
                $clean[$f] = mb_substr(trim((string) (is_scalar($row[$f] ?? null) ? $row[$f] : '')), 0, $max);
            }
            foreach ($required as $f) {
                if ($clean[$f] === '') {
                    continue 2;
                }
            }
            $out[] = $clean;
        }

        return array_slice($out, 0, self::MAX_LIST);
    }
}
