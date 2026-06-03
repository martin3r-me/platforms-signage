<?php

namespace Platform\Signage\Console\Commands;

use Illuminate\Console\Command;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Services\DocumentConversionService;

/**
 * Verarbeitet ausstehende Signage-Dokumente (PDF/PPTX) synchron zu Seitenbildern.
 * Nützlich, wenn kein Queue-Worker läuft, oder als Cron-Fallback.
 */
class ProcessDocuments extends Command
{
    protected $signature = 'signage:process-documents {--failed : Auch fehlgeschlagene erneut verarbeiten}';

    protected $description = 'Verarbeitet ausstehende Signage-Dokumente (PDF/PPTX) zu Seitenbildern.';

    public function handle(DocumentConversionService $service): int
    {
        $statuses = $this->option('failed')
            ? ['pending', 'processing', 'failed']
            : ['pending', 'processing'];

        $items = SignageMedia::where('kind', 'document')
            ->whereIn('processing_status', $statuses)
            ->get();

        if ($items->isEmpty()) {
            $this->info('Keine zu verarbeitenden Dokumente.');

            return self::SUCCESS;
        }

        foreach ($items as $media) {
            $this->line("Verarbeite #{$media->id} – {$media->name} …");
            try {
                $service->convert($media);
                $this->info("  → {$media->page_count} Seite(n).");
            } catch (\Throwable $e) {
                $this->error('  → Fehler: '.$e->getMessage());
            }
        }

        $this->info('Fertig: '.$items->count().' Dokument(e) verarbeitet.');

        return self::SUCCESS;
    }
}
