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

    public function duplicatePlaylist(int $id): void
    {
        $source = SignagePlaylist::where('team_id', $this->teamId())
            ->with('items')
            ->findOrFail($id);

        $copy = SignagePlaylist::create([
            'team_id'     => $this->teamId(),
            'user_id'     => auth()->id(),
            'name'        => $source->name.' (Kopie)',
            'kind'        => $source->kind,
            'description' => $source->description,
            'fit'         => $source->fit,
        ]);

        foreach ($source->items as $item) {
            \Platform\Signage\Models\SignagePlaylistItem::create([
                'playlist_id'      => $copy->id,
                'media_id'         => $item->media_id,
                'position'         => $item->position,
                'duration_seconds' => $item->duration_seconds,
                'transition'       => $item->transition,
            ]);
        }

        session()->flash('signage_message', '„'.$source->name.'" dupliziert.');
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
