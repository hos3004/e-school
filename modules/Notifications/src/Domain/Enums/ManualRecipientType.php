<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/** أنواع الجمهور التي يدعمها الإرسال الإداري اليدوي. */
enum ManualRecipientType: string
{
    case Student = 'student';

    case Teacher = 'teacher';

    case Group = 'group';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    public function label(): string
    {
        return __('notifications::fields.recipient_types.'.$this->value);
    }
}
