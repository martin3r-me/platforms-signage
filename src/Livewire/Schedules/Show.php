<?php

namespace Platform\Signage\Livewire\Schedules;

use Illuminate\Validation\Rule;
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
    public bool $showRuleModal = false;
    public array $ruleDays = [];
    public bool $ruleAllDay = false;
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

    /**
     * Öffnet den Dialog zum Anlegen eines Zeitfensters – optional vorausgefüllt
     * mit dem im Kalender angeklickten Wochentag + Startstunde.
     */
    public function openRuleModal(?int $day = null, ?int $hour = null): void
    {
        $this->reset('ruleDays', 'ruleAllDay', 'ruleStart', 'ruleEnd', 'rulePlaylistId', 'ruleMusicId', 'rulePriority');
        $this->resetValidation();

        if ($day !== null && $day >= 1 && $day <= 7) {
            $this->ruleDays = [$day];
        }

        if ($hour !== null) {
            $hour = max(0, min(23, $hour));
            $this->ruleStart = sprintf('%02d:00', $hour);
            $this->ruleEnd = sprintf('%02d:00', min(24, $hour + 2));
        }

        $this->showRuleModal = true;
    }

    public function closeRuleModal(): void
    {
        $this->showRuleModal = false;
    }

    public function addRule(): void
    {
        // Ganztägig wird als 00:00–00:00 gespeichert (= voller Tag in der Zeitlogik).
        if ($this->ruleAllDay) {
            $this->ruleStart = '00:00';
            $this->ruleEnd = '00:00';
        }

        $teamId = $this->teamId();
        $rules = [
            'ruleDays'       => 'required|array|min:1',
            'ruleDays.*'     => 'integer|between:1,7',
            // Nur eigene (Team-)Playlists des passenden Typs zulassen.
            'rulePlaylistId' => ['required', 'integer', Rule::exists('signage_playlists', 'id')
                ->where('team_id', $teamId)->where('kind', 'visual')->whereNull('deleted_at')],
            'ruleMusicId'    => ['nullable', 'integer', Rule::exists('signage_playlists', 'id')
                ->where('team_id', $teamId)->where('kind', 'music')->whereNull('deleted_at')],
            'rulePriority'   => 'integer|min:0|max:9999',
        ];
        if (!$this->ruleAllDay) {
            $rules['ruleStart'] = 'required|date_format:H:i';
            $rules['ruleEnd'] = 'required|date_format:H:i';
        }
        $this->validate($rules);

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

        $this->reset('ruleDays', 'ruleAllDay', 'rulePlaylistId', 'ruleMusicId', 'rulePriority');
        $this->showRuleModal = false;
        $this->bumpScreens();
        session()->flash('signage_message', 'Zeitfenster hinzugefügt.');
    }

    public function deleteRule(int $id): void
    {
        $this->schedule->rules()->whereKey($id)->delete();
        $this->bumpScreens();
    }

    protected function bumpScreens(): void
    {
        SignageScreen::whereHas('schedules', fn ($q) => $q->whereKey($this->schedule->id))
            ->increment('content_version');
    }

    /** Feste Farbpalette für die Kalenderblöcke (Inline-Style -> kein Tailwind-Build nötig). */
    private const PALETTE = ['#10b981', '#f59e0b', '#0ea5e9', '#8b5cf6', '#f43f5e', '#14b8a6', '#6366f1', '#fb923c'];

    private function toMinutes($value): int
    {
        [$h, $m] = array_pad(explode(':', (string) $value), 2, '0');

        return ((int) $h) * 60 + ((int) $m);
    }

    /** Zeitfenster einer Regel als Minuten-Segmente; über Mitternacht -> zwei Segmente. */
    private function ruleSegments(SignageScheduleRule $rule): array
    {
        $s = $this->toMinutes($rule->start_time);
        $e = $this->toMinutes($rule->end_time);

        if ($e > $s) {
            return [[$s, $e]];
        }

        $segments = [[$s, 24 * 60]];
        if ($e > 0) {
            $segments[] = [0, $e];
        }

        return $segments;
    }

    /**
     * Wochen-Kalenderdaten: sichtbarer Stundenbereich + Blöcke je Wochentag (1=Mo..7=So).
     */
    public function calendarData($rules): array
    {
        $minStart = 24 * 60;
        $maxEnd = 0;

        foreach ($rules as $rule) {
            $s = $this->toMinutes($rule->start_time);
            $e = $this->toMinutes($rule->end_time);
            $minStart = min($minStart, $s);

            if ($e <= $s) {
                $minStart = 0;
                $maxEnd = 24 * 60;
            } else {
                $maxEnd = max($maxEnd, $e);
            }
        }

        if ($rules->isEmpty()) {
            $minStart = 8 * 60;
            $maxEnd = 20 * 60;
        }

        $startHour = max(0, intdiv($minStart, 60));
        $endHour = min(24, (int) ceil($maxEnd / 60));
        if ($endHour <= $startHour) {
            $endHour = min(24, $startHour + 1);
        }

        $blocks = [];
        foreach ($rules as $i => $rule) {
            $color = self::PALETTE[$i % count(self::PALETTE)];
            foreach ($this->ruleSegments($rule) as [$sm, $em]) {
                foreach (($rule->days_of_week ?? []) as $d) {
                    $blocks[(int) $d][] = [
                        'ruleId'   => $rule->id,
                        'top'      => $sm - $startHour * 60,
                        'len'      => $em - $sm,
                        'color'    => $color,
                        'playlist' => $rule->playlist?->name ?? '—',
                        'music'    => $rule->musicPlaylist?->name,
                        'time'     => substr((string) $rule->start_time, 0, 5).'–'.substr((string) $rule->end_time, 0, 5),
                        'priority' => $rule->priority,
                    ];
                }
            }
        }

        return ['startHour' => $startHour, 'endHour' => $endHour, 'blocks' => $blocks];
    }

    public function render()
    {
        $playlists = SignagePlaylist::where('team_id', $this->teamId())->orderBy('name')->get();

        // Niedrigste Priorität zuerst -> höhere Priorität wird später gerendert (liegt oben).
        $rules = $this->schedule->rules()->with(['playlist', 'musicPlaylist'])->reorder()->orderBy('priority')->get();

        return view('signage::livewire.schedules.show', [
            'visualPlaylists' => $playlists->where('kind', 'visual')->values(),
            'musicPlaylists'  => $playlists->where('kind', 'music')->values(),
            'scheduleRules'   => $rules,
            'calendar'        => $this->calendarData($rules),
        ])->layout('platform::layouts.app');
    }
}
