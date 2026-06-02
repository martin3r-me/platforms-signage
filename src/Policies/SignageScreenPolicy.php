<?php

namespace Platform\Signage\Policies;

use Platform\Core\Models\User;
use Platform\Signage\Models\SignageScreen;

class SignageScreenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, SignageScreen $screen): bool
    {
        return $this->owns($user, $screen);
    }

    public function update(User $user, SignageScreen $screen): bool
    {
        return $this->owns($user, $screen);
    }

    public function delete(User $user, SignageScreen $screen): bool
    {
        return $this->owns($user, $screen);
    }

    protected function owns(User $user, SignageScreen $screen): bool
    {
        return $screen->team_id !== null && $screen->team_id === $user->currentTeam?->id;
    }
}
