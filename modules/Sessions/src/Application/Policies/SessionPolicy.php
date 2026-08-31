<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Sessions\Domain\Models\Session;

/**
 * سياسة الحصص — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات sessions.<resource>.<action> المعرّفة في
 * مصفوفة الصلاحيات، مع مقارنة ملكية السجل حيثما أمكن.
 */
final class SessionPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('session.view');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.view')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('session.create');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, Session $session): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, Session $session): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function confirm(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.view')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function start(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.create')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function end(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.finalize')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function complete(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.finalize')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function cancel(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.cancel')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function postpone(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.postpone.request')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function assignSubstitute(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.assign_substitute')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function markNoShow(Authenticatable $user, Session $session): bool
    {
        return $user->can('attendance.record')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function recordAttendance(Authenticatable $user, Session $session): bool
    {
        return $user->can('attendance.record')
            && $session->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function excuse(Authenticatable $user, Session $session): bool
    {
        return $user->can('session.cancel')
            && $session->organization_id === $user->organization_id;
    }
}
