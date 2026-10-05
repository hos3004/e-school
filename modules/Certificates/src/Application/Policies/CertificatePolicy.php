<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Certificates\Domain\Models\Certificate;

/**
 * سياسة الشهادات.
 *
 * السحب فعل خاص بالموديول (revoke) — لا حذف فعلي؛ السجلات تُعلَّق فقط
 * عبر SoftDeletes داخل إجراء السحب الموثق بالسبب.
 */
final class CertificatePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.certificate.view_any');
    }

    public function view(Authenticatable&Authorizable $user, Certificate $certificate): bool
    {
        return $user->can('certificates.certificate.view')
            && $certificate->organization_id === data_get($user, 'organization_id');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.certificate.create');
    }

    public function update(Authenticatable&Authorizable $user, Certificate $certificate): bool
    {
        // الشهادة الصادرة وثيقة — لا تعديل حر على بياناتها بعد الإصدار.
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, Certificate $certificate): bool
    {
        return $user->can('certificates.certificate.revoke')
            && $certificate->organization_id === data_get($user, 'organization_id');
    }

    public function revoke(Authenticatable&Authorizable $user, Certificate $certificate): bool
    {
        return $user->can('certificates.certificate.revoke')
            && $certificate->organization_id === data_get($user, 'organization_id');
    }
}
