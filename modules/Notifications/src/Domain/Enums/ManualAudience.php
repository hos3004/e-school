<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * تقييد جمهور الهدف الجماعي.
 *
 * الإرسال في كل الحالات رسائل فردية منفصلة لكل مستلم — لا مجموعة واتساب
 * ولا قائمة بث — فلا يرى أحد رقم أحد. هذا الـenum يحدد مَن يُرسل إليهم من
 * أطراف الفصل، لا كيف تُرسل الرسالة.
 */
enum ManualAudience: string
{
    case Students = 'students';

    case Teacher = 'teacher';

    case All = 'all';

    public function includesStudents(): bool
    {
        return $this !== self::Teacher;
    }

    public function includesTeacher(): bool
    {
        return $this !== self::Students;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $audience): array => [
                $audience->value => $audience->label(),
            ])
            ->all();
    }

    public function label(): string
    {
        return __('notifications::fields.audiences.'.$this->value);
    }
}
