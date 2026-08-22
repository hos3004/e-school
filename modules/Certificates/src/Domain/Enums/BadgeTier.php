<?php

declare(strict_types=1);

namespace Modules\Certificates\Domain\Enums;

/**
 * مستوى الشارة — يحدد لون العرض وترتيبها بين الشارات.
 *
 * القيم محصورة بما يقبلها عمود badges.tier في قاعدة البيانات:
 * bronze | silver | gold
 */
enum BadgeTier: string
{
    case Bronze = 'bronze';

    case Silver = 'silver';

    case Gold = 'gold';

    /**
     * كل المستويات مرتبة تصاعديًا حسب الأهمية.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Bronze, self::Silver, self::Gold];
    }

    public function label(): string
    {
        return __('certificates::tier.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Bronze => 'amber',
            self::Silver => 'gray',
            self::Gold => 'warning',
        };
    }
}
