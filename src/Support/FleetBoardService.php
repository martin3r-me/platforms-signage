<?php

namespace Platform\Signage\Support;

use Illuminate\Support\Carbon;

/**
 * Liest den Tages-Tourenplan aus der DedeFleet-Integration (platforms-integrations)
 * für das Signage-Tourenplan-Board. Strikt gekapselt + per class_exists/method_exists
 * gegatet: ohne installierte/erweiterte Integration liefert der Service einfach nichts
 * (Board zeigt einen Hinweis).
 *
 * WICHTIG – Modul-Schnitt:
 *  - Die eigentliche API-Logik (Base-URL, Bearer-Token, Datumskonvertierung,
 *    Fehlerbehandlung) bleibt vollständig in platforms-integrations. Wir rufen nur.
 *  - Das Editor-Dropdown nutzt die BESTEHENDE Methode
 *    IntegrationConnectionResolver::resolveAllForUser() (read-only, User-Kontext).
 *  - Der headless Player-Abruf braucht EINE neue Methode in platforms-integrations:
 *    DedefleetApiService::callForTeam(int $teamId, int $connectionId, string $method,
 *        string $endpoint, array $payload = []): ?array
 *    Sie prüft die Team-Zugehörigkeit der Connection und ruft die API ohne User.
 *    Solange sie fehlt, meldet available() => false und das Board zeigt
 *    "Datenquelle wird vorbereitet" statt zu crashen. Siehe docs/dedefleet-integration-handoff.md.
 */
class FleetBoardService
{
    public const INTEGRATION_KEY = 'dedefleet';

    private const API_SERVICE = \Platform\Integrations\Services\DedefleetApiService::class;
    private const RESOLVER    = \Platform\Integrations\Services\IntegrationConnectionResolver::class;

    /**
     * Ist der Live-Abruf möglich? Erfordert die Integration UND den headless-Einstieg
     * (callForTeam), den der Kollege in platforms-integrations ergänzt.
     */
    public static function available(): bool
    {
        return class_exists(self::API_SERVICE)
            && method_exists(self::API_SERVICE, 'callForTeam');
    }

    /** Ist die Integration überhaupt vorhanden (fürs Editor-Dropdown, unabhängig von callForTeam)? */
    public static function integrationPresent(): bool
    {
        return class_exists(self::RESOLVER) && class_exists(self::API_SERVICE);
    }

    /**
     * DedeFleet-Connections, die der eingeloggte User im Editor wählen kann
     * (eigene + fürs Team geteilte). Read-only über die bestehende Resolver-API.
     *
     * @return array<int, array{id:int, label:string}>
     */
    public static function connectionsForUser(?object $user): array
    {
        if (!self::integrationPresent() || !$user) {
            return [];
        }

        try {
            $connections = app(self::RESOLVER)->resolveAllForUser(self::INTEGRATION_KEY, $user);
        } catch (\Throwable $e) {
            return [];
        }

        return $connections
            ->map(fn ($c) => [
                'id'    => (int) $c->id,
                'label' => (string) ($c->name ?: ('Connection #' . $c->id)),
            ])
            ->values()
            ->all();
    }

    /**
     * Tages-Tourenplan für ein Team + eine gewählte Connection.
     *
     * @param  array{show_progress?:bool, date?:?string}  $opts
     * @return array{available:bool, error?:bool, date:string, tours:array<int,array<string,mixed>>}
     */
    public static function board(int $teamId, ?int $connectionId, array $opts = []): array
    {
        $date = self::resolveDate($opts['date'] ?? null);
        $empty = ['available' => false, 'date' => $date, 'tours' => []];

        if (!self::available() || $teamId <= 0 || !$connectionId) {
            return $empty;
        }

        // Tagesgrenzen als ISO 8601 – der ApiService konvertiert selbst ins DedeFleet-Format.
        $start = Carbon::parse($date)->startOfDay()->toIso8601String();
        $end   = Carbon::parse($date)->endOfDay()->toIso8601String();

        try {
            $raw = app(self::API_SERVICE)->callForTeam(
                $teamId,
                $connectionId,
                'POST',
                'Tour/List',
                ['start' => $start, 'end' => $end],
            );
        } catch (\Throwable $e) {
            return ['available' => true, 'error' => true, 'date' => $date, 'tours' => []];
        }

        if ($raw === null) {
            // Connection gehört nicht zum Team / nicht auflösbar.
            return $empty;
        }

        $showProgress = (bool) ($opts['show_progress'] ?? true);

        return [
            'available' => true,
            'date'      => $date,
            'tours'     => self::normalizeTours($raw, $showProgress),
        ];
    }

    // =========================================================================
    // Normalisierung der rohen DedeFleet-Antwort ins stabile Renderer-Schema
    // =========================================================================

    /**
     * ACHTUNG: Die genauen Feldnamen der Tour/List-Antwort sind im Repo nicht
     * dokumentiert (keine DTOs/Fixtures). Die Extraktion ist deshalb bewusst
     * tolerant (mehrere mögliche Schlüssel pro Feld). Sobald eine echte Response
     * vorliegt, hier gegen die tatsächlichen Feldnamen justieren – siehe die
     * TODO-Marker. Fehlende Felder bleiben schlicht leer, das Board bleibt stabil.
     *
     * @param  mixed  $raw
     * @return array<int, array<string,mixed>>
     */
    private static function normalizeTours($raw, bool $showProgress): array
    {
        $tours = self::asList($raw, ['tours', 'data', 'result', 'items']);
        $out = [];

        foreach ($tours as $i => $tour) {
            if (!is_array($tour)) {
                continue;
            }

            $stops = [];
            foreach (self::asList($tour, ['orders', 'stops', 'orderList']) as $order) {
                if (!is_array($order)) {
                    continue;
                }
                $stops[] = self::normalizeStop($order, $showProgress);
            }

            $out[] = [
                'id'        => (string) (self::pick($tour, ['tourGuid', 'guid', 'id']) ?: ('t' . $i)),
                'name'      => self::tourName($tour, $i),
                'departure' => self::timeOf(self::pickNested($tour, [['departure', 'time'], ['departureTime'], ['startTime'], ['departure']])),
                'driver'    => (string) (self::pick($tour, ['driverName', 'driver', 'driverDisplayName']) ?? ''),
                'vehicle'   => (string) (self::pick($tour, ['vehicleName', 'vehicleLabel', 'licenseNumber', 'vehicleApiID', 'vehicle']) ?? ''),
                'status'    => self::tourStatusLabel(self::pick($tour, ['status', 'tourStatus'])),
                'stops'     => $stops,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $order
     * @return array<string,mixed>
     */
    private static function normalizeStop(array $order, bool $showProgress): array
    {
        $state = self::pick($order, ['orderStatus', 'orderState', 'status']);

        return [
            // TODO(verify): Feldnamen an echter Order-Struktur prüfen.
            'va'       => (string) (self::pick($order, ['vaNumber', 'orderNumber', 'referenceNumber', 'number', 'va']) ?? ''),
            'customer' => (string) (self::pick($order, ['customerName', 'customer', 'clientName', 'name']) ?? ''),
            'address'  => self::addressOf($order),
            'window'   => self::windowOf($order),
            'anl'      => (bool) (self::pick($order, ['delivery', 'isDelivery', 'anlieferung', 'anl']) ?? true),
            'abh'      => (bool) (self::pick($order, ['pickup', 'isPickup', 'abholung', 'abh']) ?? false),
            'note'     => (string) (self::pick($order, ['remark', 'note', 'comment', 'notes', 'bemerkung']) ?? ''),
            'state'    => $showProgress ? self::orderStateKey($state) : null,
        ];
    }

    private static function tourName(array $tour, int $i): string
    {
        $name = self::pick($tour, ['name', 'tourName', 'title', 'label']);
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'Tour ' . ($i + 1);
    }

    private static function addressOf(array $order): string
    {
        $direct = self::pick($order, ['deliveryAddress', 'address', 'destinationAddress', 'fullAddress', 'lieferadresse']);
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        // Ggf. aus Einzelteilen zusammensetzen.
        $street = self::pick($order, ['street', 'strasse', 'addressLine1']);
        $zip    = self::pick($order, ['zip', 'postalCode', 'plz']);
        $city   = self::pick($order, ['city', 'ort']);
        $parts  = array_filter([
            trim((string) $street),
            trim(implode(' ', array_filter([(string) $zip, (string) $city]))),
        ]);

        return implode(', ', $parts);
    }

    private static function windowOf(array $order): string
    {
        $from = self::timeOf(self::pick($order, ['windowFrom', 'timeWindowFrom', 'arrivalFrom', 'tourArrival', 'eta']));
        $to   = self::timeOf(self::pick($order, ['windowTo', 'timeWindowTo', 'arrivalTo']));

        if ($from && $to) {
            return $from . '–' . $to;
        }

        return $from ?: $to ?: '';
    }

    /** DedeFleet-Tour-Status: 0=Planung, 1=Freigegeben, 2=Abgeschlossen. */
    private static function tourStatusLabel($status): string
    {
        return match ((int) $status) {
            0       => 'Planung',
            1       => 'Freigegeben',
            2       => 'Abgeschlossen',
            default => '',
        };
    }

    /** DedeFleet-Order-Status: 0=Offen,1=Gelesen,2=Aktiv,3=Erledigt,4=Gelöscht,5=In Navigation. */
    private static function orderStateKey($state): string
    {
        return match ((int) $state) {
            3       => 'done',
            2, 5    => 'active',
            4       => 'deleted',
            default => 'open',
        };
    }

    // =========================================================================
    // Hilfsfunktionen (tolerante Extraktion)
    // =========================================================================

    private static function resolveDate(?string $date): string
    {
        if ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return now()->toDateString();
    }

    /** Nimmt den ersten vorhandenen (nicht-null) Schlüssel aus $keys. */
    private static function pick($array, array $keys)
    {
        if (!is_array($array)) {
            return null;
        }
        foreach ($keys as $k) {
            if (array_key_exists($k, $array) && $array[$k] !== null && $array[$k] !== '') {
                return $array[$k];
            }
        }

        return null;
    }

    /** Nimmt den ersten vorhandenen Pfad (verschachtelte Schlüssel) aus $paths. */
    private static function pickNested($array, array $paths)
    {
        foreach ($paths as $path) {
            $cur = $array;
            $ok = true;
            foreach ((array) $path as $seg) {
                if (is_array($cur) && array_key_exists($seg, $cur)) {
                    $cur = $cur[$seg];
                } else {
                    $ok = false;
                    break;
                }
            }
            if ($ok && $cur !== null && $cur !== '') {
                return $cur;
            }
        }

        return null;
    }

    /**
     * Findet in einer Antwort die Liste (entweder direkt ein Array oder unter einem
     * der angegebenen Schlüssel).
     *
     * @return array<int, mixed>
     */
    private static function asList($value, array $keys): array
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return $value;
            }
            foreach ($keys as $k) {
                if (isset($value[$k]) && is_array($value[$k])) {
                    return array_is_list($value[$k]) ? $value[$k] : array_values($value[$k]);
                }
            }
        }

        return [];
    }

    /** Extrahiert HH:MM aus diversen Zeit-/Datumsformaten (ISO, "DD.MM.YYYY HH:mm", "HH:mm"). */
    private static function timeOf($value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }
        if (preg_match('/(\d{1,2}):(\d{2})/', $value, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
        }

        return '';
    }
}
