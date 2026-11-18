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
                ->map(fn ($c) => ['id' => (int) $c->id, 'label' => self::connectionLabel($c, $user)])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * "Christian Wolf · c.wolf@… " (eigene) bzw. "Patrick Hirt (geteilt)". Das Konto steht in den
     * Credentials und wird – wie in user-connectors – nur bei eigenen Verbindungen gelesen.
     */
    private static function connectionLabel(object $c, object $user): string
    {
        $own = (int) $c->owner_user_id === (int) $user->id;
        $owner = $own ? (string) ($user->name ?? '') : (string) ($c->ownerUser?->name ?? 'unbekannt');
        $account = '';
        if ($own) {
            try {
                $profile = ($c->credentials ?? [])['profile'] ?? [];
                $account = (string) ($profile['mail'] ?? $profile['userPrincipalName'] ?? '');
            } catch (\Throwable $e) {
                $account = '';
            }
        }
        $name = trim((string) ($c->name ?? ''));
        $suffix = $name !== '' && $name !== 'Microsoft 365' ? ' – ' . $name : '';

        return trim($owner . ($account !== '' ? ' · ' . mb_strtolower($account) : '') . ($own ? '' : ' (geteilt)') . $suffix);
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
    public static function menus(User $user, int $connectionId, array|string $folder, string $pattern): array
    {
        $day = CarbonImmutable::now('Europe/Berlin')->startOfDay();
        $path = OfficeFiles::menuPath($pattern, $day->toDateTimeImmutable());

        return self::cached($user, $connectionId, 'menu', [self::sourceKey($folder), $path, $day->toDateString()], function () use ($user, $connectionId, $folder, $path, $day) {
            $file = self::download($user, $connectionId, $folder, $path);
            $menus = OfficeFiles::menusFromDocx($file['bytes'], $day->toDateTimeImmutable());
            if ($menus === null) {
                throw new RuntimeException('Im Speiseplan fehlen die drei Tagesmenüs für heute.');
            }

            return ['date' => $day->toDateString(), 'menus' => $menus, 'file' => $file['name'], 'modified_at' => $file['modified_at']];
        });
    }

    /** Fahrer + Telefonnummer aus der Telefonliste (XLSX). */
    public static function contacts(User $user, int $connectionId, array|string $file): array
    {
        return self::cached($user, $connectionId, 'phones', [self::sourceKey($file)], function () use ($user, $connectionId, $file) {
            $file = self::download($user, $connectionId, $file, null);

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
     * Element hinter einer Quelle: ausgewählte Datei/Ordner (drive_id + item_id) oder
     * SharePoint-/OneDrive-Link; optional ein Pfad relativ zum Ordner.
     */
    private static function item(object $api, User $user, array|string $source, ?string $relPath): array
    {
        if (is_array($source)) {
            $driveId = (string) $source['drive_id'];
            $item = $api->get($user, '/drives/' . $api::segment($driveId) . '/items/' . $api::segment((string) $source['item_id']));
        } else {
            $shareId = 'u!' . rtrim(strtr(base64_encode($source), '+/', '-_'), '=');
            $item = $api->get($user, '/shares/' . $shareId . '/driveItem');
            $driveId = (string) ($item['parentReference']['driveId'] ?? '');
        }

        // Datei direkt ausgewählt/verlinkt: kein Monatsmuster nötig.
        if ($relPath === null || isset($item['file'])) {
            return $item;
        }
        if ($driveId === '' || empty($item['id']) || !isset($item['folder'])) {
            throw new RuntimeException('Speiseplan-Ordner nicht gefunden.');
        }
        $segments = array_map(fn ($s) => rawurlencode($s), array_filter(explode('/', $relPath), fn ($s) => $s !== '' && $s !== '.' && $s !== '..'));

        return $api->get($user, '/drives/' . $api::segment($driveId) . '/items/' . $api::segment((string) $item['id']) . ':/' . implode('/', $segments) . ':');
    }

    /**
     * Datei laden (siehe item()). Download über die vorab signierte URL (ohne Bearer-Token).
     *
     * @return array{bytes:string, name:string, modified_at:?string}
     */
    private static function download(User $user, int $connectionId, array|string $source, ?string $relPath): array
    {
        $api = app(self::API)->forConnection($connectionId);
        $item = self::item($api, $user, $source, $relPath);
        if ($relPath === null && isset($item['folder'])) {
            throw new RuntimeException('Ausgewählt ist ein Ordner statt der Telefonliste.');
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

    private static function sourceKey(array|string $source): string
    {
        return is_array($source) ? 'item:' . $source['drive_id'] . '/' . $source['item_id'] : 'url:' . $source;
    }

    // ------------------------------------------------------------------
    // Editor: Auswahl von Kalender-Gruppe, Ordner und Datei (immer als angemeldeter User,
    // nur mit einer Connection, die er selbst nutzen darf – prüft der Aufrufer).
    // ------------------------------------------------------------------

    /**
     * Microsoft-365-Gruppen (mit Gruppenkalender + SharePoint), in denen das Konto Mitglied ist.
     *
     * @return list<array{id:string, name:string}>
     */
    public static function groups(User $user, int $connectionId): array
    {
        $api = app(self::API)->forConnection($connectionId);
        $res = $api->get($user, '/me/memberOf', ['$select' => 'id,displayName,groupTypes', '$top' => 999]);

        $groups = [];
        for ($page = 0; ; $page++) {
            foreach ((array) ($res['value'] ?? []) as $g) {
                if (($g['@odata.type'] ?? '') === '#microsoft.graph.group' && in_array('Unified', (array) ($g['groupTypes'] ?? []), true) && !empty($g['id'])) {
                    $groups[] = ['id' => (string) $g['id'], 'name' => (string) ($g['displayName'] ?? $g['id'])];
                }
            }
            if ($page >= 4 || empty($res['@odata.nextLink'])) {
                break;
            }
            $res = $api->getNextPage($user, (string) $res['@odata.nextLink']);
        }

        usort($groups, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $groups;
    }

    /**
     * Eine Ebene des SharePoint-Browsers.
     *   []                                   → Gruppen (deren Dokumentbibliothek) + gefolgte Websites
     *   ['type'=>'group','id'=>…]            → Bibliotheken der Gruppen-Website
     *   ['type'=>'site','id'=>…]             → Bibliotheken der Website
     *   ['type'=>'folder','drive_id'=>…, 'item_id'=>…|'root'] → Unterordner + Dateien
     *
     * Einträge: {type: group|site|drive|folder|file, name, ...ids}. Dateien nur mit $fileExt.
     *
     * @return list<array<string, string>>
     */
    public static function browse(User $user, int $connectionId, array $at, string $fileExt = ''): array
    {
        $api = app(self::API)->forConnection($connectionId);
        $type = (string) ($at['type'] ?? '');

        if ($type === '') {
            $entries = array_map(fn ($g) => ['type' => 'group', 'id' => $g['id'], 'name' => $g['name']], self::groups($user, $connectionId));
            try {
                $sites = $api->get($user, '/me/followedSites', ['$select' => 'id,displayName,name']);
                foreach ((array) ($sites['value'] ?? []) as $site) {
                    if (!empty($site['id'])) {
                        $entries[] = ['type' => 'site', 'id' => (string) $site['id'], 'name' => (string) ($site['displayName'] ?? $site['name'] ?? 'Website')];
                    }
                }
            } catch (\Throwable $e) {
                // Gefolgte Websites sind optional – Gruppen reichen für den Einstieg.
            }

            return $entries;
        }

        if ($type === 'group' || $type === 'site') {
            $path = ($type === 'group' ? '/groups/' : '/sites/') . $api::segment((string) $at['id']) . '/drives';
            $res = $api->get($user, $path, ['$select' => 'id,name']);

            return array_values(array_map(
                fn ($d) => ['type' => 'drive', 'drive_id' => (string) $d['id'], 'item_id' => 'root', 'name' => (string) ($d['name'] ?? 'Dokumente')],
                array_filter((array) ($res['value'] ?? []), fn ($d) => !empty($d['id']))
            ));
        }

        $driveId = (string) ($at['drive_id'] ?? '');
        $itemId = (string) ($at['item_id'] ?? 'root');
        $base = '/drives/' . $api::segment($driveId) . ($itemId === 'root' ? '/root' : '/items/' . $api::segment($itemId));
        $res = $api->get($user, $base . '/children', ['$select' => 'id,name,folder,file', '$top' => 500]);

        $folders = $files = [];
        foreach ((array) ($res['value'] ?? []) as $c) {
            if (empty($c['id'])) {
                continue;
            }
            $row = ['drive_id' => $driveId, 'item_id' => (string) $c['id'], 'name' => (string) ($c['name'] ?? '')];
            if (isset($c['folder'])) {
                $folders[] = ['type' => 'folder'] + $row;
            } elseif ($fileExt !== '' && str_ends_with(mb_strtolower($row['name']), $fileExt)) {
                $files[] = ['type' => 'file'] + $row;
            }
        }
        $byName = fn ($a, $b) => strnatcasecmp($a['name'], $b['name']);
        usort($folders, $byName);
        usort($files, $byName);

        return array_merge($folders, $files);
    }

    /** Sofort-Prüfung im Editor: Speiseplan (Datei bzw. Monatsdatei im Ordner) mit Menüs für heute? */
    public static function probeMenu(User $user, int $connectionId, array|string $source, string $pattern): string
    {
        $day = CarbonImmutable::now('Europe/Berlin')->startOfDay()->toDateTimeImmutable();
        $path = OfficeFiles::menuPath($pattern, $day);
        try {
            $file = self::download($user, $connectionId, $source, $path);

            return OfficeFiles::menusFromDocx($file['bytes'], $day) !== null
                ? 'Heutige Menüs gefunden in „' . $file['name'] . '“ ✓'
                : '„' . $file['name'] . '“ gefunden, aber ohne Menüs für heute.';
        } catch (\Throwable $e) {
            $isFile = is_array($source) && ($source['kind'] ?? '') === 'file';

            // Eigene Meldungen tragen keinen HTTP-Code; 404 kommt nur aus Graph.
            return !$isFile && (int) $e->getCode() === 404
                ? 'Für diesen Monat noch keine Datei gefunden: ' . $path
                : self::publicError($e);
        }
    }

    /** Sofort-Prüfung im Editor: Wie viele Fahrer liest die Telefonliste? */
    public static function probeContacts(User $user, int $connectionId, array|string $file): string
    {
        try {
            $count = count(OfficeFiles::contactsFromXlsx(self::download($user, $connectionId, $file, null)['bytes']));

            return $count > 0 ? $count . ' Einträge gelesen ✓' : 'Keine Einträge gefunden – Spalten „Fahrer“ und „Nummer“/„Telefon“ prüfen.';
        } catch (\Throwable $e) {
            return self::publicError($e);
        }
    }

    /** Öffentliche Meldung für den Editor (gleiche Texte wie am Bildschirm). */
    public static function editorError(\Throwable $e): string
    {
        return self::publicError($e);
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
