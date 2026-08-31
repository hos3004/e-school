<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Policies;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Recordings\Application\Services\RecordingAccessDecision;
use Modules\Recordings\Domain\Models\Recording;

/**
 * سياسة التسجيلات — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات recordings.recording.<action> المعرّفة
 * في مصفوفة الصلاحيات، مع مقارنة ملكية السجل حيثما أمكن.
 */
final class RecordingPolicy
{
    public function __construct(private readonly RecordingAccessDecision $access) {}

    public function viewAny(Authenticatable&Authorizable $user): bool
    {
        return $user->can('recording.view') || $user->can('recording.view.any');
    }

    public function view(Authenticatable&Authorizable $user, Recording $recording): bool
    {
        return $this->access->canView($user, $recording);
    }

    public function create(Authenticatable&Authorizable $user): bool
    {
        return false;
    }

    public function update(Authenticatable&Authorizable $user, Recording $recording): bool
    {
        return false;
    }

    public function delete(Authenticatable&Authorizable $user, Recording $recording): bool
    {
        return $user->can('recording.delete')
            && $recording->organization_id === data_get($user, 'organization_id');
    }

    /** الإعلان عن الجاهزية أو الأرشفة — عمليات دورة الحياة. */
    public function manageLifecycle(Authenticatable&Authorizable $user, Recording $recording): bool
    {
        return $user->can('recording.delete')
            && $recording->organization_id === data_get($user, 'organization_id');
    }

    /** مشاهدة المحتوى نفسه (توليد رابط موقّع). */
    public function watch(Authenticatable&Authorizable $user, Recording $recording): bool
    {
        return $this->access->canView($user, $recording);
    }
}
