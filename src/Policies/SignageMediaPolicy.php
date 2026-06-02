<?php

namespace Platform\Signage\Policies;

use Platform\Core\Models\User;
use Platform\Signage\Models\SignageMedia;

class SignageMediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, SignageMedia $media): bool
    {
        return $this->owns($user, $media);
    }

    public function update(User $user, SignageMedia $media): bool
    {
        return $this->owns($user, $media);
    }

    public function delete(User $user, SignageMedia $media): bool
    {
        return $this->owns($user, $media);
    }

    protected function owns(User $user, SignageMedia $media): bool
    {
        return $media->team_id === $user->currentTeam?->id;
    }
}
