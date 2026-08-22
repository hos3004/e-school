<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Events;

/**
 * أُضيف تعليق على منشور في حائط الصف.
 */
final class ClassWallCommentAdded extends MessagingEvent
{
    public function __construct(
        public readonly string $commentId,
        public readonly string $postId,
        public readonly string $organizationId,
        public readonly string $commenterUserId,
    ) {
        parent::__construct();
    }

    public function name(): string
    {
        return 'messaging.class_wall_comment_added';
    }

    public function payload(): array
    {
        return [
            'comment_id' => $this->commentId,
            'post_id' => $this->postId,
            'organization_id' => $this->organizationId,
            'commenter_user_id' => $this->commenterUserId,
        ];
    }
}
