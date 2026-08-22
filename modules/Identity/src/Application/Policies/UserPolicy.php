<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Policies;

use Modules\Identity\Domain\Models\User;

/**
 * سياسة المستخدم — صلاحيات عبر مصفوفة الأذونات فقط، لا فحص أدوار.
 *
 * القدرات: identity.users.view_any / view / create / update / delete
 *          + identity.users.change_status (الإيقاف والتجميد).
 */
final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('identity.users.view_any');
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->can('identity.users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('identity.users.create');
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->id === $target->id || $actor->can('identity.users.update');
    }

    public function delete(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->can('identity.users.delete');
    }

    /** فعل خاص: تغيير حالة الحساب — لا يُستثنى به المالك لنفسه. */
    public function changeStatus(User $actor, User $target): bool
    {
        return $actor->id !== $target->id && $actor->can('identity.users.change_status');
    }
}
