<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Policies;

use Modules\Sessions\Domain\Models\SessionStatusHistory;

/**
 * سياسة سجل تغيّر حالات الحصة — قراءة فقط.
 */
final class SessionStatusHistoryPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('sessions.history.view_any');
    }

    public function view($user, SessionStatusHistory $history): bool
    {
        return $user->can('sessions.history.view');
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, SessionStatusHistory $history): bool
    {
        return false;
    }

    public function delete($user, SessionStatusHistory $history): bool
    {
        return false;
    }
}
