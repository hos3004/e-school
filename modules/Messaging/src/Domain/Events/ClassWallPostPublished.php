<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * نُشر منشور جديد على حائط الصف.
 */
final class ClassWallPostPublished extends MessagingEvent
{
    public function __construct(
        public readonly string $postId,
        public readonly string $organizationId,
        public readonly string $groupId,
        public readonly string $authorUserId,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.class_wall_post_published';
    }

    public function payload(): array
    {
        return [
            'post_id' => $this->postId,
            'organization_id' => $this->organizationId,
            'group_id' => $this->groupId,
            'author_user_id' => $this->authorUserId,
        ];
    }
}
