<?php

namespace Platform\Signage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Services\WebsiteThumbnailService;

/**
 * Holt beim Speichern einer Website im Hintergrund einen Screenshot
 * (siehe WebsiteThumbnailService) und legt ihn als Anzeige-Variante ab.
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

    public function handle(WebsiteThumbnailService $service): void
    {
        $media = SignageMedia::find($this->mediaId);
        if (!$media || $media->kind !== 'website' || !$media->stream_url) {
            return;
        }

        $result = $service->capture($media);

        if ($result['ok']) {
            return;
        }

        if ($this->attempts() < $this->tries) {
            $this->release($this->backoff);

            return;
        }

        Log::info('[Signage] Website-Screenshot nicht erstellt', [
            'media_id' => $this->mediaId,
            'reason'   => $result['reason'],
        ]);
    }
}
