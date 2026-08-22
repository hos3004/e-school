<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Modules\Messaging\Domain\Models\ClassWallPost;

/**
 * سياسة حائط الصف.
 */
final class ClassWallPostPolicy
{
    public function viewAny($user): bool
    {
        return $user->can('messaging.class_wall_post.view_any');
    }

    public function view($user, ClassWallPost $post): bool
    {
        return $user->can('messaging.class_wall_post.view')
            && $post->organization_id === $user->organization_id;
    }

    public function create($user): bool
    {
        return $user->can('messaging.class_wall_post.create');
    }

    public function update($user, ClassWallPost $post): bool
    {
        return $user->can('messaging.class_wall_post.update')
            && $post->organization_id === $user->organization_id;
    }

    public function delete($user, ClassWallPost $post): bool
    {
        return $user->can('messaging.class_wall_post.delete')
            && $post->organization_id === $user->organization_id;
    }

    public function pin($user, ClassWallPost $post): bool
    {
        return $user->can('messaging.class_wall_post.pin')
            && $post->organization_id === $user->organization_id;
    }
}
