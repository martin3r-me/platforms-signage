# DedeFleet-Tourenplan-App – Integrations-Notizen

**Modul:** Signage (Tourenplan-App, `app_type = dedefleet`)
**Status:** ✅ Kein Eingriff in `platforms-integrations` nötig.

## Wie der Abruf läuft (finaler Stand)

Signage nutzt ausschließlich die **bestehenden, user-basierten** Methoden des
`DedefleetApiService` (`listTours(User $user, …)`, `listCustomers(User $user, …)`),
gekapselt in `Platform\Signage\Support\FleetBoardService`. **Es gibt keinen headless
`callForTeam`-Einstieg mehr** – der frühere Handoff ist damit hinfällig.

Der User wird je Kontext bestimmt:

- **Editor/Vorschau:** der eingeloggte User (`auth()->user()`).
- **Player (headless, kein Login):** der **Ersteller der App** (`SignageMedia->user_id`).
  Der Player-Endpoint (`ScreenController::fleet`) löst die App über die im Manifest
  hinterlegte `media`-Kennung auf – **eingegrenzt aufs Team des Bildschirms** –, liest
  `connection_id` + Ersteller-User aus dem Record (nie vom Gerät) und ruft:

  ```php
  app(DedefleetApiService::class)
      ->forConnection($connectionId)
      ->listTours($creatorUser, ['start' => '2026-07-24T00:00:00', 'end' => '2026-07-24T23:59:59']);
  ```

  Die Zugriffsprüfung passiert in der Integration selbst: `listTours` →
  `resolveConnection` → `resolveById($connectionId, $user)` → `access->canUse(...)`.
  Da der Connector **user-gebunden an den Ersteller** freigegeben ist, greift das sauber.

## ⚠ Bekannter Integrations-Bug + Signage-Fallback (2026-07-29)

**Symptom:** Ist die DedeFleet-Connection dem Ersteller nur **team-weit freigegeben**
(Owner = anderer User, Share auf `team_id = <Team>`), erscheint sie im Signage-Dropdown,
aber der Abruf wirft „keine Connection" → Board „Tourenplan aktuell nicht abrufbar".

**Ursache (in platforms-integrations):** `IntegrationConnectionResolver::resolveById()` ruft
`IntegrationAccessService::canUse($user, $connection)` **ohne Team-Kontext** auf. `hasShareAccess()`
matcht ohne `$teamId` aber nur Shares mit `team_id IS NULL` (alle Teams) oder direkte User-Shares —
ein **team-scoped Share wird abgelehnt**. Inkonsistent zu `resolveForUser()` und `resolveAllForUser()`
(Dropdown), die Team-Shares sehr wohl berücksichtigen. **Sauberer Fix (Kollege):** in `resolveById()`
den Team-Kontext an `canUse()` durchreichen (bzw. `canUse` die Team-Mitgliedschaften des Users
berücksichtigen lassen — analog `resolveForUser`).

**Signage-Fallback (bereits umgesetzt, kein Integrations-Eingriff):**
`FleetBoardService::fetchTours()` (und die Customer-Anreicherung) versuchen zuerst
`forConnection($id)->listTours($user)`; scheitert das, fällt der Abruf auf `listTours($user)`
**ohne** `forConnection` zurück → `getConnectionForUser()`→`resolveForUser()` ehrt den Team-Share
und liefert dieselbe Connection. Nutzt im Fallback ggf. die Default-Connection des Users statt exakt
der gewählten (bei nur einer DedeFleet-Connection identisch). Kann entfallen, sobald der Integrations-Fix da ist.

## Kunde + Adresse: Customer-Join (Signage-seitig)

`Tour/List` liefert je Order **keinen** Kundennamen/Adresse (`order.location.*` = null,
nur `location.id`). `FleetBoardService::enrichWithCustomers()` holt daher einmal
`listCustomers($user)` (10 min gecacht pro Connection) und joint lokal
**`order.location.id` == `customer.customerNumber`** → füllt `location.name/street/postal/city`.
`Order/Get` pro Stopp wäre die Alternative, ist aber teurer (N Calls) und nicht nötig.

## Verifizierte `Tour/List`-Felder (2026-07-24)

Anhand einer echten Response (Team BHG.DIGITAL, Connection „DedeFleet") verifiziert und
`normalizeTours()`/`normalizeStop()` entsprechend justiert. Die **realen** Feldnamen:

**Tour-Ebene:**

| Anzeige       | echtes Feld                     | Hinweis                                              |
|---------------|---------------------------------|------------------------------------------------------|
| Tour-Name     | `tour`                          | z.B. „Kalt-1", „Import" (NICHT `name`/`tourName`)    |
| Abfahrt       | `departure.time`                | „06:44:39" → Board zeigt „06:44" ✓                    |
| Fahrer        | `driverName`                    | im Testdatensatz `null` (keine Fahrer zugewiesen)    |
| Fahrzeug/KFZ  | `vehicleApiID`                  | ⚠ nur numerische ID („639192…"), KEIN Klartext/Kennzeichen in Tour/List |
| Tour-Status   | `status`                        | 0=Planung,1=Freigegeben,2=Abgeschlossen ✓            |
| Stopps        | `orders[]`                      | ✓                                                    |

**Stopp/Order-Ebene (eingebettet in `orders[]`):**

| Anzeige            | echtes Feld            | Hinweis                                                    |
|--------------------|------------------------|------------------------------------------------------------|
| #VA / Auftragsnr   | `order`                | „Auf1004" (Lieferschein-Nr separat in `delivery` = „Lief1004") |
| Anlieferung/Abh.   | `type` (int)           | 0=Anlieferung, 1=Abholung (NICHT bool-Felder!)             |
| Zeitfenster/Ankunft| `tourArrival`          | „07:00" ✓                                                  |
| Fortschritt        | `orderStatus`          | 0=Offen,1=Gelesen,2=Aktiv,3=Erledigt,4=Gelöscht,5=In Navigation ✓ |
| Kunde              | `location.name`        | ⚠ in Tour/List `null` — siehe „Kernbefund" unten           |
| Lieferadresse      | `location.{street,postal,city}` | ⚠ in Tour/List `null` — siehe „Kernbefund" unten  |
| Bemerkung          | `notes` / `driverMessage` | `notes` in Tour/List meist `null`; die brauchbare Fahrer-Notiz („ebenerdig, Temperaturmessung…") liefert nur `Order/Get` als `driverMessage` |

(Kunde/Adresse werden per Customer-Join angereichert – siehe Abschnitt „Kunde + Adresse" oben.
`location.id="42"` → Kunde „Herrenshof", Schaffenbergstraße 27b, Korschenbroich, verifiziert.)

### Weitere verifizierte API-Constraints

- **`Tour/List` erlaubt max. 7 Tage** `start`–`end` (sonst HTTP 500 „Start/End has a Time
  Range over 7 Days!"). Signage fragt nur einen Tag ab → unkritisch.
- **Datumsformat (korrigiert 2026-07-29):** `start`/`end` dürfen NUR ein reines Datum sein
  (ISO `yyyy-MM-dd`, wird zu `DD.MM.YYYY` konvertiert). **Mit Uhrzeit** (`yyyy-MM-ddTHH:mm:ss`
  → `DD.MM.YYYY HH:mm:ss`) **ODER mit Zeitzonen-Offset** antwortet Tour/List mit HTTP 500
  „Start is not a valid date!". An echter API am 2026-07-29 verifiziert: `29.07.2026` liefert
  Touren, `29.07.2026 00:00:00` bzw. `2026-07-29T00:00:00` → 500. `FleetBoardService::board()`
  sendet daher `Y-m-d` (kein `startOfDay()/endOfDay()`).
  ⚠ Die frühere Notiz „offset-freies `Y-m-d\TH:i:s` funktioniert" war falsch und war die Ursache
  für das „Keine Touren für heute"-Symptom im Board.
