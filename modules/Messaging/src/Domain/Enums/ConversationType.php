<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain\Enums;

/**
 * نوع المحادثة.
 *
 * direct   : محادثة ثنائية بين مستخدمين.
 * group    : مجموعة حرة بعدة مشاركين.
 * class    : محادثة مرتبطة بمجموعة صفية عبر related_type/related_id.
 */
enum ConversationType: string
{
    case Direct = 'direct';

    case Group = 'group';

    case Class = 'class';

    /**
     * الأنواع المسموح الانتقال إليها عند إنشاء المحادثة فقط — النوع ثابت
     * بعد الإنشاء ولا يُعدَّل، لذلك لا توجد انتقالات لاحقة.
     */
    public function canTransitionTo(self $target): bool
    {
        return $this === $target;
    }

    /** هل تقبل هذه المحادثة أكثر من مشاركَين؟ */
    public function allowsMultipleParticipants(): bool
    {
        return $this !== self::Direct;
    }

    public function label(): string
    {
        return __('messaging::enums.conversation_type.'.$this->value);
    }
}
