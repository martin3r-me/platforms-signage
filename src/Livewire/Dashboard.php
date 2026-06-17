<?php

namespace Platform\Signage\Livewire;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignagePlaylist;
use Platform\Signage\Models\SignageScreen;

class Dashboard extends Component
{
    use WithCurrentTeam;

    public function render()
    {
        $teamId = $this->teamId();
        $screenTeamIds = $this->screenTeamIds();

        // Im Parent-Team sind auch die Bildschirme der Kind-Teams sichtbar (read-only Überblick).
        $screens = SignageScreen::whereIn('team_id', $screenTeamIds)
            ->where('status', 'active')
            ->with('team')
            ->orderBy('name')
            ->get();

        $stats = [
            // Bildschirme & Online beziehen die Kind-Teams ein (Überblick);
            // Medien/Playlists bleiben aufs eigene Team bezogen.
            'screens'  => $screens->count(),
            'online'   => $screens->filter->isOnline()->count(),
            'media'    => SignageMedia::where('team_id', $teamId)->count(),
            'playlists' => SignagePlaylist::where('team_id', $teamId)->count(),
        ];

        return view('signage::livewire.dashboard', [
            'screens'       => $screens,
            'stats'         => $stats,
            'currentTeamId' => $teamId,
        ])->layout('platform::layouts.app');
    }
}
