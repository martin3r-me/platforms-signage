<?php

namespace Platform\Signage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;

/**
 * Holt beim Speichern einer Website einen Screenshot und legt ihn als
 * Anzeige-Variante (display_*) der Website-Kachel ab. Primär über thum.io
 * (liefert sofort), als Fallback WordPress mShots (kein Wasserzeichen, aber oft
 * erst nach mehreren Anläufen fertig -> mehrfach versuchen).
 */
class CaptureWebsiteThumbnailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 6;
    public $backoff = 15;
    public $timeout = 60;

    public function __construct(private int $mediaId)
    {
    }

    public function handle(): void
    {
        $media = SignageMedia::find($this->mediaId);
        if (!$media || $media->kind !== 'website' || !$media->stream_url) {
            return;
        }

        // 1) thum.io – liefert den Screenshot i.d.R. sofort beim ersten Abruf.
        $shot = $this->fetch('https://image.thum.io/get/width/1200/crop/700/noanimate/'.$media->stream_url);

        // 2) Fallback: WordPress mShots (braucht ggf. mehrere Anläufe, kein Wasserzeichen).
        if (!$shot) {
            $shot = $this->fetch('https://s.wordpress.com/mshots/v1/'.rawurlencode($media->stream_url).'?w=1200&h=700');
        }

        if (!$shot) {
            $this->retryOrLog('Screenshot noch nicht verfügbar');

            return;
        }

        [$body, $ct] = $shot;
        $disk = config('filesystems.default', 'public');
        $token = Str::random(40);
        $path = $token.(str_contains($ct, 'png') ? '.png' : '.jpg');
        Storage::disk($disk)->put($path, $body);

        $media->update(['disk' => $disk, 'display_path' => $path, 'display_token' => $token]);
        SignageScreen::bumpForMedia($media->id);
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
            $res = Http::timeout(30)->get($url);
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

    private function retryOrLog(string $reason): void
    {
        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff);

            return;
        }

        Log::info('[Signage] Website-Screenshot nicht erstellt', ['media_id' => $this->mediaId, 'reason' => $reason]);
    }
}
