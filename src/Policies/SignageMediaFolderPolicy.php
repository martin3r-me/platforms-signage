<?php

namespace Platform\Signage\Policies;

use Platform\Core\Models\User;
use Platform\Signage\Models\SignageMediaFolder;

class SignageMediaFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, SignageMediaFolder $folder): bool
    {
        return $this->owns($user, $folder);
    }

    public function update(User $user, SignageMediaFolder $folder): bool
    {
        return $this->owns($user, $folder);
    }

    public function delete(User $user, SignageMediaFolder $folder): bool
    {
        return $this->owns($user, $folder);
    }

    protected function owns(User $user, SignageMediaFolder $folder): bool
    {
        return $folder->team_id === $user->currentTeam?->id;
    }
}
