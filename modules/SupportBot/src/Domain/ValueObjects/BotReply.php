<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\ValueObjects;

use Modules\SupportBot\Domain\Enums\BotTopic;
use Modules\SupportBot\Domain\Enums\TopicMode;

/**
 * ما يُعرض للمستخدم، وما يُسجَّل عنه.
 *
 * الرد لا يخرج فارغًا أبدًا: كل مسار فشل يملك نصًّا معدًّا. فقاعة فارغة في وجه
 * مستخدم أسوأ من اعتذار واضح.
 */
final readonly class BotReply
{
    public function __construct(
        public string $body,
        public ?BotTopic $topic,
        public ?TopicMode $mode,
        public bool $wasGenerated,
        public string $conversationId,
        public string $correlationId,
        public ?string $failureReason = null,
    ) {}

    /** هل انتهى هذا الدور إلى رد معدّ بدل إجابة مصوغة؟ */
    public function wasWithheld(): bool
    {
        return !$this->wasGenerated;
    }
}
