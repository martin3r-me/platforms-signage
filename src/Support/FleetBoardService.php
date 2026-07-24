<?php

namespace Platform\Signage\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Platform\Core\Models\User;

/**
 * Liest den Tages-Tourenplan aus der DedeFleet-Integration (platforms-integrations)
 * für das Signage-Tourenplan-Board. Strikt gekapselt + per class_exists/method_exists
 * gegatet: ohne installierte Integration liefert der Service einfach nichts
 * (Board zeigt einen Hinweis).
 *
 * WICHTIG – Modul-Schnitt:
 *  - Die eigentliche API-Logik (Base-URL, Bearer-Token, Datumskonvertierung,
 *    Fehlerbehandlung) bleibt vollständig in platforms-integrations. Wir rufen nur.
 *  - Alle Abrufe laufen über den bestehenden, USER-basierten DedefleetApiService
 *    (listTours/listCustomers). KEINE Integrations-Änderung nötig:
 *      · Editor/Vorschau: der eingeloggte User.
 *      · Player (headless, kein Login): der ERSTELLER der App (SignageMedia->user_id).
 *        Dessen Connector-Zugriff (user-gebundene Freigabe) wird via
 *        forConnection($id)->listTours($user, …) → resolveById($id, $user) → canUse()
 *        geprüft. Der user_id kommt serverseitig aus dem Media-Record, nie vom Gerät.
 *  - Kunde + Adresse fehlen in Tour/List (order.location.* = null) und werden per
 *    Customer/List angereichert (Join order.location.id == customerNumber).
 */
class FleetBoardService
{
    public const INTEGRATION_KEY = 'dedefleet';

    private const API_SERVICE = \Platform\Integrations\Services\DedefleetApiService::class;
    private const RESOLVER    = \Platform\Integrations\Services\IntegrationConnectionResolver::class;

    /**
     * Ist der Live-Abruf möglich? Erfordert nur die installierte Integration mit der
     * bestehenden user-basierten listTours()-Methode (kein headless-Handoff mehr nötig).
     */
    public static function available(): bool
    {
        return class_exists(self::API_SERVICE)
            && method_exists(self::API_SERVICE, 'listTours');
    }

    /** Ist die Integration überhaupt vorhanden (fürs Editor-Dropdown). */
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
     * Tages-Tourenplan für einen User (Ersteller bzw. eingeloggter Editor) + gewählte Connection.
     *
     * @param  array{show_progress?:bool, date?:?string}  $opts
     * @return array{available:bool, error?:bool, date:string, tours:array<int,array<string,mixed>>}
     */
    public static function board(?User $user, ?int $connectionId, array $opts = []): array
    {
        $date = self::resolveDate($opts['date'] ?? null);
        $empty = ['available' => false, 'date' => $date, 'tours' => []];

        if (!self::available() || !$user || !$connectionId) {
            return $empty;
        }

        // Tagesgrenzen als ISO 8601 OHNE Zeitzonen-Offset – der ApiService konvertiert selbst
        // ins DedeFleet-Format. WICHTIG: mit Offset (+02:00) antwortet Tour/List mit HTTP 500
        // "Start is not a valid date!" (2026-07 an echter API verifiziert). Zusätzlich gilt die
        // 7-Tage-Range-Grenze der API – hier unkritisch, da immer nur ein einzelner Tag.
        $start = Carbon::parse($date)->startOfDay()->format('Y-m-d\TH:i:s');
        $end   = Carbon::parse($date)->endOfDay()->format('Y-m-d\TH:i:s');

        try {
            // forConnection() pinnt die gewählte Connection; listTours() prüft via
            // resolveById($connectionId, $user) den Zugriff des Users (canUse) und ruft
            // POST /Tour/List. Wirft DedefleetApiException bei API-/Zugriffsfehlern.
            $raw = app(self::API_SERVICE)
                ->forConnection($connectionId)
                ->listTours($user, ['start' => $start, 'end' => $end]);
        } catch (\Throwable $e) {
            return ['available' => true, 'error' => true, 'date' => $date, 'tours' => []];
        }

        $showProgress = (bool) ($opts['show_progress'] ?? true);

        // Kunde + Adresse sind in Tour/List nicht enthalten → aus Customer/List anreichern.
        $raw = self::enrichWithCustomers($raw, $user, $connectionId);

        return [
            'available' => true,
            'date'      => $date,
            'tours'     => self::normalizeTours($raw, $showProgress),
        ];
    }

    // =========================================================================
    // Anreicherung: Kunde + Adresse aus Customer/List (fehlen in Tour/List)
    // =========================================================================

    /**
     * Füllt je Order die (in Tour/List leere) location mit Name/Adresse des Kunden.
     * Join: order.location.id == customer.customerNumber. Ein Customer/List-Call pro
     * Connection, 10 min gecacht (Stammdaten ändern sich selten, viele Screens teilen sie).
     *
     * @param  mixed  $raw
     * @return mixed
     */
    private static function enrichWithCustomers($raw, User $user, int $connectionId)
    {
        if (!is_array($raw)) {
            return $raw;
        }

        $map = self::customerMap($user, $connectionId);
        if (!$map) {
            return $raw;
        }

        // Referenz DIREKT auf die Tour-Liste innerhalb von $raw (in-place anreichern) –
        // entweder ist $raw selbst die Liste oder sie steckt in einem bekannten Unterschlüssel.
        if (array_is_list($raw)) {
            $listRef = &$raw;
        } else {
            $key = null;
            foreach (['tours', 'data', 'result', 'items'] as $k) {
                if (isset($raw[$k]) && is_array($raw[$k])) {
                    $key = $k;
                    break;
                }
            }
            if ($key === null) {
                return $raw;
            }
            $listRef = &$raw[$key];
        }

        foreach ($listRef as &$tour) {
            if (!is_array($tour)) {
                continue;
            }
            foreach (['orders', 'stops', 'orderList'] as $ok) {
                if (!isset($tour[$ok]) || !is_array($tour[$ok])) {
                    continue;
                }
                foreach ($tour[$ok] as &$order) {
                    if (!is_array($order) || !isset($order['location']) || !is_array($order['location'])) {
                        continue;
                    }
                    $custNo = (string) ($order['location']['id'] ?? '');
                    if ($custNo === '' || !isset($map[$custNo])) {
                        continue;
                    }
                    // Nur leere Felder auffüllen – vorhandene Werte nie überschreiben.
                    foreach ($map[$custNo] as $mk => $mv) {
                        if ($mv !== '' && (($order['location'][$mk] ?? '') === '' || ($order['location'][$mk] ?? null) === null)) {
                            $order['location'][$mk] = $mv;
                        }
                    }
                }
                unset($order);
            }
        }
        unset($tour, $listRef);

        return $raw;
    }

    /**
     * customerNumber => ['name','street','postal','city'] aus Customer/List.
     *
     * @return array<string, array<string,string>>
     */
    private static function customerMap(User $user, int $connectionId): array
    {
        try {
            $raw = Cache::remember(
                'signage.dedefleet.customers.' . $connectionId,
                600,
                fn () => app(self::API_SERVICE)->forConnection($connectionId)->listCustomers($user),
            );
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach (self::asList($raw, ['customers', 'data', 'result', 'items']) as $c) {
            if (!is_array($c)) {
                continue;
            }
            $no = (string) (self::pick($c, ['customerNumber', 'number', 'id']) ?? '');
            if ($no === '') {
                continue;
            }
            $loc = is_array($c['location'] ?? null) ? $c['location'] : $c;
            $map[$no] = [
                'name'   => (string) (self::pick($c, ['name', 'customerName']) ?? ''),
                'street' => (string) (self::pick($loc, ['street', 'strasse', 'addressLine1']) ?? ''),
                'postal' => (string) (self::pick($loc, ['postal', 'zip', 'postalCode', 'plz']) ?? ''),
                'city'   => (string) (self::pick($loc, ['city', 'ort']) ?? ''),
            ];
        }

        return $map;
    }

    // =========================================================================
    // Normalisierung der rohen DedeFleet-Antwort ins stabile Renderer-Schema
    // =========================================================================

    /**
     * Feldnamen an einer echten Tour/List-Antwort verifiziert (2026-07-24, siehe
     * docs/dedefleet-integration-handoff.md). Die Extraktion bleibt tolerant (mehrere
     * Kandidaten je Feld); fehlende Felder bleiben leer, das Board bleibt stabil.
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

        // Verifiziert an echter Tour/List-Antwort (2026-07):
        //  - Auftragsnr steht in 'order' ("Auf1004"), Lieferschein-Nr in 'delivery' ("Lief1004").
        //  - Anlieferung/Abholung wird über 'type' (int) unterschieden, NICHT über bool-Felder.
        //  - Kunde + Adresse sind in der eingebetteten Order NICHT enthalten (order.location.*
        //    ist null); sie müssen per Customer/List (Join order.location.id == customerNumber)
        //    oder Order/Get nachgeladen werden. Siehe docs/dedefleet-integration-handoff.md.
        return [
            'va'       => (string) (self::pick($order, ['order', 'vaNumber', 'orderNumber', 'referenceNumber', 'number', 'va']) ?? ''),
            'customer' => self::customerOf($order),
            'address'  => self::addressOf($order),
            'window'   => self::windowOf($order),
            'anl'      => ((int) (self::pick($order, ['type']) ?? 0)) === 0,
            'abh'      => ((int) (self::pick($order, ['type']) ?? 0)) === 1,
            'note'     => (string) (self::pick($order, ['driverMessage', 'remark', 'note', 'comment', 'notes', 'bemerkung']) ?? ''),
            'state'    => $showProgress ? self::orderStateKey($state) : null,
        ];
    }

    private static function tourName(array $tour, int $i): string
    {
        // Verifiziert an echter Tour/List-Antwort (2026-07): der Name steht in 'tour'
        // (z.B. "Kalt-1", "Import"). Die übrigen Kandidaten bleiben als Fallback.
        $name = self::pick($tour, ['tour', 'name', 'tourName', 'title', 'label']);
        if (is_string($name) && $name !== '') {
            return $name;
        }

        return 'Tour ' . ($i + 1);
    }

    /** Kundenname: direkt am Order oder aus der verschachtelten location (DedeFleet: location.name). */
    private static function customerOf(array $order): string
    {
        $direct = self::pick($order, ['customerName', 'customer', 'clientName', 'name']);
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        $loc = $order['location'] ?? null;
        if (is_array($loc)) {
            return (string) (self::pick($loc, ['name', 'customerName']) ?? '');
        }

        return '';
    }

    private static function addressOf(array $order): string
    {
        $direct = self::pick($order, ['deliveryAddress', 'address', 'destinationAddress', 'fullAddress', 'lieferadresse']);
        if (is_string($direct) && $direct !== '') {
            return $direct;
        }

        // DedeFleet liefert die Adresse verschachtelt unter 'location' (street/postal/city);
        // in der eingebetteten Tour/List-Order ist sie leer, via Order/Get bzw. Customer-Join gefüllt.
        $src    = is_array($order['location'] ?? null) ? $order['location'] : $order;
        $street = self::pick($src, ['street', 'strasse', 'addressLine1']);
        $zip    = self::pick($src, ['postal', 'zip', 'postalCode', 'plz']);
        $city   = self::pick($src, ['city', 'ort']);
        $parts  = array_filter([
            trim((string) $street),
            trim(implode(' ', array_filter([trim((string) $zip), trim((string) $city)]))),
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
