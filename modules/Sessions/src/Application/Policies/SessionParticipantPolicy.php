<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Sessions\Domain\Models\SessionParticipant;

/**
 * سياسة مشاركو الحصص.
 */
final class SessionParticipantPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('attendance.view');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, SessionParticipant $participant): bool
    {
        return $user->can('attendance.view')
            && $participant->session()->first()?->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, SessionParticipant $participant): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, SessionParticipant $participant): bool
    {
        return false;
    }
}
