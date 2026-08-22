<?php

declare(strict_types=1);

namespace Modules\Recordings\Application\Policies;

use Illuminate\Auth\Access\Response;
use Modules\Recordings\Domain\Models\Recording;

/**
 * سياسة التسجيلات — لا فحص لأسماء الأدوار إطلاقًا.
 *
 * كل فعل يمر عبر بوابة الصلاحيات recordings.recording.<action> المعرّفة
 * في مصفوفة الصلاحيات، مع مقارنة ملكية السجل حيثما أمكن.
 */
final class RecordingPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('recordings.recording.view_any');
    }

    public function view($user, Recording $recording): bool
    {
        return $user->can('recordings.recording.view')
            && $recording->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('recordings.recording.create');
    }

    public function update($user, Recording $recording): bool
    {
        return $user->can('recordings.recording.update')
            && $recording->organization_id === $user->organization_id;
    }

    public function delete($user, Recording $recording): bool
    {
        return $user->can('recordings.recording.delete')
            && $recording->organization_id === $user->organization_id;
    }

    /** الإعلان عن الجاهزية أو الأرشفة — عمليات دورة الحياة. */
    public function manageLifecycle($user, Recording $recording): bool
    {
        return $user->can('recordings.recording.manage_lifecycle')
            && $recording->organization_id === $user->organization_id;
    }

    /** مشاهدة المحتوى نفسه (توليد رابط موقّع). */
    public function watch($user, Recording $recording): bool
    {
        return $user->can('recordings.recording.watch')
            && $recording->organization_id === $user->organization_id;
    }
}
