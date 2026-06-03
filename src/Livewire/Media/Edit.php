<?php

namespace Platform\Signage\Livewire\Media;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageMediaFolder;

/**
 * Bearbeitet ein Datei-Medium (Bild/Video/Audio/Dokument): Name + Ordner.
 * Apps/Streams/Websites haben eigene Editoren.
 */
class Edit extends Component
{
    use WithCurrentTeam;

    public SignageMedia $media;
    public string $name = '';
    public ?int $folderId = null;

    public function mount(SignageMedia $media): void
    {
        abort_unless($media->team_id === $this->teamId(), 403);
        $this->media = $media;
        $this->name = (string) $media->name;
        $this->folderId = $media->folder_id;
    }

    public function save()
    {
        $this->validate(['name' => 'required|string|max:255']);

        $folderId = $this->folderId ?: null;
        if ($folderId) {
            SignageMediaFolder::where('team_id', $this->teamId())->findOrFail($folderId);
        }

        $this->media->update([
            'name'      => $this->name,
            'folder_id' => $folderId,
        ]);

        session()->flash('signage_message', 'Medium gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    public function render()
    {
        $folderOptions = SignageMediaFolder::where('team_id', $this->teamId())
            ->orderBy('name')->get()
            ->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->values()->all();

        return view('signage::livewire.media.edit', [
            'folderOptions' => $folderOptions,
            'preview'       => $this->media->previewUrl(),
        ])->layout('platform::layouts.app');
    }
}
