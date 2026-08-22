<?php

declare(strict_types=1);

namespace Modules\Content\Domain\Enums;

/**
 * نوع المادة التعليمية داخل الكورس.
 *
 * النوع يحدّد القواعد: ملف مرفوع يحتاج disk و path،
 * ورابط خارجي يحتاج external_url فقط.
 */
enum MaterialType: string
{
    /** ملف مرفوع على تخزين المنصة (PDF، صورة، فيديو...). */
    case File = 'file';

    /** رابط خارجي (YouTube، Google Drive، مقال...). */
    case Link = 'link';

    /** هل هذا النوع يتطلب ملفًا مرفوعًا؟ */
    public function requiresFile(): bool
    {
        return $this === self::File;
    }

    /** هل هذا النوع يتطلب رابطًا خارجيًا؟ */
    public function requiresExternalUrl(): bool
    {
        return $this === self::Link;
    }

    public function label(): string
    {
        return __('content::material_type.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
