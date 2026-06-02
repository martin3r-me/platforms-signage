# Platform Digital Signage

OptiSigns-ähnliches Digital-Signage-Modul: Medien (Bilder, Videos, Audio, **PDF, PowerPoint**)
hochladen, in **Wiedergabelisten** organisieren und auf **TVs/Monitoren** anzeigen — mit
**Hintergrundmusik**, **Zeitplanung** und **Live-Vorschau**.

- **Key:** `signage` · **Namespace:** `Platform\Signage` · **Prefix:** `/signage`
- **Player:** browserbasiert. Jeder Bildschirm öffnet `/signage/play` und koppelt sich per
  6-stelligem Code in der Admin-Oberfläche („Bildschirme“).
- **PDF/PowerPoint:** werden serverseitig in Seitenbilder umgewandelt; jede Folie/Seite wird
  für die eingestellte Dauer angezeigt.

## Architektur

| Bereich | Datei(en) |
|---|---|
| Registrierung | `src/SignageServiceProvider.php`, `config/signage.php` |
| Datenmodell | `database/migrations/*`, `src/Models/*` |
| Geräte-Kopplung | `src/Services/ScreenPairingService.php` |
| Manifest-Auflösung | `src/Services/PlayerManifestService.php` |
| Dokument-Konvertierung | `src/Services/DocumentConversionService.php`, `src/Jobs/ConvertDocumentJob.php` |
| Guest-API | `routes/api.php`, `src/Http/Controllers/Api/*` |
| Player | `routes/guest.php`, `src/Http/Controllers/PlayerController.php`, `resources/views/player.blade.php` |
| Admin | `src/Livewire/*`, `resources/views/livewire/*` |

## Installation in einer App

1. Modul als Composer-Paket einbinden. Lokale Entwicklung über ein Path-Repository in der
   `composer.json` der App:
   ```json
   "repositories": [
     { "type": "path", "url": "../platform/modules/platforms-signage" }
   ],
   "require": {
     "martin3r/platforms-signage": "*"
   }
   ```
   (In Produktion analog zu den übrigen `martin3r/*`-Modulen über die genutzte Registry/VCS.)
2. `composer update martin3r/platforms-signage`
3. `php artisan migrate`
4. Caches leeren: `php artisan config:clear && php artisan route:clear`
5. Der Service-Provider wird via `extra.laravel.providers` automatisch erkannt.

## Voraussetzungen (Server)

- **Ghostscript** (`gs`) für PDF → Seitenbilder (bereits im Stack, vgl. Events).
- **LibreOffice headless** (`soffice`) für PowerPoint → PDF.
- Laufender **Queue-Worker** (`php artisan queue:work`) — Dokument-Konvertierung läuft asynchron.
- Datei-Storage über `ContextFileService` (lokal `public` oder S3, automatisch erkannt).

## Bedienung

1. **Medien** hochladen (`/signage/media`).
2. **Wiedergabeliste** anlegen (visuell oder Musik) und Elemente mit Dauer ordnen.
3. **Bildschirm koppeln:** `/signage/play` am TV öffnen → Code in „Bildschirme“ eingeben.
4. Standard-Wiedergabeliste + Hintergrundmusik zuweisen, optional **Zeitpläne** definieren.
5. Änderungen erscheinen am Bildschirm automatisch (Polling) oder per **Neuladen**-Button.
