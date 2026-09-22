<?php

declare(strict_types=1);

namespace Modules\SupportBot\Domain\ValueObjects;

use Modules\SupportBot\Domain\Enums\BotTopic;
use Modules\SupportBot\Domain\Enums\TopicMode;

/**
 * قرار الحارس لسؤال واحد.
 *
 * `clampedByCode` يوثّق أن سياجًا في الكود خفّض الوضع رغم ما يقوله الجدول.
 * يظهر في الأرشيف ليعرف من يراجع لاحقًا أن قاعدة محرَّرة حاولت السماح بما لا
 * يُسمح به — وهي إشارة تستحق الانتباه لا أن تمر صامتة.
 */
final readonly class GuardrailDecision
{
    public function __construct(
        public BotTopic $topic,
        public TopicMode $mode,
        public string $replyKey,
        public bool $clampedByCode = false,
    ) {}

    public function allowsGeneration(): bool
    {
        return $this->mode->allowsGeneration();
    }
}
