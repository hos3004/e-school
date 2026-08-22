<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Modules\Reporting\Domain\Models\OrganizationSnapshot;

/**
 * سياسة اللقطات التنظيمية — قراءة للإدارة، وبناء عبر الإجراء فقط.
 */
final class OrganizationSnapshotPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('reporting.snapshot.view_any');
    }

    public function view($user, OrganizationSnapshot $snapshot): bool
    {
        return $user->can('reporting.snapshot.view')
            && $snapshot->organization_id === $user->organization_id;
    }

    /** بناء اللقطة إجراء مجدول/إداري — عبر بوابة مخصصة. */
    public function create($user): bool
    {
        return $user->can('reporting.snapshot.build');
    }

    /** اللقطات append/upsert — لا تعديل يدوي. */
    public function update($user, OrganizationSnapshot $snapshot): bool
    {
        return false;
    }

    public function delete($user, OrganizationSnapshot $snapshot): bool
    {
        return $user->can('reporting.snapshot.delete')
            && $snapshot->organization_id === $user->organization_id;
    }
}
