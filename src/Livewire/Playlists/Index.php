<?php

namespace Platform\Signage\Livewire\Playlists;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignagePlaylist;

class Index extends Component
{
    use WithCurrentTeam;

    public bool $showCreateModal = false;
    public string $name = '';
    public string $kind = 'visual';

    protected array $rules = [
        'name' => 'required|string|max:255',
        'kind' => 'required|in:visual,music',
    ];

    public function openCreateModal(): void
    {
        $this->reset('name', 'kind');
        $this->resetValidation();
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
    }

    public function create(): void
    {
        $this->validate();

        $playlist = SignagePlaylist::create([
            'team_id' => $this->teamId(),
            'user_id' => auth()->id(),
            'name'    => $this->name,
            'kind'    => $this->kind,
        ]);

        $this->reset('name');

        $this->redirectRoute('signage.playlists.show', $playlist, navigate: true);
    }

    public function deletePlaylist(int $id): void
    {
        SignagePlaylist::where('team_id', $this->teamId())->findOrFail($id)->delete();
        session()->flash('signage_message', 'Wiedergabeliste gelöscht.');
    }

    public function render()
    {
        $playlists = SignagePlaylist::where('team_id', $this->teamId())
            ->withCount('items')
            ->orderBy('name')
            ->get()
            ->groupBy('kind');

        return view('signage::livewire.playlists.index', [
            'visual' => $playlists->get('visual', collect()),
            'music'  => $playlists->get('music', collect()),
        ])->layout('platform::layouts.app');
    }
}
