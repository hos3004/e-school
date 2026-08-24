<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Contracts\ClassAudienceQueries;
use Modules\Messaging\Domain\Events\ClassWallPostPublished;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/**
 * نشر منشور على حائط الصف.
 */
final readonly class PublishWallPostAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
        private ClassAudienceQueries $audience,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $attachments
     */
    public function execute(
        string $organizationId,
        string $groupId,
        string $authorUserId,
        string $body,
        array $attachments = [],
        bool $isPinned = false,
    ): ClassWallPost {
        if (!$this->audience->canAccessClass($organizationId, $groupId, $authorUserId)) {
            throw BusinessRuleViolation::make(
                'messaging.class_access_denied',
                'messaging::errors.class_access_denied',
            );
        }

        $post = $this->transaction->run(function () use (
            $organizationId,
            $groupId,
            $authorUserId,
            $body,
            $attachments,
            $isPinned,
        ): ClassWallPost {
            $wallPost = new ClassWallPost([
                'organization_id' => $organizationId,
                'group_id' => $groupId,
                'user_id' => $authorUserId,
                'body' => $body,
                'attachments' => $attachments,
                'is_pinned' => $isPinned,
                'created_at' => now(),
            ]);
            $wallPost->save();

            return $wallPost;
        });

        $this->events->dispatch(new ClassWallPostPublished(
            postId: (string) $post->id,
            organizationId: $organizationId,
            groupId: $groupId,
            authorUserId: $authorUserId,
        ));

        return $post;
    }
}
