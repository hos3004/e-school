<?php

declare(strict_types=1);

namespace Modules\Notifications\Domain\Enums;

/**
 * جمهور الحملة — قيم مغلقة تُفسَّر عبر عقد AudienceResolver القانوني،
 * ولا يُسمح أبدًا بفحص role مباشرة في الواجهات.
 */
enum PopupAudience: string
{
    case Student = 'student';
    case Guardian = 'guardian';
    case Teacher = 'teacher';
    case Supervisor = 'supervisor';
    case Administrator = 'administrator';
    case AllAuthenticated = 'all_authenticated';

    public function label(): string
    {
        return __('notifications::popups.audience.'.$this->value);
    }

    /** @return list<self> */
    public static function concrete(): array
    {
        return [
            self::Student,
            self::Guardian,
            self::Teacher,
            self::Supervisor,
            self::Administrator,
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(static fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}
