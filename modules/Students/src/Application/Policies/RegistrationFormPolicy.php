<?php

declare(strict_types=1);

namespace Modules\Students\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Students\Domain\Models\RegistrationForm;

/** إدارة نماذج التسجيل مقصورة على مسؤول القبول داخل مؤسسته. */
final class RegistrationFormPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create');
    }

    public function view(Authenticatable&Authorizable $user, RegistrationForm $form): bool
    {
        return $this->canManage($user, $form);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create');
    }

    public function update(Authenticatable&Authorizable $user, RegistrationForm $form): bool
    {
        return $this->canManage($user, $form);
    }

    /** النماذج التاريخية لا تُحذف؛ تُعطّل للحفاظ على مصدر الطلبات. */
    public function delete(Authenticatable&Authorizable $user, RegistrationForm $form): bool
    {
        return false;
    }

    private function canManage(Authenticatable&Authorizable $user, RegistrationForm $form): bool
    {
        return $user->can('student.create')
            && hash_equals((string) data_get($user, 'organization_id'), $form->organization_id);
    }
}
