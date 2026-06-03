<?php

namespace Platform\Signage\Livewire\Playlists;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignagePlaylist;
use Platform\Signage\Models\SignagePlaylistItem;
use Platform\Signage\Models\SignageScreen;

class Show extends Component
{
    use WithCurrentTeam;

    public SignagePlaylist $playlist;

    public ?int $addMediaId = null;

    // Einstellungen
    public string $name = '';
    public string $description = '';
    public string $fit = 'contain'; // contain = Originalformat, cover = Vollbild

    public function mount(SignagePlaylist $playlist): void
    {
        abort_unless($playlist->team_id === $this->teamId(), 403);
        $this->playlist = $playlist;
        $this->name = (string) $playlist->name;
        $this->description = (string) $playlist->description;
        $this->fit = $playlist->fit ?? 'contain';
    }

    public function rename(): void
    {
        $this->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'fit'         => 'required|in:contain,cover',
        ]);

        $this->playlist->update([
            'name'        => $this->name,
            'description' => $this->description ?: null,
            'fit'         => $this->fit,
        ]);

        $this->bumpAffectedScreens();
        session()->flash('signage_message', 'Wiedergabeliste gespeichert.');
    }

    /** Medien, die zum Typ der Liste passen. */
    public function availableMedia()
    {
        $kinds = $this->playlist->kind === 'music' ? ['audio'] : ['image', 'video', 'document', 'app'];

        return SignageMedia::where('team_id', $this->teamId())
            ->whereIn('kind', $kinds)
            ->orderBy('name')
            ->get();
    }

    public function addItem(): void
    {
        $this->validate(['addMediaId' => 'required|integer']);

        $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($this->addMediaId);

        $position = (int) $this->playlist->items()->max('position') + 1;

        SignagePlaylistItem::create([
            'playlist_id'      => $this->playlist->id,
            'media_id'         => $media->id,
            'position'         => $position,
            'duration_seconds' => null,
            'transition'       => 'fade',
        ]);

        $this->addMediaId = null;
        $this->bumpAffectedScreens();
    }

    public function updateDuration(int $itemId, $seconds): void
    {
        $item = $this->playlist->items()->findOrFail($itemId);
        $seconds = (int) $seconds;
        $item->update(['duration_seconds' => $seconds > 0 ? $seconds : null]);
        $this->bumpAffectedScreens();
    }

    public function removeItem(int $itemId): void
    {
        $this->playlist->items()->whereKey($itemId)->delete();
        $this->bumpAffectedScreens();
    }

    public function move(int $itemId, string $direction): void
    {
        $items = $this->playlist->items()->orderBy('position')->get()->values();
        $index = $items->search(fn ($i) => $i->id === $itemId);
        if ($index === false) {
            return;
        }

        $swapWith = $direction === 'up' ? $index - 1 : $index + 1;
        if ($swapWith < 0 || $swapWith >= $items->count()) {
            return;
        }

        DB::transaction(function () use ($items, $index, $swapWith) {
            $a = $items[$index];
            $b = $items[$swapWith];
            [$a->position, $b->position] = [$b->position, $a->position];
            $a->save();
            $b->save();
        });

        $this->bumpAffectedScreens();
    }

    /**
     * Erhöht die content_version aller Bildschirme, die diese Liste nutzen
     * (als Standard-/Musik-Liste oder über einen Zeitplan).
     */
    protected function bumpAffectedScreens(): void
    {
        SignageScreen::bumpForPlaylists([$this->playlist->id]);
    }

    public function render()
    {
        $this->playlist->load('items.media');

        return view('signage::livewire.playlists.show', [
            'items'          => $this->playlist->items,
            'available'      => $this->availableMedia(),
            'defaultDuration' => (int) config('signage.default_image_duration', 10),
        ])->layout('platform::layouts.app');
    }
}
