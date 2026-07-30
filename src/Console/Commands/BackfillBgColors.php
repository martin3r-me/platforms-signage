<?php

namespace Platform\Signage\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Services\SignageImageService;

/**
 * Erkennt die Rand-/Hintergrundfarbe (bg_color) bestehender Bilder & Dokumente
 * nachträglich – für Medien, die vor Einführung der Auto-Erkennung hochgeladen
 * wurden. Setzt bg_color und bumpt die betroffenen Bildschirme, damit der Player
 * die neue Letterbox-Farbe übernimmt. Einmalig auszuführen.
 */
class BackfillBgColors extends Command
{
    protected $signature = 'signage:backfill-bg-colors {--force : Auch Medien mit bereits gesetzter bg_color neu erkennen}';

    protected $description = 'Erkennt die Rand-/Hintergrundfarbe (bg_color) bestehender Bilder & Dokumente nachträglich.';

    public function handle(SignageImageService $images): int
    {
        $force = (bool) $this->option('force');

        $base = fn () => SignageMedia::query()
            ->whereIn('kind', ['image', 'document'])
            ->when(!$force, fn ($q) => $q->whereNull('bg_color'));

        $total = $base()->count();
        if ($total === 0) {
            $this->info('Keine Medien zu verarbeiten.');

            return self::SUCCESS;
        }

        $this->info("Verarbeite {$total} Medium(en) …");
        $updated = 0;
        $skipped = 0;

        // chunkById ist sicher, obwohl wir bg_color setzen: wir blättern per id
        // aufwärts, bereits verarbeitete (niedrigere ids) fallen nur hinter uns raus.
        $base()->chunkById(100, function ($chunk) use ($images, &$updated, &$skipped) {
            foreach ($chunk as $media) {
                $content = $this->contentFor($media);
                if ($content === null) {
                    $skipped++;
                    continue;
                }

                $bg = $images->detectBackgroundColor($content);
                if ($bg === null) {
                    $skipped++;
                    continue;
                }

                $media->update(['bg_color' => $bg]);
                SignageScreen::bumpForMedia($media->id);
                $updated++;
                $this->line("  #{$media->id} {$media->name} → {$bg}");
            }
        });

        $this->info("Fertig: {$updated} gesetzt, {$skipped} übersprungen (kein Bild verfügbar oder Ecken uneindeutig).");

        return self::SUCCESS;
    }

    /** Bildinhalt zum Abtasten: Bild = eigene Datei, Dokument = erste Seite. */
    private function contentFor(SignageMedia $media): ?string
    {
        try {
            if ($media->kind === 'image' && $media->path) {
                return Storage::disk($media->disk)->get($media->path);
            }

            if ($media->kind === 'document') {
                $page = $media->pages()->orderBy('page_number')->first();
                if ($page && $page->path) {
                    return Storage::disk($page->disk ?: $media->disk)->get($page->path);
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}
