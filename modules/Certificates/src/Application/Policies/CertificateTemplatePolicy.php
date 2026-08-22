<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Policies;

use Modules\Certificates\Domain\Models\CertificateTemplate;

/**
 * سياسة قوالب الشهادات — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات certificates.<resource>.<action>
 * المعرّفة في مصفوفة الصلاحيات، مع مقارنة ملكية السجل للمؤسسة.
 */
final class CertificateTemplatePolicy
{
    public function viewAny($user): bool
    {
        return $user->can('certificates.template.view_any');
    }

    public function view($user, CertificateTemplate $template): bool
    {
        return $user->can('certificates.template.view')
            && $template->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('certificates.template.create');
    }

    public function update($user, CertificateTemplate $template): bool
    {
        return $user->can('certificates.template.update')
            && $template->organization_id === $user->organization_id;
    }

    public function delete($user, CertificateTemplate $template): bool
    {
        return $user->can('certificates.template.delete')
            && $template->organization_id === $user->organization_id;
    }
}
