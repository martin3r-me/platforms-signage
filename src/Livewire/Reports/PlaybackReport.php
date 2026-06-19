<?php

namespace Platform\Signage\Livewire\Reports;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageProofOfPlay;

/**
 * Einfache Proof-of-Play-Auswertung: Wiedergaben je Medium im gewählten Zeitraum.
 */
class PlaybackReport extends Component
{
    use WithCurrentTeam;

    public int $days = 7; // 1 | 7 | 30

    public function render()
    {
        $teamId = $this->teamId();
        $days = in_array($this->days, [1, 7, 30], true) ? $this->days : 7;
        $since = now()->subDays($days)->startOfDay();

        $rows = SignageProofOfPlay::where('team_id', $teamId)
            ->where('played_at', '>=', $since)
            ->selectRaw('media_id, COUNT(*) as plays, COUNT(DISTINCT screen_id) as screens')
            ->groupBy('media_id')
            ->orderByDesc('plays')
            ->limit(200)
            ->get();

        $names = SignageMedia::withTrashed()
            ->whereIn('id', $rows->pluck('media_id')->filter()->all())
            ->pluck('name', 'id');

        $total = (int) SignageProofOfPlay::where('team_id', $teamId)
            ->where('played_at', '>=', $since)->count();

        return view('signage::livewire.reports.playback', [
            'rows'  => $rows,
            'names' => $names,
            'total' => $total,
        ])->layout('platform::layouts.app');
    }
}
