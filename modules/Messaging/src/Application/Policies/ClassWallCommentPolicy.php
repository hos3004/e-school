<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Messaging\Domain\Models\ClassWallComment;

/**
 * سياسة تعليقات حائط الصف.
 */
final class ClassWallCommentPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('messaging.class_wall_comment.view_any');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, ClassWallComment $comment): bool
    {
        return $user->can('messaging.class_wall_comment.view')
            && $comment->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('messaging.class_wall_comment.create');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, ClassWallComment $comment): bool
    {
        return $user->can('messaging.class_wall_comment.update')
            && $comment->organization_id === $user->organization_id
            && (string) $comment->user_id === $user->getAuthIdentifier();
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, ClassWallComment $comment): bool
    {
        return $user->can('messaging.class_wall_comment.delete')
            && $comment->organization_id === $user->organization_id;
    }
}
