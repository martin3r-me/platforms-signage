<?php

namespace Platform\Signage\Livewire\Schedules;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignagePlaylist;
use Platform\Signage\Models\SignageSchedule;
use Platform\Signage\Models\SignageScheduleRule;
use Platform\Signage\Models\SignageScreen;

class Show extends Component
{
    use WithCurrentTeam;

    public SignageSchedule $schedule;

    public string $name = '';

    // Regel-Formular
    public array $ruleDays = [];
    public string $ruleStart = '08:00';
    public string $ruleEnd = '18:00';
    public ?int $rulePlaylistId = null;
    public ?int $ruleMusicId = null;
    public int $rulePriority = 0;

    public function mount(SignageSchedule $schedule): void
    {
        abort_unless($schedule->team_id === $this->teamId(), 403);
        $this->schedule = $schedule;
        $this->name = (string) $schedule->name;
    }

    public function rename(): void
    {
        $this->validate(['name' => 'required|string|max:255']);
        $this->schedule->update(['name' => $this->name]);
        session()->flash('signage_message', 'Zeitplan gespeichert.');
    }

    public function addRule(): void
    {
        $this->validate([
            'ruleDays'       => 'required|array|min:1',
            'ruleStart'      => 'required',
            'ruleEnd'        => 'required',
            'rulePlaylistId' => 'required|integer',
        ]);

        SignageScheduleRule::create([
            'schedule_id'       => $this->schedule->id,
            'playlist_id'       => $this->rulePlaylistId,
            'music_playlist_id' => $this->ruleMusicId ?: null,
            'days_of_week'      => array_values(array_map('intval', $this->ruleDays)),
            'start_time'        => $this->ruleStart,
            'end_time'          => $this->ruleEnd,
            'priority'          => $this->rulePriority,
            'active'            => true,
        ]);

        $this->reset('ruleDays', 'rulePlaylistId', 'ruleMusicId', 'rulePriority');
        $this->bumpScreens();
        session()->flash('signage_message', 'Regel hinzugefügt.');
    }

    public function deleteRule(int $id): void
    {
        $this->schedule->rules()->whereKey($id)->delete();
        $this->bumpScreens();
    }

    protected function bumpScreens(): void
    {
        SignageScreen::where('schedule_id', $this->schedule->id)->increment('content_version');
    }

    public function render()
    {
        $playlists = SignagePlaylist::where('team_id', $this->teamId())->orderBy('name')->get();

        return view('signage::livewire.schedules.show', [
            'visualPlaylists' => $playlists->where('kind', 'visual')->values(),
            'musicPlaylists'  => $playlists->where('kind', 'music')->values(),
            'scheduleRules'   => $this->schedule->rules()->with(['playlist', 'musicPlaylist'])->get(),
        ])->layout('platform::layouts.app');
    }
}
