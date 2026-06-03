<?php

namespace Platform\Signage\Livewire\Schedules;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageSchedule;

class Index extends Component
{
    use WithCurrentTeam;

    public bool $showCreateModal = false;
    public string $name = '';

    protected array $rules = [
        'name' => 'required|string|max:255',
    ];

    public function openCreateModal(): void
    {
        $this->reset('name');
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

        $schedule = SignageSchedule::create([
            'team_id' => $this->teamId(),
            'name'    => $this->name,
        ]);

        $this->reset('name');
        $this->redirectRoute('signage.schedules.show', $schedule, navigate: true);
    }

    public function deleteSchedule(int $id): void
    {
        SignageSchedule::where('team_id', $this->teamId())->findOrFail($id)->delete();
        session()->flash('signage_message', 'Zeitplan gelöscht.');
    }

    public function render()
    {
        $schedules = SignageSchedule::where('team_id', $this->teamId())
            ->withCount('rules')
            ->orderBy('name')
            ->get();

        return view('signage::livewire.schedules.index', [
            'schedules' => $schedules,
        ])->layout('platform::layouts.app');
    }
}
