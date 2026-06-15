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
    /** @var array<int> IDs der zugewiesenen Zeitpläne (kombinierbar) */
    public array $scheduleIds = [];
    // "playlist:ID" oder "media:ID" (einzelner Stream/Audio) oder '' (keine)
    public string $musicSource = '';
    public string $orientation = 'landscape';
    public ?string $timezone = null;
    public string $name = '';

    public function mount(SignageScreen $screen): void
    {
        abort_unless($screen->team_id === $this->teamId(), 403);
        $this->screen = $screen;
        $this->defaultPlaylistId = $screen->default_playlist_id;
        $this->scheduleIds = $screen->schedules()->pluck('signage_schedules.id')->map(fn ($id) => (int) $id)->all();
        $this->timezone = $screen->timezone;
        if ($screen->music_playlist_id) {
            $this->musicSource = 'playlist:'.$screen->music_playlist_id;
        } elseif ($screen->music_media_id) {
            $this->musicSource = 'media:'.$screen->music_media_id;
        }
        $this->orientation = $screen->orientation;
        $this->name = (string) $screen->name;
    }

    public function saveSettings(ScreenPairingService $pairing): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'orientation' => 'required|in:landscape,landscape_180,portrait,portrait_180',
        ]);

        // Nur eigene Zeitpläne zulassen, mit Regeln für die Überlappungsprüfung laden.
        $scheduleIds = array_values(array_unique(array_map('intval', $this->scheduleIds)));
        $schedules = SignageSchedule::where('team_id', $this->teamId())
            ->whereIn('id', $scheduleIds)
            ->with(['rules' => fn ($q) => $q->where('active', true)])
            ->get();

        if ($conflict = \Platform\Signage\Support\ScheduleOverlap::conflictMessage($schedules)) {
            $this->addError('scheduleIds', $conflict);

            return;
        }

        $musicPlaylistId = null;
        $musicMediaId = null;
        if (str_starts_with($this->musicSource, 'playlist:')) {
            $musicPlaylistId = (int) substr($this->musicSource, 9);
        } elseif (str_starts_with($this->musicSource, 'media:')) {
            $musicMediaId = (int) substr($this->musicSource, 6);
        }

        $this->screen->update([
            'name'                => $this->name,
            'default_playlist_id' => $this->defaultPlaylistId ?: null,
            'music_playlist_id'   => $musicPlaylistId,
            'music_media_id'      => $musicMediaId,
            'orientation'         => $this->orientation,
            'timezone'            => $this->timezone ?: null,
        ]);

        $this->screen->schedules()->sync($schedules->pluck('id')->all());

        $pairing->bumpVersion($this->screen->refresh());
        session()->flash('signage_message', 'Einstellungen gespeichert.');
    }

    public function reload(ScreenPairingService $pairing): void
    {
        $pairing->bumpVersion($this->screen);
        session()->flash('signage_message', 'Neuladen ausgelöst.');
    }

    public function render()
    {
        $playlists = SignagePlaylist::where('team_id', $this->teamId())->orderBy('name')->get();
        $musicPlaylists = $playlists->where('kind', 'music')->values();

        // Audio-Medien (inkl. Streams) als direkt wählbare Hintergrundmusik.
        $audioMedia = \Platform\Signage\Models\SignageMedia::where('team_id', $this->teamId())
            ->where('kind', 'audio')->orderBy('name')->get();

        $musicOptions = [];
        foreach ($musicPlaylists as $p) {
            $musicOptions[] = ['value' => 'playlist:'.$p->id, 'label' => 'Liste: '.$p->name];
        }
        foreach ($audioMedia as $m) {
            $musicOptions[] = ['value' => 'media:'.$m->id, 'label' => ($m->isStream() ? 'Stream: ' : 'Audio: ').$m->name];
        }

        $scheduleOptions = SignageSchedule::where('team_id', $this->teamId())
            ->orderBy('name')->get()
            ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->values()->all();

        $timezoneOptions = array_map(
            fn ($tz) => ['value' => $tz, 'label' => $tz],
            \DateTimeZone::listIdentifiers()
        );

        return view('signage::livewire.screens.show', [
            'visualPlaylists' => $playlists->where('kind', 'visual')->values(),
            'musicOptions'    => $musicOptions,
            'scheduleOptions' => $scheduleOptions,
            'timezoneOptions' => $timezoneOptions,
            'previewUrl'      => url('/signage/play').'?token='.$this->screen->device_token,
        ])->layout('platform::layouts.app');
    }
}
