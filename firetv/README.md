# Fire TV / Android TV Kiosk-Player

Dünner WebView-Wrapper um den browserbasierten Signage-Player (`/signage/play`).
Die Logik bleibt komplett serverseitig – die App sorgt nur für Vollbild,
Autostart, Kein-Bildschirmschoner und Auto-Reload.

## Wie die Player-URL ins Gerät kommt

Es gibt **eine** generische, signierte APK. Beim Download über den Button
**„Für Fire TV herunterladen"** (Modul → Bildschirme) schreibt die Plattform die
Player-URL **dieser Instanz** in den APK-Kommentar (`ApkUrlInjector`). Die App
liest diese URL beim ersten Start aus ihrer eigenen Datei – ohne Tippen, ohne
Standort-Auswahl. Pro Standort/Server also einfach von dort herunterladen.

> Wichtig: Die APK wird **nur v1-signiert** (`enableV2Signing=false`). Nur so
> bleibt die Signatur gültig, wenn die URL in den Kommentar geschrieben wird.
> Deshalb `targetSdk=29` (Sideload erlaubt v1-only). Für eine Appstore-
> Veröffentlichung (v2 Pflicht) müsste auf das APK-Signing-Block-Verfahren
> (Walle) umgestellt werden.

## Einmalig: Signatur-Keystore + GitHub-Secrets

```bash
keytool -genkeypair -v -keystore release.keystore \
  -alias signage -keyalg RSA -keysize 2048 -validity 10000

base64 -w0 release.keystore   # Ausgabe als Secret hinterlegen
```

Repository-Secrets anlegen:

| Secret | Inhalt |
|--------|--------|
| `ANDROID_KEYSTORE_BASE64` | base64 der release.keystore |
| `ANDROID_KEYSTORE_PASSWORD` | Keystore-Passwort |
| `ANDROID_KEY_ALIAS` | `signage` (bzw. dein Alias) |
| `ANDROID_KEY_PASSWORD` | Key-Passwort |

> Den Keystore sicher aufbewahren – nur damit lassen sich Updates ohne
> Neuinstallation ausspielen.

## APK bauen

GitHub → Actions → **Fire TV APK** → *Run workflow*. Das signierte
`app-release.apk` landet als Artefakt **signage-player-apk**.

Lokal (Android Studio): Ordner `firetv/` öffnen, `release`-Variante mit dem
Keystore bauen (oder Debug zum Testen – dann ohne URL-Injektion).

## APK ins Modul legen

Heruntergeladenes Artefakt nach `resources/firetv/signage-player.apk` im Modul
ablegen (oder Pfad per `SIGNAGE_FIRETV_APK` setzen). Sobald die Datei existiert,
erscheint der Download-Button auf der Bildschirme-Seite automatisch.

## Auf den Fire TV bringen (Sideload)

1. Fire TV: Einstellungen → My Fire TV → Developer Options → **Apps from
   Unknown Sources** aktivieren.
2. App **„Downloader"** installieren, die Download-URL aus dem Modul öffnen
   (`/signage/firetv/app.apk`) → APK laden und installieren. (Alternativ
   `adb install signage-player.apk`.)
3. App starten → Kopplungs-Code erscheint → im Admin unter **Bildschirme**
   koppeln. Fertig.

## Bedienung

- **Autostart** nach Reboot/Stromausfall.
- **MENU-Taste** der Fernbedienung → Player-URL ansehen/ändern.
- **Zurück-Taste** ist im Kiosk neutralisiert (kein versehentliches Verlassen).
