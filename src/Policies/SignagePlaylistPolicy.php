<?php

namespace Platform\Signage\Policies;

use Platform\Core\Models\User;
use Platform\Signage\Models\SignagePlaylist;

class SignagePlaylistPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, SignagePlaylist $playlist): bool
    {
        return $this->owns($user, $playlist);
    }

    public function update(User $user, SignagePlaylist $playlist): bool
    {
        return $this->owns($user, $playlist);
    }

    public function delete(User $user, SignagePlaylist $playlist): bool
    {
        return $this->owns($user, $playlist);
    }

    protected function owns(User $user, SignagePlaylist $playlist): bool
    {
        return $playlist->team_id === $user->currentTeam?->id;
    }
}
