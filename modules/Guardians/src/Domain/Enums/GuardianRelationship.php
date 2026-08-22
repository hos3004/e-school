<?php

declare(strict_types=1);

namespace Modules\Guardians\Domain\Enums;

/**
 * صلة القرابة بين الوصي والطالب.
 *
 * قيمة معجمية ثابتة في guardian_links.relationship — لا نصوص حرة،
 * حتى تبقى التقارير والتصفية متسقة عبر المؤسسات.
 */
enum GuardianRelationship: string
{
    case Father = 'father';
    case Mother = 'mother';
    case Grandfather = 'grandfather';
    case Grandmother = 'grandmother';
    case Brother = 'brother';
    case Sister = 'sister';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case LegalGuardian = 'legal_guardian';
    case Other = 'other';

    /**
     * @return list<self>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): self => $case,
            self::cases(),
        );
    }

    public function label(): string
    {
        return __('guardians::relationship.'.$this->value);
    }
}
