# Fire-TV-APK ablegen

Die per GitHub-Action gebaute, signierte **`signage-player.apk`** hier ablegen:

    resources/firetv/signage-player.apk

Sobald die Datei vorhanden ist, zeigt die Bildschirme-Seite den Button
**„Für Fire TV herunterladen"**. Beim Download wird die Player-URL der jeweiligen
Instanz in die APK injiziert (siehe `src/Support/ApkUrlInjector.php`).

Alternativ kann der Pfad per ENV `SIGNAGE_FIRETV_APK` auf einen anderen Ort
(z.B. Storage) gesetzt werden.

Quellprojekt der App: `firetv/` (siehe `firetv/README.md`).
