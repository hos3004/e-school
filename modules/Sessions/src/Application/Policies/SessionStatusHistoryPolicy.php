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
        return $user->can('session.view');
    }

    public function view(Authenticatable $user, SessionStatusHistory $history): bool
    {
        return $user->can('session.view')
            && $history->session()->first()?->organization_id === $user->getAttribute('organization_id');
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
