<?php

declare(strict_types=1);

namespace Modules\Certificates\Application\Policies;

use Modules\Certificates\Domain\Models\BadgeAward;

/**
 * سياسة منح الشارات.
 *
 * قيود المنح لصيقة (append-only): تُنشأ مرة واحدة ولا تُعدَّل ولا تُحذف،
 * لذلك update و delete مرفوضان دائمًا بغض النظر عن الصلاحيات.
 */
final class BadgeAwardPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('certificates.award.view_any');
    }

    public function view($user, BadgeAward $award): bool
    {
        return $user->can('certificates.award.view')
            && $award->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('certificates.award.create');
    }

    public function update($user, BadgeAward $award): bool
    {
        return false;
    }

    public function delete($user, BadgeAward $award): bool
    {
        return false;
    }
}
