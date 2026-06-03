<?php

namespace Platform\Signage\Console\Commands;

use Illuminate\Console\Command;
use Platform\Signage\Models\SignageScreen;

/**
 * Entfernt endgültig die nicht gekoppelten (pending) Bildschirme, die vom
 * öffentlichen Register-Endpoint angelegt, aber nie mit einem Team gekoppelt
 * wurden – verhindert das Anwachsen von Karteileichen.
 */
class PruneScreens extends Command
{
    protected $signature = 'signage:prune-screens {--days= : Alter in Tagen (Standard aus config signage.prune_pending_after_days)}';

    protected $description = 'Entfernt nicht gekoppelte (pending) Bildschirme, die älter als X Tage sind.';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('signage.prune_pending_after_days', 7));
        $days = max(1, $days);
        $cutoff = now()->subDays($days);

        $query = SignageScreen::query()
            ->where('status', 'pending')
            ->whereNull('paired_at')
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('Keine verwaisten Bildschirme zum Entfernen.');

            return self::SUCCESS;
        }

        // Endgültig entfernen (nicht nur soft-delete), damit der Platz wirklich frei wird.
        $query->forceDelete();

        $this->info("{$count} nicht gekoppelte Bildschirm(e) (älter als {$days} Tage) entfernt.");

        return self::SUCCESS;
    }
}
