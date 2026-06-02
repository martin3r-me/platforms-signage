<?php

namespace Platform\Signage\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Services\DocumentConversionService;

/**
 * Konvertiert ein hochgeladenes Dokument (PDF/PPTX) asynchron in Seitenbilder.
 * Pattern nach platforms-core RenderDocumentJob / GenerateImageVariantsJob.
 */
class ConvertDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries = 2;

    public function __construct(private int $mediaId)
    {
    }

    public function handle(DocumentConversionService $service): void
    {
        $media = SignageMedia::find($this->mediaId);

        if (!$media) {
            Log::warning('[Signage] ConvertDocumentJob: Medium nicht gefunden', ['media_id' => $this->mediaId]);

            return;
        }

        $service->convert($media);

        Log::info('[Signage] Dokument konvertiert', [
            'media_id'   => $media->id,
            'page_count' => $media->page_count,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[Signage] ConvertDocumentJob endgültig fehlgeschlagen', [
            'media_id' => $this->mediaId,
            'error'    => $e->getMessage(),
        ]);

        if ($media = SignageMedia::find($this->mediaId)) {
            $media->update(['processing_status' => 'failed']);
        }
    }
}
