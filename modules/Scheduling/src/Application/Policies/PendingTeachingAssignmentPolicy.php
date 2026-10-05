<?php

declare(strict_types=1);

namespace Modules\Scheduling\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Scheduling\Domain\Models\PendingTeachingAssignment;

final class PendingTeachingAssignmentPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('schedule.view');
    }

    public function view(Authenticatable $user, PendingTeachingAssignment $schedule): bool
    {
        return $user->can('schedule.view')
            && $schedule->organization_id === $user->getAttribute('organization_id');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('schedule.manage');
    }

    public function update(Authenticatable $user, PendingTeachingAssignment $schedule): bool
    {
        return $user->can('schedule.manage')
            && $schedule->organization_id === $user->getAttribute('organization_id');
    }

    public function deactivate(Authenticatable $user, PendingTeachingAssignment $schedule): bool
    {
        return $this->update($user, $schedule);
    }

    public function activate(Authenticatable $user, PendingTeachingAssignment $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
