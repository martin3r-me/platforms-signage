<?php

namespace Platform\Signage\Console\Commands;

use Illuminate\Console\Command;
use Platform\Signage\Models\SignageProofOfPlay;

/**
 * Entfernt alte Proof-of-Play-Einträge, damit die Tabelle nicht unbegrenzt wächst.
 */
class PrunePlaybackLogs extends Command
{
    protected $signature = 'signage:prune-playback {--days=90}';

    protected $description = 'Löscht Proof-of-Play-Einträge, die älter als --days (Standard 90) sind.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $deleted = SignageProofOfPlay::where('played_at', '<', $cutoff)->delete();

        $this->info("Proof-of-Play: {$deleted} Einträge älter als {$days} Tage gelöscht.");

        return self::SUCCESS;
    }
}
