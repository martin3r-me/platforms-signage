<?php

namespace Platform\Signage\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;

trait WithCurrentTeam
{
    protected function currentTeam()
    {
        return Auth::user()?->currentTeam;
    }

    protected function teamId(): ?int
    {
        return $this->currentTeam()?->id;
    }
}
