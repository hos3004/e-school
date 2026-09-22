<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\ValueObjects;

use Modules\SupportBot\Domain\Enums\BotTopic;

/**
 * نتيجة مرحلة التصنيف، ومعها ما كلّفته.
 *
 * الفشل لا يُترجم إلى موضوع افتراضي هنا: التمييز بين «صنّفتُه غير مفهوم» و«لم
 * أستطع التصنيف أصلًا» يغيّر ما يُعرض للمستخدم — الأول يطلب توضيح السؤال،
 * والثاني يعتذر عن عطل مؤقت.
 */
final readonly class ClassificationResult
{
    private function __construct(
        public ?BotTopic $topic,
        public ?string $failureReason,
        public int $inputTokens,
        public int $outputTokens,
    ) {}

    public static function classified(BotTopic $topic, int $inputTokens, int $outputTokens): self
    {
        return new self($topic, null, $inputTokens, $outputTokens);
    }

    public static function failed(string $reason): self
    {
        return new self(null, $reason, 0, 0);
    }

    public function succeeded(): bool
    {
        return $this->topic instanceof BotTopic;
    }
}
