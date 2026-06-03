<?php

namespace Platform\Signage\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;

/**
 * Holt einen Website-Screenshot und legt ihn als Anzeige-Variante (display_*)
 * der Website-Kachel ab. Primär über thum.io (liefert sofort), als Fallback
 * WordPress mShots (kein Wasserzeichen, aber oft erst nach mehreren Anläufen).
 *
 * Wird sowohl synchron (Button) als auch über die Queue (Job) genutzt.
 */
class WebsiteThumbnailService
{
    /**
     * @return array{ok:bool, reason:?string} ok=true wenn ein Screenshot gespeichert wurde
     */
    public function capture(SignageMedia $media): array
    {
        if ($media->kind !== 'website' || !$media->stream_url) {
            return ['ok' => false, 'reason' => 'Keine Website-URL hinterlegt.'];
        }

        // 1) thum.io – liefert den Screenshot i.d.R. sofort beim ersten Abruf.
        $shot = $this->fetch('https://image.thum.io/get/width/1200/crop/700/noanimate/'.$media->stream_url);
        $reason = $shot ? null : 'thum.io lieferte kein Bild';

        // 2) Fallback: WordPress mShots (kein Wasserzeichen).
        if (!$shot) {
            $shot = $this->fetch('https://s.wordpress.com/mshots/v1/'.rawurlencode($media->stream_url).'?w=1200&h=700');
            if ($shot) {
                $reason = null;
            } else {
                $reason = 'Kein Anbieter lieferte ein Bild (Server-Internetzugang/Firewall prüfen).';
            }
        }

        if (!$shot) {
            return ['ok' => false, 'reason' => $reason];
        }

        [$body, $ct] = $shot;
        $disk = config('filesystems.default', 'public');
        $token = Str::random(40);
        $path = $token.(str_contains($ct, 'png') ? '.png' : '.jpg');

        try {
            Storage::disk($disk)->put($path, $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'Speichern fehlgeschlagen: '.$e->getMessage()];
        }

        $media->update(['disk' => $disk, 'display_path' => $path, 'display_token' => $token]);
        SignageScreen::bumpForMedia($media->id);

        return ['ok' => true, 'reason' => null];
    }

    /**
     * Lädt eine Screenshot-URL und gibt [body, content-type] zurück, wenn es ein
     * echtes Bild ist (Platzhalter/Fehlerseiten sind klein) – sonst null.
     *
     * @return array{0:string,1:string}|null
     */
    private function fetch(string $url): ?array
    {
        try {
            $res = Http::timeout(25)->get($url);
        } catch (\Throwable $e) {
            return null;
        }

        $ct = strtolower((string) $res->header('Content-Type'));
        $body = $res->body();

        if ($res->ok() && str_contains($ct, 'image') && strlen($body) >= 6000) {
            return [$body, $ct];
        }

        return null;
    }
}
