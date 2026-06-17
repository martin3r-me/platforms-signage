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

    /**
     * Team-IDs, deren Bildschirme im aktuellen Team sichtbar sind:
     * Im Parent-/Root-Team alle eigenen + alle (rekursiven) Kind-Teams,
     * in Child-Teams nur das eigene. Dient nur der Anzeige (Dashboard-Überblick);
     * Bearbeiten bleibt aufs jeweilige Team beschränkt.
     *
     * @return array<int>
     */
    protected function screenTeamIds(): array
    {
        $team = $this->currentTeam();
        if (!$team) {
            return [];
        }

        return $team->isRootTeam()
            ? $team->getAllTeamIdsIncludingChildren()
            : [$team->id];
    }
}
