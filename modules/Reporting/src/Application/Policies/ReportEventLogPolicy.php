<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Modules\Reporting\Domain\Models\ReportEventLog;

/**
 * سياسة سجل الأحداث — قراءة تشخيصية للإدارة فقط، والسجل append-only.
 */
final class ReportEventLogPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('reporting.event_log.view_any');
    }

    public function view($user, ReportEventLog $log): bool
    {
        return $user->can('reporting.event_log.view')
            && $log->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return false;
    }

    public function update($user, ReportEventLog $log): bool
    {
        return false;
    }

    public function delete($user, ReportEventLog $log): bool
    {
        return $user->can('reporting.event_log.delete')
            && $log->organization_id === $user->organization_id;
    }
}
