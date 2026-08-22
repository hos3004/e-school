<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Messaging\Domain\Models\ClassWallPost;

/**
 * سياسة حائط الصف.
 */
final class ClassWallPostPolicy
{
    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('class_wall.post') || $user->can('message.send');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, ClassWallPost $post): bool
    {
        return ($user->can('class_wall.post') || $user->can('message.send'))
            && $post->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function create(Authenticatable $user): bool
    {
        return $user->can('class_wall.post');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function update(Authenticatable $user, ClassWallPost $post): bool
    {
        return $user->can('class_wall.post')
            && $post->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function delete(Authenticatable $user, ClassWallPost $post): bool
    {
        return ($user->can('class_wall.post') || $user->can('message.moderate'))
            && $post->organization_id === $user->organization_id;
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function pin(Authenticatable $user, ClassWallPost $post): bool
    {
        return $user->can('class_wall.post')
            && $post->organization_id === $user->organization_id;
    }
}
