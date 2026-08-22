<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reporting\Domain\Models\ReportEventLog;

/**
 * سياسة سجل الأحداث — قراءة تشخيصية للإدارة فقط، والسجل append-only.
 */
final class ReportEventLogPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('reporting.event_log.view_any');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, ReportEventLog $log): bool
    {
        return $user->can('reporting.event_log.view')
            && $log->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, ReportEventLog $log): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, ReportEventLog $log): bool
    {
        return $user->can('reporting.event_log.delete')
            && $log->organization_id === $user->organization_id;
    }
}
