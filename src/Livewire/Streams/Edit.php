<?php

namespace Platform\Signage\Livewire\Streams;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\ResolvesStream;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageMedia;
use Platform\Signage\Models\SignagePlaylistItem;
use Platform\Signage\Models\SignageSchedule;
use Platform\Signage\Models\SignageScreen;

/**
 * Bearbeitet einen Musik-Stream (Name, URL, Typ). URL wird wie beim Anlegen
 * neu aufgelöst (TuneIn -> direkter Stream).
 */
class Edit extends Component
{
    use WithCurrentTeam, ResolvesStream;

    public int $mediaId;
    public string $name = '';
    public string $url = '';
    public string $type = 'stream'; // stream | embed

    public function mount(SignageMedia $media): void
    {
        abort_unless($media->team_id === $this->teamId() && $media->isStream(), 403);

        $this->mediaId = $media->id;
        $this->name = (string) $media->name;
        $this->url = (string) $media->stream_url;
        $this->type = $media->is_embed ? 'embed' : 'stream';
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'url'  => 'required|url|max:1024',
            'type' => 'required|in:stream,embed',
        ]);

        [$url, $isEmbed] = $this->resolveStream($this->url, $this->type);

        $media = SignageMedia::where('team_id', $this->teamId())->findOrFail($this->mediaId);
        $media->update([
            'name'       => $this->name,
            'stream_url' => $url,
            'is_embed'   => $isEmbed,
        ]);

        $this->bumpScreensUsing($media->id);
        session()->flash('signage_message', 'Stream gespeichert.');

        return $this->redirectRoute('signage.media.index', navigate: true);
    }

    /**
     * Bildschirme neu laden lassen, die diesen Stream als Musik nutzen
     * (direkt als music_media_id oder über eine Musik-Playlist/Zeitplan).
     */
    protected function bumpScreensUsing(int $mediaId): void
    {
        $playlistIds = SignagePlaylistItem::where('media_id', $mediaId)->pluck('playlist_id')->unique();

        $screenIds = SignageScreen::where('music_media_id', $mediaId)
            ->orWhereIn('music_playlist_id', $playlistIds)
            ->pluck('id')
            ->merge(
                SignageSchedule::whereIn('music_playlist_id', $playlistIds)->pluck('screen_id')
            )
            ->unique();

        if ($screenIds->isNotEmpty()) {
            SignageScreen::whereIn('id', $screenIds)->increment('content_version');
        }
    }

    public function render()
    {
        return view('signage::livewire.streams.edit')->layout('platform::layouts.app');
    }
}
