<?php

namespace Platform\Signage\Livewire\Screens;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignagePlaylist;
use Platform\Signage\Models\SignageSchedule;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Services\ScreenPairingService;

class Show extends Component
{
    use WithCurrentTeam;

    public SignageScreen $screen;

    // Einstellungen
    public ?int $defaultPlaylistId = null;
    public ?int $musicPlaylistId = null;
    public string $orientation = 'landscape';
    public string $name = '';

    // Zeitplan-Formular
    public array $schedDays = [];
    public string $schedStart = '08:00';
    public string $schedEnd = '18:00';
    public ?int $schedPlaylistId = null;
    public ?int $schedMusicId = null;
    public int $schedPriority = 0;

    public function mount(SignageScreen $screen): void
    {
        abort_unless($screen->team_id === $this->teamId(), 403);
        $this->screen = $screen;
        $this->defaultPlaylistId = $screen->default_playlist_id;
        $this->musicPlaylistId = $screen->music_playlist_id;
        $this->orientation = $screen->orientation;
        $this->name = (string) $screen->name;
    }

    public function saveSettings(ScreenPairingService $pairing): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'orientation' => 'required|in:landscape,landscape_180,portrait,portrait_180',
        ]);

        $this->screen->update([
            'name'                => $this->name,
            'default_playlist_id' => $this->defaultPlaylistId ?: null,
            'music_playlist_id'   => $this->musicPlaylistId ?: null,
            'orientation'         => $this->orientation,
        ]);

        $pairing->bumpVersion($this->screen->refresh());
        session()->flash('signage_message', 'Einstellungen gespeichert.');
    }

    public function addSchedule(ScreenPairingService $pairing): void
    {
        $this->validate([
            'schedDays'       => 'required|array|min:1',
            'schedStart'      => 'required',
            'schedEnd'        => 'required',
            'schedPlaylistId' => 'required|integer',
        ]);

        SignageSchedule::create([
            'team_id'           => $this->teamId(),
            'screen_id'         => $this->screen->id,
            'playlist_id'       => $this->schedPlaylistId,
            'music_playlist_id' => $this->schedMusicId ?: null,
            'days_of_week'      => array_values(array_map('intval', $this->schedDays)),
            'start_time'        => $this->schedStart,
            'end_time'          => $this->schedEnd,
            'priority'          => $this->schedPriority,
            'active'            => true,
        ]);

        $this->reset('schedDays', 'schedPlaylistId', 'schedMusicId', 'schedPriority');
        $pairing->bumpVersion($this->screen);
        session()->flash('signage_message', 'Zeitplan hinzugefügt.');
    }

    public function deleteSchedule(int $id, ScreenPairingService $pairing): void
    {
        $this->screen->schedules()->whereKey($id)->delete();
        $pairing->bumpVersion($this->screen);
    }

    public function reload(ScreenPairingService $pairing): void
    {
        $pairing->bumpVersion($this->screen);
        session()->flash('signage_message', 'Neuladen ausgelöst.');
    }

    public function render()
    {
        $playlists = SignagePlaylist::where('team_id', $this->teamId())->orderBy('name')->get();

        return view('signage::livewire.screens.show', [
            'visualPlaylists' => $playlists->where('kind', 'visual')->values(),
            'musicPlaylists'  => $playlists->where('kind', 'music')->values(),
            'schedules'       => $this->screen->schedules()->with(['playlist', 'musicPlaylist'])->orderByDesc('priority')->get(),
            'previewUrl'      => url('/signage/play').'?token='.$this->screen->device_token,
        ])->layout('platform::layouts.app');
    }
}
