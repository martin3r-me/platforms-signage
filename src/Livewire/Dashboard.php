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

        $screens = SignageScreen::where('team_id', $teamId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $stats = [
            'screens'  => $screens->count(),
            'online'   => $screens->filter->isOnline()->count(),
            'media'    => SignageMedia::where('team_id', $teamId)->count(),
            'playlists' => SignagePlaylist::where('team_id', $teamId)->count(),
        ];

        return view('signage::livewire.dashboard', [
            'screens' => $screens,
            'stats'   => $stats,
        ])->layout('platform::layouts.app');
    }
}
