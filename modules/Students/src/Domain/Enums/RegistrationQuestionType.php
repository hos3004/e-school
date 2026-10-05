<?php

declare(strict_types=1);

namespace Modules\Students\Domain\Enums;

/**
 * نوع سؤال التقييم في طلب التسجيل.
 */
enum RegistrationQuestionType: string
{
    case Text = 'text';

    case Textarea = 'textarea';

    case Select = 'select';

    case Radio = 'radio';

    case Checkbox = 'checkbox';

    case Number = 'number';

    public function hasOptions(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Checkbox], true);
    }

    public function canBeFiltered(): bool
    {
        return in_array($this, [self::Select, self::Radio, self::Number], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }

    public function label(): string
    {
        return __('students::registration_questions.types.'.$this->value);
    }
}
