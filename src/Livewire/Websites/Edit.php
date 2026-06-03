<?php

namespace Platform\Signage\Livewire\Websites;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignageScreen;

/**
 * Bearbeitet/erstellt eine Website-Kachel (wird im Player als Vollbild-iframe
 * für die in der Wiedergabeliste eingestellte Dauer angezeigt).
 */
class Edit extends Component
{
    use WithCurrentTeam;

    public ?int $mediaId = null;
    public string $name = '';
    public string $url = '';

    public function mount(?SignageMedia $media = null): void
    {
        if ($media && $media->exists) {
            abort_unless($media->team_id === $this->teamId() && $media->isWebsite(), 403);
            $this->mediaId = $media->id;
            $this->name = (string) $media->name;
            $this->url = (string) $media->stream_url;
        }
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'url'  => 'required|url|max:1024',
        ]);

        if ($this->mediaId) {
            $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($this->mediaId);
            $urlChanged = $media->stream_url !== $this->url;
            $media->update(['name' => $this->name, 'stream_url' => $this->url]);
            SignageScreen::bumpForMedia($media->id);
        } else {
            $media = SignageMedia::create([
                'team_id'           => $this->teamId(),
                'user_id'           => auth()->id(),
                'name'              => $this->name,
                'kind'              => 'website',
                'stream_url'        => $this->url,
                'processing_status' => 'ready',
            ]);
            $urlChanged = true;
        }

        // Vorschau-Screenshot (neu) im Hintergrund holen, wenn die URL neu/geändert ist.
        if ($urlChanged) {
            \Platform\Signage\Jobs\CaptureWebsiteThumbnailJob::dispatch($media->id);
        }

        session()->flash('signage_message', 'Website gespeichert. Die Vorschau wird im Hintergrund erstellt.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    public function render()
    {
        return view('signage::livewire.websites.edit')->layout('platform::layouts.app');
    }
}
