<?php

namespace Platform\Signage\Livewire\Groups;

use Livewire\Component;
use Platform\Signage\Livewire\Concerns\WithCurrentTeam;
use Platform\Signage\Models\SignageScreenGroup;

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

        $group = SignageScreenGroup::create([
            'team_id' => $this->teamId(),
            'user_id' => auth()->id(),
            'name'    => $this->name,
        ]);

        $this->reset('name');
        $this->redirectRoute('signage.groups.show', $group, navigate: true);
    }

    public function deleteGroup(int $id): void
    {
        SignageScreenGroup::where('team_id', $this->teamId())->findOrFail($id)->delete();
        session()->flash('signage_message', 'Gruppe gelöscht.');
    }

    public function render()
    {
        $groups = SignageScreenGroup::where('team_id', $this->teamId())
            ->withCount('screens')
            ->orderBy('name')
            ->get();

        return view('signage::livewire.groups.index', [
            'groups' => $groups,
        ])->layout('platform::layouts.app');
    }
}
