<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Modules\Messaging\Domain\Models\ClassWallComment;

/**
 * سياسة تعليقات حائط الصف.
 */
final class ClassWallCommentPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('messaging.class_wall_comment.view_any');
    }

    public function view($user, ClassWallComment $comment): bool
    {
        return $user->can('messaging.class_wall_comment.view')
            && $comment->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('messaging.class_wall_comment.create');
    }

    public function update($user, ClassWallComment $comment): bool
    {
        return $user->can('messaging.class_wall_comment.update')
            && $comment->organization_id === $user->organization_id
            && (string) $comment->user_id === $user->getAuthIdentifier();
    }

    public function delete($user, ClassWallComment $comment): bool
    {
        return $user->can('messaging.class_wall_comment.delete')
            && $comment->organization_id === $user->organization_id;
    }
}
