<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Messaging\Domain\Events\ClassWallCommentAdded;
use Modules\Messaging\Domain\Models\ClassWallComment;
use Modules\Messaging\Domain\Models\ClassWallPost;
use Shared\Support\Transaction;

/**
 * إضافة تعليق على منشور في حائط الصف.
 */
final readonly class AddWallCommentAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(ClassWallPost $post, string $commenterUserId, string $body): ClassWallComment
    {
        $comment = $this->transaction->run(function () use ($post, $commenterUserId, $body): ClassWallComment {
            $wallComment = new ClassWallComment([
                'organization_id' => $post->organization_id,
                'post_id' => (string) $post->id,
                'user_id' => $commenterUserId,
                'body' => $body,
            ]);
            $wallComment->created_at = now();
            $wallComment->save();

            return $wallComment;
        });

        $this->events->dispatch(new ClassWallCommentAdded(
            commentId: (string) $comment->id,
            postId: (string) $post->id,
            organizationId: (string) $post->organization_id,
            commenterUserId: $commenterUserId,
        ));

        return $comment;
    }
}
