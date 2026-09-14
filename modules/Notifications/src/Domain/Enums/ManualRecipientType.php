<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * أنواع الجمهور التي يدعمها الإرسال الإداري اليدوي.
 *
 * المجموعة ليست وحدة التجميع الوحيدة: المدرسة تعمل اليوم بجداول فردية،
 * فالكورس والجدول هما «الفصل» الفعلي الذي تُراسل أطرافه.
 */
enum ManualRecipientType: string
{
    case Student = 'student';

    case Teacher = 'teacher';

    case Group = 'group';

    case Course = 'course';

    case Schedule = 'schedule';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    /**
     * هل يحتمل هذا الهدف أكثر من طرف فيصح تقييده بجمهور؟
     */
    public function isAudienceScoped(): bool
    {
        return in_array($this, [self::Group, self::Course, self::Schedule], true);
    }

    public function label(): string
    {
        return __('notifications::fields.recipient_types.'.$this->value);
    }
}
