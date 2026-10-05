<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\AcademicReports\Domain\Models\MonthlyReport;

/**
 * سياسة التقارير الشهرية — لا فحص لأسماء الأدوار إطلاقًا.
 */
final class MonthlyReportPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('report.view');
    }

    public function view(Authenticatable&Authorizable $user, MonthlyReport $report): bool
    {
        return $user->can('report.view')
            && $this->belongsToOrganization($user, (string) $report->getAttribute('organization_id'));
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('monthly_report.create');
    }

    public function update(Authenticatable&Authorizable $user, MonthlyReport $report): bool
    {
        return $user->can('monthly_report.create')
            && $this->belongsToOrganization($user, (string) $report->getAttribute('organization_id'));
    }

    public function delete(Authenticatable&Authorizable $user, MonthlyReport $report): bool
    {
        return false;
    }

    public function approve(Authenticatable&Authorizable $user, MonthlyReport $report): bool
    {
        return $user->can('monthly_report.approve')
            && $this->belongsToOrganization($user, (string) $report->getAttribute('organization_id'));
    }

    public function send(Authenticatable&Authorizable $user, MonthlyReport $report): bool
    {
        return $user->can('monthly_report.approve')
            && $this->belongsToOrganization($user, (string) $report->getAttribute('organization_id'));
    }

    private function belongsToOrganization(Authenticatable $user, string $organizationId): bool
    {
        $actorOrganizationId = data_get($user, 'organization_id');

        return is_string($actorOrganizationId)
            && $actorOrganizationId !== ''
            && hash_equals($actorOrganizationId, $organizationId);
    }
}
