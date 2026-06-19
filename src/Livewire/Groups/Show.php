<?php

namespace Platform\Signage\Livewire\Groups;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignagePlaylist;
use Platform\Signage\Models\SignageSchedule;
use Platform\Signage\Models\SignageScreen;
use Platform\Signage\Models\SignageScreenGroup;
use Platform\Signage\Services\ScreenPairingService;

class Show extends Component
{
    use WithCurrentTeam;

    public SignageScreenGroup $group;

    public string $name = '';
    /** @var array<int> */
    public array $memberIds = [];

    // "Auf Gruppe anwenden"
    public ?int $applyDefaultPlaylistId = null;
    /** @var array<int> */
    public array $applyScheduleIds = [];

    public function mount(SignageScreenGroup $group): void
    {
        abort_unless($group->team_id === $this->teamId(), 403);
        $this->group = $group;
        $this->name = (string) $group->name;
        $this->memberIds = $group->screens()->pluck('signage_screens.id')->map(fn ($id) => (int) $id)->all();
    }

    public function saveGroup(): void
    {
        $this->validate(['name' => 'required|string|max:255']);
        $this->group->update(['name' => $this->name]);

        // Mitglieder nur aus dem eigenen Team zulassen.
        $ids = SignageScreen::where('team_id', $this->teamId())
            ->whereIn('id', array_map('intval', $this->memberIds))
            ->pluck('id')->all();
        $this->group->screens()->sync($ids);

        session()->flash('signage_message', 'Gruppe gespeichert.');
    }

    /** Schreibt die gewählten Einstellungen auf ALLE Mitglieds-Bildschirme. */
    public function applyToGroup(ScreenPairingService $pairing): void
    {
        $screens = $this->group->screens()->where('signage_screens.team_id', $this->teamId())->get();
        if ($screens->isEmpty()) {
            session()->flash('signage_message', 'Gruppe hat keine Bildschirme.');

            return;
        }

        $playlistId = null;
        if ($this->applyDefaultPlaylistId) {
            $playlistId = SignagePlaylist::where('team_id', $this->teamId())
                ->where('kind', 'visual')->whereKey($this->applyDefaultPlaylistId)->value('id');
        }

        $scheduleIds = SignageSchedule::where('team_id', $this->teamId())
            ->whereIn('id', array_map('intval', $this->applyScheduleIds))->pluck('id')->all();

        foreach ($screens as $screen) {
            if ($this->applyDefaultPlaylistId) {
                $screen->update(['default_playlist_id' => $playlistId]);
            }
            $screen->schedules()->sync($scheduleIds);
            $pairing->bumpVersion($screen);
        }

        session()->flash('signage_message', $screens->count().' Bildschirm(e) aktualisiert.');
    }

    public function render()
    {
        $screens = SignageScreen::where('team_id', $this->teamId())
            ->where('status', 'active')->orderBy('name')->get();

        $playlists = SignagePlaylist::where('team_id', $this->teamId())
            ->where('kind', 'visual')->orderBy('name')
            ->get()->map(fn ($p) => ['value' => $p->id, 'label' => $p->name])->values()->all();

        $schedules = SignageSchedule::where('team_id', $this->teamId())
            ->orderBy('name')->get()->map(fn ($s) => ['value' => $s->id, 'label' => $s->name])->values()->all();

        return view('signage::livewire.groups.show', [
            'screens'         => $screens,
            'playlistOptions' => $playlists,
            'scheduleOptions' => $schedules,
        ])->layout('platform::layouts.app');
    }
}
