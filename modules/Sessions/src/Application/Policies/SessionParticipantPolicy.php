<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Policies;

use Modules\Sessions\Domain\Models\Session;
use Modules\Sessions\Domain\Models\SessionParticipant;

/**
 * سياسة مشاركو الحصص.
 */
final class SessionParticipantPolicy
{
    public function viewAny($user, Session $session): bool
    {
        return $user->can('sessions.participant.view_any')
            && $session->organization_id === $user->organization_id;
    }

    public function view($user, SessionParticipant $participant): bool
    {
        return $user->can('sessions.participant.view')
            && $participant->session()->first()?->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('sessions.participant.create');
    }

    public function update($user, SessionParticipant $participant): bool
    {
        return $user->can('sessions.participant.update');
    }

    public function delete($user, SessionParticipant $participant): bool
    {
        return $user->can('sessions.participant.delete');
    }

    public function recordAttendance($user, Session $session): bool
    {
        return $user->can('sessions.participant.record_attendance')
            && $session->organization_id === $user->organization_id;
    }
}
