<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Enums;

/**
 * دورة حياة حملة واتساب.
 *
 * draft     → أُنشئت وقائمتها جاهزة للمراجعة، ولم يخرج منها شيء بعد.
 * running   → وُزِّعت مهام الإرسال المؤجلة، والرسائل تخرج على مهلها.
 * completed → لم يبق مستلم في الانتظار — حالة نهائية.
 * stopped   → أوقفها المرسِل أو أوقفها مفتاح القناة — نهائية، والمتبقي يُلغى.
 */
enum WhatsappCampaignStatus: string
{
    case Draft = 'draft';

    case Running = 'running';

    case Completed = 'completed';

    case Stopped = 'stopped';

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Running, self::Stopped],
            self::Running => [self::Completed, self::Stopped],
            self::Completed, self::Stopped => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
