<?php

declare(strict_types=1);

namespace Modules\Identity\Domain\Contracts\DTOs;

/** بيانات الحساب العامة التي يجوز إعادتها للموديولات الأخرى. */
final readonly class UserAccountData
{
    public function __construct(
        public string $id,
        public string $organizationId,
        public string $name,
        public ?string $email,
        public string $username,
        public ?string $phone,
        public string $status,
    ) {}
}
