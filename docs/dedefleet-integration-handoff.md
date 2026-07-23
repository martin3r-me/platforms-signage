# Handoff: headless-Einstieg für die DedeFleet-Tourenplan-App

**An:** Betreuer von `platforms-integrations`
**Von:** Signage (Tourenplan-App, `app_type = dedefleet`)
**Aufwand:** eine neue Methode (~15–25 Zeilen), additiv, keine Breaking Changes.

## Warum

Die neue Signage-App „Tourenplan" zeigt den DedeFleet-Tagesplan live auf Displays.
Der Player läuft **headless** (nur Geräte-Token, **kein eingeloggter User**). Der
bestehende `DedefleetApiService` verlangt aber bei jedem Call ein `User`-Objekt
(`listTours(User $user, …)`, intern `resolveConnection(User $user)`).

Damit Signage die **bestehende API-Schicht wiederverwendet** (Base-URL, Bearer-Token,
Datumskonvertierung, Fehlerbehandlung) statt sie zu duplizieren, brauchen wir **einen
einzigen** user-losen, team-abgesicherten Einstieg.

Signage ruft ihn ausschließlich über `Platform\Signage\Support\FleetBoardService`
auf und ist bereits so gebaut, dass es sauber „Datenquelle wird vorbereitet" anzeigt,
solange die Methode fehlt (`method_exists`-Gate). Sobald sie da ist, geht das Board
**automatisch** live – keine weitere Signage-Änderung nötig.

## Die Methode

In `Platform\Integrations\Services\DedefleetApiService`:

```php
/**
 * Headless-Aufruf im Team-Kontext (ohne eingeloggten User) – für Consumer wie
 * Signage-Displays. Prüft, dass die Connection zum Team gehört (Owner ist
 * Team-Mitglied ODER explizit fürs Team geteilt) und den DedeFleet-Key hat,
 * und ruft dann die API.
 *
 * @return array|null  Rohe API-Antwort, oder null wenn die Connection nicht
 *                     zum Team auflösbar ist. Bei API-Fehlern: DedefleetApiException.
 */
public function callForTeam(
    int $teamId,
    int $connectionId,
    string $method,      // 'GET' | 'POST'
    string $endpoint,    // z.B. 'Tour/List'
    array $payload = []
): ?array;
```

### Semantik / Vertrag

1. **Auflösen + absichern:** Connection per `$connectionId` laden. Nur zurückgeben, wenn
   - `integration->key === 'dedefleet'` **und**
   - sie dem Team `$teamId` gehört: Owner ist Mitglied des Teams **oder** sie ist per
     `shares` fürs Team geteilt (gleiche Logik wie `IntegrationConnectionResolver::resolveForTeam()`).
   - sonst → **`null`** (Signage zeigt dann „nicht verfügbar", kein Fehler).
2. **Call:** über den bestehenden `request()`-Pfad mit dem Token dieser Connection
   (ISO-Datumskonvertierung, Bearer-Header, `handleResponse()` unverändert nutzen).
3. **Fehler:** API-/HTTP-Fehler wie gehabt als `DedefleetApiException` werfen – Signage
   fängt das ab und zeigt einen neutralen Hinweis.

### Vorschlag zur Umsetzung

Am einfachsten eine team-scoped Resolver-Methode + ein user-loser Request-Pfad:

```php
// IntegrationConnectionResolver – neu:
public function resolveByIdForTeam(int $connectionId, int $teamId): ?IntegrationConnection
{
    $team = Team::find($teamId);
    if (!$team) return null;

    $conn = IntegrationConnection::with('integration')->find($connectionId);
    if (!$conn || optional($conn->integration)->key !== 'dedefleet') return null;

    $memberIds = $team->users()->pluck('users.id')->toArray();
    $ownedByTeam = in_array($conn->owner_user_id, $memberIds, true);
    $sharedWithTeam = $conn->shares()->where('team_id', $team->id)->exists();

    return ($ownedByTeam || $sharedWithTeam) ? $conn : null;
}

// DedefleetApiService – neu:
public function callForTeam(int $teamId, int $connectionId, string $method, string $endpoint, array $payload = []): ?array
{
    $conn = app(IntegrationConnectionResolver::class)->resolveByIdForTeam($connectionId, $teamId);
    if (!$conn) return null;

    // request() so anpassen/überladen, dass es eine bereits aufgelöste Connection
    // akzeptiert statt zwingend einen User (resolveConnection(?User) → nutzt $conn).
    return $this->requestWithConnection($conn, $method, $endpoint, $payload);
}
```

`requestWithConnection()` kann die vorhandene `request()`-Logik teilen – nur die
Connection-Beschaffung (aktuell `resolveConnection(User $user)`) wird durch die bereits
aufgelöste `$conn` ersetzt. Der Token kommt wie gehabt aus
`DedefleetIntegrationService::getApiToken($conn)`.

## Was Signage damit macht

Signage ruft genau einen Endpoint auf (ein Call pro Tagesplan):

```php
$raw = app(DedefleetApiService::class)->callForTeam(
    $teamId, $connectionId, 'POST', 'Tour/List',
    ['start' => '2026-07-23T00:00:00+02:00', 'end' => '2026-07-23T23:59:59+02:00'],
);
```

(Das Editor-Dropdown zum Auswählen der Connection nutzt bereits die vorhandene
`resolveAllForUser('dedefleet', $user)` – da ist nichts zu tun.)

## Bitte mit-verifizieren: `orders[]`-Felder in der Tour/List-Antwort

Signage normalisiert die rohe Antwort in `FleetBoardService::normalizeTours()`. Die
genauen Feldnamen sind im Repo nicht dokumentiert (keine DTOs/Fixtures), deshalb liest
Signage tolerant mehrere Kandidaten. **Bitte einmal eine echte `Tour/List`-Response
gegen die Swagger-Spec abgleichen** und uns die realen Feldnamen nennen (oder direkt in
`normalizeTours()` justieren). Signage erwartet/sucht je Tour bzw. Stopp:

| Anzeige            | gesuchte Schlüssel (erste Treffer gewinnen)                                   |
|--------------------|--------------------------------------------------------------------------------|
| Tour-Name          | `name`, `tourName`, `title`, `label` (sonst „Tour N")                           |
| Abfahrt            | `departure.time`, `departureTime`, `startTime`, `departure`                     |
| Fahrer             | `driverName`, `driver`, `driverDisplayName`                                     |
| Fahrzeug/KFZ       | `vehicleName`, `vehicleLabel`, `licenseNumber`, `vehicleApiID`, `vehicle`       |
| Tour-Status        | `status`, `tourStatus` (0=Planung,1=Freigegeben,2=Abgeschlossen)                |
| Stopps             | `orders[]`, `stops[]`, `orderList[]`                                            |
| — #VA / Auftragsnr | `vaNumber`, `orderNumber`, `referenceNumber`, `number`, `va`                    |
| — Kunde            | `customerName`, `customer`, `clientName`, `name`                                |
| — Lieferadresse    | `deliveryAddress`, `address`, `destinationAddress`, `fullAddress` (oder Teile: `street`/`zip`/`city`) |
| — Zeitfenster      | `windowFrom`/`windowTo`, `timeWindowFrom`/`…To`, `arrivalFrom`, `tourArrival`, `eta` |
| — Anlieferung/Abh. | `delivery`/`isDelivery`/`anlieferung`, `pickup`/`isPickup`/`abholung`           |
| — Bemerkung        | `remark`, `note`, `comment`, `notes`, `bemerkung`                               |
| — Fortschritt      | `orderStatus`, `orderState`, `status` (0=Offen…3=Erledigt,5=In Navigation)      |

Falls `Tour/List` die Detailfelder je Order nicht liefert, sagt uns das Bescheid –
dann ergänzen wir in Signage einen zweiten Call (`Tour/GetBulk` oder `Order/Get`).
