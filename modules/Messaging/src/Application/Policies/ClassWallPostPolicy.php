<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Messaging\Domain\Models\ClassWallPost;

/**
 * سياسة حائط الصف.
 */
final class ClassWallPostPolicy
{
    public function __construct(
        private readonly ClassAudienceQueries $audience,
    ) {}

    /** @param Authenticatable&object{organization_id: string} $user */
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('class_wall.post') || $user->can('message.send');
    }

    /** @param Authenticatable&object{organization_id: string} $user */
    public function view(Authenticatable $user, ClassWallPost $post): bool
    {
        if ($post->organization_id !== $user->organization_id) {
            return false;
        }

        if ($user->can('message.moderate')) {
            return true;
        }

        return ($user->can('class_wall.post') || $user->can('message.send'))
            && $this->audience->canAccessClass(
                (string) $post->organization_id,
                (string) $post->group_id,
                (string) $user->getAuthIdentifier(),
            );
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
            && $post->organization_id === $user->organization_id
            && $this->audience->canAccessClass(
                (string) $post->organization_id,
                (string) $post->group_id,
                (string) $user->getAuthIdentifier(),
            );
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
