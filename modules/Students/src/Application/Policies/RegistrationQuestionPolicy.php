<?php

declare(strict_types=1);

namespace Modules\Students\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Students\Domain\Models\RegistrationQuestion;

/** تفويض أسئلة النموذج مع عزل المؤسسة. */
final class RegistrationQuestionPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create');
    }

    public function view(Authenticatable&Authorizable $user, RegistrationQuestion $question): bool
    {
        return $this->canManage($user, $question);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('student.create');
    }

    public function update(Authenticatable&Authorizable $user, RegistrationQuestion $question): bool
    {
        return $this->canManage($user, $question);
    }

    public function delete(Authenticatable&Authorizable $user, RegistrationQuestion $question): bool
    {
        return $this->canManage($user, $question);
    }

    private function canManage(Authenticatable&Authorizable $user, RegistrationQuestion $question): bool
    {
        return $user->can('student.create')
            && hash_equals((string) data_get($user, 'organization_id'), $question->organization_id);
    }
}
