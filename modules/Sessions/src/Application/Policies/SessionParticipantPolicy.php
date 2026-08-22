<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;

/**
 * سياسة مشاركو الحصص.
 */
final class SessionParticipantPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user, Session $session): bool
    {
        return $user->can('sessions.participant.view_any')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, SessionParticipant $participant): bool
    {
        return $user->can('sessions.participant.view')
            && $participant->session()->first()?->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('sessions.participant.create');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, SessionParticipant $participant): bool
    {
        return $user->can('sessions.participant.update');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, SessionParticipant $participant): bool
    {
        return $user->can('sessions.participant.delete');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function recordAttendance(Authenticatable $user, Session $session): bool
    {
        return $user->can('sessions.participant.record_attendance')
            && $session->organization_id === $user->organization_id;
    }
}
