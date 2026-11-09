<?php

namespace Platform\Signage\Support\KitaOffice;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use RuntimeException;

/**
 * Microsoft-365-Quellen des Kita-Office-Boards (Gruppenkalender, Speiseplan, Telefonliste)
 * über die User-Connection aus platform-user-connectors.
 *
 * Zugriffsmodell: Graph wird immer mit einer M365-Connection aufgerufen, auf die der
 * "Daten-User" selbst Zugriff hat (eigene oder mit ihm/seinem Team geteilte). Im Editor ist
 * das der angemeldete User, am Bildschirm der User, der die Connection zuletzt gespeichert hat
 * (config.m365_user_id, serverseitig gesetzt). Der Bildschirm selbst hat keine Zugangsdaten.
 *
 * Nur die vorhandene generische API (get mit Headern) wird genutzt; Dateien kommen über die
 * vorab signierte @microsoft.graph.downloadUrl, d. h. ohne Token-Weitergabe.
 * Per class_exists gegatet: ohne installierte user-connectors bleibt der Bereich leer.
 */
final class M365Source
{
    public const CONNECTOR_KEY = 'microsoft365';

    private const API      = \Platform\UserConnectors\Services\Microsoft365\Microsoft365ApiService::class;
    private const RESOLVER = \Platform\UserConnectors\Services\ConnectionResolver::class;

    private const FRESH_SECONDS = 600;        // 10 Minuten bis zum nächsten Graph-Abruf
    private const KEEP_SECONDS  = 7 * 86400;  // letzter guter Stand als Rückfall
    private const MAX_BYTES     = 15 * 1024 * 1024;

    public static function available(): bool
    {
        return class_exists(self::API) && class_exists(self::RESOLVER);
    }

    /** @return array<int, array{id:int, label:string}> */
    public static function connectionsForUser(?object $user): array
    {
        if (!self::available() || !$user) {
            return [];
        }

        try {
            return app(self::RESOLVER)->resolveAllForUser(self::CONNECTOR_KEY, $user)
                ->map(function ($c) use ($user) {
                    $owner = (int) $c->owner_user_id === (int) $user->id ? 'eigene' : ($c->ownerUser?->name ?? 'geteilt');

                    return ['id' => (int) $c->id, 'label' => trim(($c->name ?: 'Microsoft 365') . ' (' . $owner . ')')];
                })
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function canUse(?object $user, ?int $connectionId): bool
    {
        if (!$user || !$connectionId) {
            return false;
        }

        foreach (self::connectionsForUser($user) as $c) {
            if ($c['id'] === $connectionId) {
                return true;
            }
        }

        return false;
    }

    // ------------------------------------------------------------------

    /** Termine des Gruppenkalenders ab heute 00:00 (Berlin) für $days Tage. */
    public static function calendar(User $user, int $connectionId, string $groupId, int $days): array
    {
        return self::cached($user, $connectionId, 'cal', [$groupId, $days], function () use ($user, $connectionId, $groupId, $days) {
            $from = CarbonImmutable::now('Europe/Berlin')->startOfDay();
            $api = app(self::API)->forConnection($connectionId);
            $res = $api->get($user, '/groups/' . $api::segment($groupId) . '/calendarView', [
                'startDateTime' => $from->utc()->format('Y-m-d\TH:i:s\Z'),
                'endDateTime'   => $from->addDays($days)->utc()->format('Y-m-d\TH:i:s\Z'),
                '$select'       => 'subject,start,end,isAllDay,isCancelled',
                '$orderby'      => 'start/dateTime',
                '$top'          => 100,
            ], ['Prefer' => 'outlook.timezone="UTC"']);

            $events = [];
            foreach ((array) ($res['value'] ?? []) as $e) {
                if (!empty($e['isCancelled'])) {
                    continue;
                }
                $events[] = [
                    'subject' => mb_substr((string) ($e['subject'] ?? '') ?: 'Termin', 0, 200),
                    'start'   => self::utc($e['start']['dateTime'] ?? null),
                    'end'     => self::utc($e['end']['dateTime'] ?? null),
                    'all_day' => (bool) ($e['isAllDay'] ?? false),
                ];
            }

            return ['events' => $events, 'incomplete' => !empty($res['@odata.nextLink'])];
        });
    }

    /** Drei Tagesmenüs des heutigen Tages aus dem Monatsplan im SharePoint-Ordner. */
    public static function menus(User $user, int $connectionId, string $folderUrl, string $pattern): array
    {
        $day = CarbonImmutable::now('Europe/Berlin')->startOfDay();
        $path = OfficeFiles::menuPath($pattern, $day->toDateTimeImmutable());

        return self::cached($user, $connectionId, 'menu', [$folderUrl, $path, $day->toDateString()], function () use ($user, $connectionId, $folderUrl, $path, $day) {
            $file = self::download($user, $connectionId, $folderUrl, $path);
            $menus = OfficeFiles::menusFromDocx($file['bytes'], $day->toDateTimeImmutable());
            if ($menus === null) {
                throw new RuntimeException('Im Speiseplan fehlen die drei Tagesmenüs für heute.');
            }

            return ['date' => $day->toDateString(), 'menus' => $menus, 'file' => $file['name'], 'modified_at' => $file['modified_at']];
        });
    }

    /** Fahrer + Telefonnummer aus der Telefonliste (XLSX). */
    public static function contacts(User $user, int $connectionId, string $fileUrl): array
    {
        return self::cached($user, $connectionId, 'phones', [$fileUrl], function () use ($user, $connectionId, $fileUrl) {
            $file = self::download($user, $connectionId, $fileUrl, null);

            return ['contacts' => OfficeFiles::contactsFromXlsx($file['bytes']), 'file' => $file['name'], 'modified_at' => $file['modified_at']];
        });
    }

    // ------------------------------------------------------------------

    /**
     * Graph-Ergebnis mit Cache und Rückfall auf den letzten guten Stand.
     * Ergebnis enthält immer: ok, stale, checked_at, error (öffentliche Meldung).
     */
    private static function cached(User $user, int $connectionId, string $kind, array $params, callable $fetch): array
    {
        $key = 'signage.kitaoffice.m365.' . $kind . '.' . $connectionId . '.' . (int) $user->id . '.' . md5(json_encode($params));

        if ($hit = Cache::get($key . '.fresh')) {
            return $hit;
        }

        try {
            $data = $fetch() + ['ok' => true, 'stale' => false, 'checked_at' => now()->toAtomString(), 'error' => null];
            Cache::put($key . '.fresh', $data, self::FRESH_SECONDS);
            Cache::put($key . '.last', $data, self::KEEP_SECONDS);

            return $data;
        } catch (\Throwable $e) {
            Log::warning('Signage KitaOffice: M365-Abruf fehlgeschlagen', [
                'kind' => $kind, 'connection_id' => $connectionId, 'user_id' => $user->id,
                'exception' => $e::class, 'message' => $e->getMessage(),
            ]);

            $error = self::publicError($e);
            $last = Cache::get($key . '.last');
            // Fehler kurz merken, damit nicht jeder Bildschirm-Poll Graph erneut anfragt.
            $result = $last ? ['stale' => true, 'error' => $error] + $last : ['ok' => false, 'stale' => false, 'checked_at' => now()->toAtomString(), 'error' => $error];
            Cache::put($key . '.fresh', $result, 120);

            return $result;
        }
    }

    /**
     * Datei über einen SharePoint-/OneDrive-Link laden; optional ein Pfad relativ zum
     * verlinkten Ordner. Download über die vorab signierte URL (ohne Bearer-Token).
     *
     * @return array{bytes:string, name:string, modified_at:?string}
     */
    private static function download(User $user, int $connectionId, string $url, ?string $relPath): array
    {
        $api = app(self::API)->forConnection($connectionId);
        $shareId = 'u!' . rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
        $item = $api->get($user, '/shares/' . $shareId . '/driveItem');

        if ($relPath !== null) {
            $driveId = (string) ($item['parentReference']['driveId'] ?? '');
            if ($driveId === '' || empty($item['id'])) {
                throw new RuntimeException('Speiseplan-Ordner nicht gefunden.');
            }
            $segments = array_map(fn ($s) => rawurlencode($s), array_filter(explode('/', $relPath), fn ($s) => $s !== '' && $s !== '.' && $s !== '..'));
            $item = $api->get($user, '/drives/' . $api::segment($driveId) . '/items/' . $api::segment((string) $item['id']) . ':/' . implode('/', $segments) . ':');
        } elseif (!empty($item['folder'])) {
            throw new RuntimeException('Link zeigt auf einen Ordner statt auf die Telefonliste.');
        }

        $downloadUrl = (string) ($item['@microsoft.graph.downloadUrl'] ?? '');
        $host = (string) parse_url($downloadUrl, PHP_URL_HOST);
        if (!str_starts_with($downloadUrl, 'https://') || !preg_match('~(^|\.)sharepoint\.com$~i', $host)) {
            throw new RuntimeException('Datei ist nicht herunterladbar.');
        }
        if ((int) ($item['size'] ?? 0) > self::MAX_BYTES) {
            throw new RuntimeException('Datei ist zu groß.');
        }

        $res = Http::timeout(20)->withOptions(['allow_redirects' => false])->get($downloadUrl);
        if (!$res->successful() || strlen($res->body()) > self::MAX_BYTES) {
            throw new RuntimeException('Datei konnte nicht geladen werden.');
        }

        return [
            'bytes'       => $res->body(),
            'name'        => (string) ($item['name'] ?? ''),
            'modified_at' => isset($item['lastModifiedDateTime']) ? (string) $item['lastModifiedDateTime'] : null,
        ];
    }

    private static function utc(?string $graph): ?string
    {
        if (!$graph) {
            return null;
        }
        // Graph liefert mit Prefer UTC "2026-11-09T07:30:00.0000000" (ohne Zone).
        return substr($graph, 0, 19) . 'Z';
    }

    private static function publicError(\Throwable $e): string
    {
        // Eigene Meldungen (OfficeFiles/download) sind für den Bildschirm formuliert.
        if (!str_starts_with($e::class, 'Platform\\UserConnectors\\')) {
            return $e instanceof RuntimeException && !$e instanceof \Illuminate\Http\Client\HttpClientException
                ? $e->getMessage()
                : 'Microsoft 365 aktuell nicht erreichbar.';
        }

        $status = (int) $e->getCode();
        $code = method_exists($e, 'getErrorCode') ? (string) $e->getErrorCode() : '';

        return match (true) {
            $status === 401 || $code === 'InvalidAuthenticationToken' || str_contains($e->getMessage(), 'Kein gültiger Token')
                => 'Microsoft-365-Anmeldung abgelaufen – Verbindung neu verbinden.',
            str_contains($e->getMessage(), 'nicht gefunden oder kein Zugriff') || str_contains($e->getMessage(), 'Keine Microsoft 365')
                => 'Ausgewählte Microsoft-365-Verbindung nicht mehr verfügbar.',
            $status === 403 || in_array($code, ['Authorization_RequestDenied', 'accessDenied', 'ErrorAccessDenied'], true)
                => 'Keine Berechtigung – Zugriff auf Gruppe/Datei oder Graph-Berechtigungen prüfen.',
            $status === 404 || in_array($code, ['itemNotFound', 'ResourceNotFound', 'Request_ResourceNotFound'], true)
                => 'Datei oder Gruppe nicht gefunden.',
            $status === 429 => 'Microsoft 365 drosselt gerade – nächster Versuch automatisch.',
            default => 'Microsoft 365 aktuell nicht erreichbar.',
        };
    }
}
