<?php

declare(strict_types=1);

namespace Modules\Sessions\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Sessions\Domain\Models\SessionStatusHistory;

/**
 * سياسة سجل تغيّر حالات الحصة — قراءة فقط.
 */
final class SessionStatusHistoryPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('sessions.history.view_any');
    }

    public function view(Authenticatable $user, SessionStatusHistory $history): bool
    {
        return $user->can('sessions.history.view');
    }

    public function create(Authenticatable $user): bool
    {
        return false;
    }

    public function update(Authenticatable $user, SessionStatusHistory $history): bool
    {
        return false;
    }

    public function delete(Authenticatable $user, SessionStatusHistory $history): bool
    {
        return false;
    }
}
