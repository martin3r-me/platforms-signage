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
 * Holt beim Speichern einer Website einen Screenshot (kostenlos über WordPress
 * mShots) und legt ihn als Anzeige-Variante (display_*) der Website-Kachel ab.
 * mShots liefert beim ersten Abruf oft noch einen Platzhalter -> mehrfach versuchen.
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

        $url = 'https://s.wordpress.com/mshots/v1/'.rawurlencode($media->stream_url).'?w=1200&h=675';

        try {
            $res = Http::timeout(30)->get($url);
        } catch (\Throwable $e) {
            $this->retryOrLog($e->getMessage());

            return;
        }

        $ct = strtolower((string) $res->header('Content-Type'));
        $body = $res->body();

        // mShots-Platzhalter (noch nicht fertig) ist klein -> erneut versuchen.
        if (!$res->ok() || !str_contains($ct, 'image') || strlen($body) < 6000) {
            $this->retryOrLog('Screenshot noch nicht bereit ('.strlen($body).' bytes)');

            return;
        }

        $disk = config('filesystems.default', 'public');
        $token = Str::random(40);
        $path = $token.(str_contains($ct, 'png') ? '.png' : '.jpg');
        Storage::disk($disk)->put($path, $body);

        $media->update(['disk' => $disk, 'display_path' => $path, 'display_token' => $token]);
        SignageScreen::bumpForMedia($media->id);
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
