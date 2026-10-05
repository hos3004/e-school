<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts\DTOs;

/**
 * الصورة النهائية القابلة للعرض لمستخدم — رابط جاهز فقط.
 * لا يُكشف أبدًا المسار الخام المخزَّن في users.avatar_path.
 */
final readonly class AvatarPresentation
{
    public function __construct(
        public string $url,
        public bool $isDefault,
    ) {}
}
