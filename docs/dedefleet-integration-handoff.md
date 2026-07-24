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
- **Datumsformat:** ISO `yyyy-MM-dd` bzw. `yyyy-MM-ddTHH:mm:ss` **ohne Zeitzonen-Offset**.
  Mit `+02:00` → 500 „Start is not a valid date!". `FleetBoardService::board()` sendet daher
  offset-freies `Y-m-d\TH:i:s`; der ApiService konvertiert selbst ins DedeFleet-Format `DD.MM.YYYY`.
