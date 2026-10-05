<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Certificates\Domain\Models\BadgeAward;

/**
 * سياسة منح الشارات.
 *
 * قيود المنح لصيقة (append-only): تُنشأ مرة واحدة ولا تُعدَّل ولا تُحذف،
 * لذلك update و delete مرفوضان دائمًا بغض النظر عن الصلاحيات.
 */
final class BadgeAwardPolicy
{
    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.award.view_any');
    }

    public function view(Authenticatable&Authorizable $user, BadgeAward $award): bool
    {
        return $user->can('certificates.award.view')
            && $award->organization_id === data_get($user, 'organization_id');
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return $user->can('certificates.award.create');
    }

    public function update(Authenticatable&Authorizable $user, BadgeAward $award): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, BadgeAward $award): bool
    {
        return false;
    }
}
