<?php

declare(strict_types=1);

namespace Modules\Reporting\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;

/**
 * سياسة اللقطات التنظيمية — قراءة للإدارة، وبناء عبر الإجراء فقط.
 */
final class OrganizationSnapshotPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('reporting.snapshot.view_any');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, OrganizationSnapshot $snapshot): bool
    {
        return $user->can('reporting.snapshot.view')
            && $snapshot->organization_id === $user->organization_id;
    }

    /** بناء اللقطة إجراء مجدول/إداري — عبر بوابة مخصصة. */
    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('reporting.snapshot.build');
    }

    /** اللقطات append/upsert — لا تعديل يدوي. */
    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, OrganizationSnapshot $snapshot): bool
    {
        return false;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, OrganizationSnapshot $snapshot): bool
    {
        return $user->can('reporting.snapshot.delete')
            && $snapshot->organization_id === $user->organization_id;
    }
}
