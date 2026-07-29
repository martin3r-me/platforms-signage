<?php

namespace Platform\Signage\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        // Tagesgrenzen als REINES ISO-Datum (Y-m-d) – der ApiService konvertiert das zu
        // DedeFleet "DD.MM.YYYY". WICHTIG: Tour/List akzeptiert für start/end NUR ein Datum
        // ohne Uhrzeit. Mit Uhrzeit ("29.07.2026 00:00:00") ODER mit Zeitzonen-Offset
        // antwortet die API mit HTTP 500 "Start is not a valid date!" (2026-07-29 an echter
        // API verifiziert). Deshalb kein startOfDay()/endOfDay(). Die 7-Tage-Range-Grenze der
        // API ist hier unkritisch (immer nur ein einzelner Tag).
        $start = Carbon::parse($date)->format('Y-m-d');
        $end   = Carbon::parse($date)->format('Y-m-d');

        try {
            $raw = self::fetchTours($user, $connectionId, $start, $end);
        } catch (\Throwable $e) {
            // Nicht still verschlucken – sonst ist ein API-/Zugriffsfehler von einem
            // echten "keine Touren" nicht zu unterscheiden.
            Log::warning('Signage DedeFleet board() failed', [
                'connection_id' => $connectionId,
                'user_id'       => $user->id ?? null,
                'date'          => $date,
                'exception'     => $e::class,
                'message'       => $e->getMessage(),
            ]);

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

    /**
     * Ruft Tour/List für die gewählte Connection – mit Fallback.
     *
     * Schlägt die explizit gewählte Connection am Zugriff fehl, fällt der Abruf auf die per
     * resolveForUser auflösbare Connection des Users zurück. Hintergrund: eine dem User nur
     * TEAM-weit freigegebene Connection erscheint zwar im Dropdown (resolveAllForUser ehrt
     * Team-Shares), aber forConnection()->listTours() prüft via resolveById()/canUse() OHNE
     * Team-Kontext und lehnt team-scoped Shares ab → "keine Connection". resolveForUser (ohne
     * forConnection) berücksichtigt Team-Freigaben und liefert dieselbe Connection. So bleibt
     * das Board nutzbar, ohne Eingriff in platforms-integrations.
     *
     * @return mixed  rohe Tour/List-Antwort; wirft weiter, wenn auch der Fallback scheitert.
     */
    private static function fetchTours(User $user, int $connectionId, string $start, string $end): mixed
    {
        $filter = ['start' => $start, 'end' => $end];

        try {
            return app(self::API_SERVICE)->forConnection($connectionId)->listTours($user, $filter);
        } catch (\Throwable $e) {
            Log::info('Signage DedeFleet: gewählte Connection nicht direkt nutzbar, Fallback auf Default-Auflösung', [
                'connection_id' => $connectionId,
                'user_id'       => $user->id ?? null,
                'message'       => $e->getMessage(),
            ]);

            return app(self::API_SERVICE)->listTours($user, $filter);
        }
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
                    if (!is_array($order)) {
                        continue;
                    }
                    // Die Kundennummer steckt im notes-Feld ("Kundennr: 42"), NICHT in
                    // location.id (das ist null). Darüber joinen wir Name + Adresse.
                    $custNo = self::customerNumberFromNotes((string) ($order['notes'] ?? ''));
                    if ($custNo === '' || !isset($map[$custNo])) {
                        continue;
                    }
                    if (!isset($order['location']) || !is_array($order['location'])) {
                        $order['location'] = [];
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
                function () use ($connectionId, $user) {
                    // Gleicher Fallback wie bei den Touren (team-scoped Share → forConnection scheitert).
                    try {
                        return app(self::API_SERVICE)->forConnection($connectionId)->listCustomers($user);
                    } catch (\Throwable $e) {
                        return app(self::API_SERVICE)->listCustomers($user);
                    }
                },
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
                'name'   => trim((string) (self::pick($c, ['name', 'customerName']) ?? '')),
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
                // vehicleApiID ist eine interne Zahl (kein Kennzeichen) und via VehicleProfile/List
                // NICHT auflösbar → NICHT anzeigen. Nur echte Klartext-Felder verwenden.
                'vehicle'   => (string) (self::pick($tour, ['vehicleName', 'vehicleLabel', 'licenseNumber']) ?? ''),
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
            'note'     => self::noteOf($order),
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

    /** Extrahiert die Kundennummer aus dem notes-Feld ("Kundennr: 42" → "42"). */
    private static function customerNumberFromNotes(string $notes): string
    {
        if (preg_match('/Kundennr\.?\s*:?\s*([A-Za-z0-9\-]+)/i', $notes, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Anzeige-Bemerkung. In Tour/List ist die eigentliche Fahrer-Notiz (driverMessage) NICHT
     * enthalten – dort steht in notes nur der Kundennr-Marker ("Kundennr: 42"), der nicht
     * angezeigt werden darf. Reihenfolge: driverMessage/remark/comment, sonst notes OHNE den
     * Kundennr-Marker (der volle driverMessage käme nur via Order/Get, N Calls).
     */
    private static function noteOf(array $order): string
    {
        $direct = self::pick($order, ['driverMessage', 'remark', 'comment', 'bemerkung']);
        if (is_string($direct) && trim($direct) !== '') {
            return trim($direct);
        }

        $notes = (string) (self::pick($order, ['notes', 'note']) ?? '');
        $notes = preg_replace('/Kundennr\.?\s*:?\s*[A-Za-z0-9\-]+/i', '', $notes);

        return trim((string) $notes);
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
