<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Policies;

use Modules\Sessions\Domain\Models\Session;

/**
 * سياسة الحصص — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات sessions.<resource>.<action> المعرّفة في
 * مصفوفة الصلاحيات، مع مقارنة ملكية السجل حيثما أمكن.
 */
final class SessionPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('sessions.session.view_any');
    }

    public function view($user, Session $session): bool
    {
        return $user->can('sessions.session.view')
            && $session->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('sessions.session.create');
    }

    public function update($user, Session $session): bool
    {
        return $user->can('sessions.session.update')
            && $session->organization_id === $user->organization_id;
    }

    public function delete($user, Session $session): bool
    {
        return $user->can('sessions.session.delete')
            && $session->organization_id === $user->organization_id
            && !$session->status->isTerminal();
    }

    public function confirm($user, Session $session): bool
    {
        return $user->can('sessions.session.confirm')
            && $session->organization_id === $user->organization_id;
    }

    public function start($user, Session $session): bool
    {
        return $user->can('sessions.session.start')
            && $session->organization_id === $user->organization_id;
    }

    public function end($user, Session $session): bool
    {
        return $user->can('sessions.session.end')
            && $session->organization_id === $user->organization_id;
    }

    public function complete($user, Session $session): bool
    {
        return $user->can('sessions.session.complete')
            && $session->organization_id === $user->organization_id;
    }

    public function cancel($user, Session $session): bool
    {
        return $user->can('sessions.session.cancel')
            && $session->organization_id === $user->organization_id;
    }

    public function postpone($user, Session $session): bool
    {
        return $user->can('sessions.session.postpone')
            && $session->organization_id === $user->organization_id;
    }

    public function markNoShow($user, Session $session): bool
    {
        return $user->can('sessions.session.mark_no_show')
            && $session->organization_id === $user->organization_id;
    }

    public function excuse($user, Session $session): bool
    {
        return $user->can('sessions.session.excuse')
            && $session->organization_id === $user->organization_id;
    }
}
