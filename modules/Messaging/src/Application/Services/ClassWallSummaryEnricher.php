<?php

declare(strict_types=1);

namespace Modules\Messaging\Application\Services;

use Illuminate\Support\Collection;
use Modules\Identity\Domain\Contracts\UserAccountDirectory;
use Modules\Messaging\Domain\Models\ClassWallComment;
use Modules\Messaging\Domain\Models\ClassWallPost;

/**
 * يحقن اسم الكاتب (وعدد التعليقات للمنشور) كـattributes ديناميكية قبل أن
 * تقرأها ClassWallPostResource/ClassWallCommentResource — بنفس أسلوب
 * ConversationSummaryEnricher ولنفس السبب: لا الموديل ولا القرص يحملان
 * اسم المستخدم، وModules الأخرى لا تُستدعى إلا عبر UserAccountDirectory.
 */
final readonly class ClassWallSummaryEnricher
{
    public function __construct(
        private UserAccountDirectory $accounts,
    ) {}

    /**
     * @param iterable<int, ClassWallPost> $posts
     */
    public function attachToPosts(iterable $posts, string $organizationId): void
    {
        /** @var Collection<int, ClassWallPost> $list */
        $list = $posts instanceof Collection ? $posts : collect($posts);

        if ($list->isEmpty()) {
            return;
        }

        $postIds = $list->map(static fn (ClassWallPost $post): string => (string) $post->id)->all();

        $userIds = $list->map(static fn (ClassWallPost $post): string => (string) $post->user_id)
            ->unique()
            ->values()
            ->all();
        $accounts = $this->accounts->findMany($organizationId, $userIds);

        $commentCounts = ClassWallComment::query()
            ->whereIn('post_id', $postIds)
            ->selectRaw('post_id, count(*) as aggregate')
            ->groupBy('post_id')
            ->pluck('aggregate', 'post_id');

        foreach ($list as $post) {
            $account = $accounts[(string) $post->user_id] ?? null;
            $name = $account !== null ? $account->name : __('messaging::fields.unknown_sender');
            $post->setAttribute('author_name', $name);
            $post->setAttribute(
                'comments_count',
                (int) ($commentCounts[(string) $post->id] ?? 0),
            );
        }
    }

    /**
     * @param iterable<int, ClassWallComment> $comments
     */
    public function attachToComments(iterable $comments, string $organizationId): void
    {
        /** @var Collection<int, ClassWallComment> $list */
        $list = $comments instanceof Collection ? $comments : collect($comments);

        if ($list->isEmpty()) {
            return;
        }

        $userIds = $list->map(static fn (ClassWallComment $comment): string => (string) $comment->user_id)
            ->unique()
            ->values()
            ->all();
        $accounts = $this->accounts->findMany($organizationId, $userIds);

        foreach ($list as $comment) {
            $account = $accounts[(string) $comment->user_id] ?? null;
            $name = $account !== null ? $account->name : __('messaging::fields.unknown_sender');
            $comment->setAttribute('author_name', $name);
        }
    }
}
