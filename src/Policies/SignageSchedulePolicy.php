<?php

namespace Platform\Signage\Policies;

use Platform\Core\Models\User;
use Platform\Signage\Models\SignageSchedule;

class SignageSchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function view(User $user, SignageSchedule $schedule): bool
    {
        return $this->owns($user, $schedule);
    }

    public function update(User $user, SignageSchedule $schedule): bool
    {
        return $this->owns($user, $schedule);
    }

    public function delete(User $user, SignageSchedule $schedule): bool
    {
        return $this->owns($user, $schedule);
    }

    protected function owns(User $user, SignageSchedule $schedule): bool
    {
        return $schedule->team_id === $user->currentTeam?->id;
    }
}
