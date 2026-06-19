<?php

namespace Platform\Signage\Livewire\Apps;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;

/**
 * Übersicht/Verwaltung der App-Medien (Uhr, Wetter, Menü, Veranstaltungen).
 * Apps bleiben zugleich in der Medienbibliothek (für Playlists) sichtbar.
 */
class Index extends Component
{
    use WithCurrentTeam;

    public function deleteApp(int $id): void
    {
        SignageMedia::where('team_id', $this->teamId())->where('kind', 'app')->findOrFail($id)->delete();
        session()->flash('signage_message', 'App gelöscht.');
    }

    public function render()
    {
        $apps = SignageMedia::where('team_id', $this->teamId())
            ->where('kind', 'app')
            ->orderBy('name')
            ->get();

        return view('signage::livewire.apps.index', [
            'apps' => $apps,
        ])->layout('platform::layouts.app');
    }
}
