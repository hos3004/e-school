<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Certificates\Domain\Models\CertificateTemplate;

/**
 * سياسة قوالب الشهادات — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات certificates.<resource>.<action>
 * المعرّفة في مصفوفة الصلاحيات، مع مقارنة ملكية السجل للمؤسسة.
 */
final class CertificateTemplatePolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.template.view_any');
    }

    public function view(Authenticatable&Authorizable $user, CertificateTemplate $template): bool
    {
        return $user->can('certificates.template.view')
            && $template->organization_id === data_get($user, 'organization_id');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.template.create');
    }

    public function update(Authenticatable&Authorizable $user, CertificateTemplate $template): bool
    {
        return $user->can('certificates.template.update')
            && $template->organization_id === data_get($user, 'organization_id');
    }

    public function delete(Authenticatable&Authorizable $user, CertificateTemplate $template): bool
    {
        return $user->can('certificates.template.delete')
            && $template->organization_id === data_get($user, 'organization_id');
    }
}
